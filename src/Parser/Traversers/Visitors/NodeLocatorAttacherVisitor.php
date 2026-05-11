<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Parser\Traversers\Visitors;

use PhpNoobs\PhpSource\Locator\NodeLocator;
use PhpNoobs\PhpSource\Parser\AbstractNodeVisitor;
use PhpNoobs\PhpSource\Parser\Traversers\NodeLocatorAttacher;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_ as StmtFunction;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;

/**
 * Visitor that attaches locators.
 */
final class NodeLocatorAttacherVisitor extends AbstractNodeVisitor
{
    private ?string $currentNamespace = null;

    /**
     * @var NodeLocator Fully qualified class-like name (e.g. "Acme\\Foo")
     */
    private NodeLocator $currentClassLikeFqcn;

    /**
     * @var list<string> Stack of current "scope locators" (function-like / closure-like).
     *                   Example: ["Acme\\Foo::bar", "Acme\\Foo::bar#closure(1)"]
     */
    private array $scopeStack = [];

    /**
     * @var array<string, int> counts for closures/arrows within a given scope locator
     */
    private array $closureCounters = [];

    /**
     * @var array<string, int> counts for arrows within a given scope locator
     */
    private array $arrowCounters = [];

    public function __construct()
    {
        $this->currentClassLikeFqcn = NodeLocator::null();
    }

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof Namespace_) {
            $this->currentNamespace = $node->name instanceof Name ? $node->name->toString() : null;

            return null;
        }

        if ($node instanceof ClassLike) {
            $nodeLocator = $this->buildClassLikeFqcn($node);
            $this->currentClassLikeFqcn = $nodeLocator->classLikeFqcn();
            if (!$this->currentClassLikeFqcn->isNull()) {
                $this->setAttribute($node, $this->currentClassLikeFqcn);
            }

            return null;
        }

        if ($node instanceof StmtFunction) {
            $fnLocator = $this->buildFunctionLocator($node);
            $this->setAttribute($node, $fnLocator);

            $this->pushScope($fnLocator);

            $this->attachParamsLocators($node->getParams(), $fnLocator);

            return null;
        }

        if ($node instanceof ClassMethod) {
            $methodLocator = $this->buildMethodLocator($node);
            // $node->setAttribute(NodeLocatorAttacher::ATTR_LOCATOR, $methodLocator);
            $this->setAttribute($node, $methodLocator);

            $this->pushScope($methodLocator);

            $this->attachParamsLocators($node->getParams(), $methodLocator);

            return null;
        }

        if ($node instanceof Closure) {
            $parentScope = $this->currentScopeOrFileFallback();
            $idx = $this->nextClosureIndex($parentScope);
            $closureLocator = NodeLocator::closureLike($parentScope, $idx);
            // $closureLocator = $parentScope . '#closure(' . $idx . ')';

            // $node->setAttribute(NodeLocatorAttacher::ATTR_LOCATOR, $closureLocator);
            $this->setAttribute($node, $closureLocator);

            $this->pushScope($closureLocator);

            $this->attachParamsLocators($node->getParams(), $closureLocator);

            return null;
        }

        if ($node instanceof ArrowFunction) {
            $parentScope = $this->currentScopeOrFileFallback();
            $idx = $this->nextArrowIndex($parentScope);
            $arrowLocator = NodeLocator::ArrowFunctionLike($parentScope, $idx);
            // $arrowLocator = $parentScope . '#arrow(' . $idx . ')';

            // $node->setAttribute(NodeLocatorAttacher::ATTR_LOCATOR, $arrowLocator);
            $this->setAttribute($node, $arrowLocator);

            $this->pushScope($arrowLocator);

            $this->attachParamsLocators($node->getParams(), $arrowLocator);

            return null;
        }

        if ($node instanceof Property) {
            $this->attachPropertyLocators($node);

            return null;
        }

        return null;
    }

    private function setAttribute(Node $node, NodeLocator $locator): void
    {
        $node->setAttribute(NodeLocatorAttacher::ATTR_LOCATOR, $locator->toString());
    }

    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof StmtFunction || $node instanceof ClassMethod || $node instanceof Closure || $node instanceof ArrowFunction) {
            $this->popScope();
        }

        if ($node instanceof ClassLike) {
            $this->currentClassLikeFqcn = NodeLocator::null();
        }

        return null;
    }

    /**
     * Build FQCN for class-like nodes (Class_/Interface_/Trait_/Enum).
     */
    private function buildClassLikeFqcn(ClassLike $classLike): NodeLocator
    {
        if (null === $classLike->name) {
            return NodeLocator::null();
        }

        $short = $classLike->name->toString();

        return NodeLocator::classLike($this->currentNamespace, $short);
    }

    /**
     * Build locator for a named function.
     */
    private function buildFunctionLocator(StmtFunction $function): NodeLocator
    {
        $short = $function->name->toString();

        return NodeLocator::functionLike($this->currentNamespace, $short);
    }

    /**
     * Build locator for a class method.
     */
    private function buildMethodLocator(ClassMethod $method): NodeLocator
    {
        $methodName = $method->name->toString();

        return NodeLocator::methodLike($this->currentNamespace, $methodName);
    }

    /**
     * Attach locators to params using their position in the signature (stable).
     *
     * @param array<Param> $params
     */
    private function attachParamsLocators(array $params, NodeLocator $scopeLocator): void
    {
        foreach ($params as $i => $param) {
            $paramLocator = NodeLocator::paramLike($scopeLocator, $i);
            // $paramLocator = $scopeLocator . '#param(' . $i . ')';
            $this->setAttribute($param, $paramLocator);

            // Optional: also attach to the variable itself (Expr\Variable) for convenience.
            $this->setAttribute($param->var, $paramLocator);
        }
    }

    /**
     * Attach locators to properties (each declared property name).
     *
     * Handles: public int $a, $b;
     */
    private function attachPropertyLocators(Property $property): void
    {
        if ($this->currentClassLikeFqcn->isNull()) {
            return;
        }

        foreach ($property->props as $prop) {
            $name = $prop->name->toString();
            $locator = NodeLocator::propertyLike($this->currentClassLikeFqcn, $name);
            // $locator = $this->currentClassLikeFqcn . '::$' . $name;

            // $prop->setAttribute(NodeLocatorAttacher::ATTR_LOCATOR, $locator);
            $this->setAttribute($prop, $locator);
            // $property->setAttribute(NodeLocatorAttacher::ATTR_LOCATOR, $this->currentClassLikeFqcn);
            $this->setAttribute($property, $this->currentClassLikeFqcn);
        }
    }

    private function currentScopeOrFileFallback(): string
    {
        $current = end($this->scopeStack);
        if (is_string($current) && '' !== $current) {
            return $current;
        }

        // Fallback scope used only if a closure appears outside any function/class context.
        // You can improve this by prefixing with file path if you carry it in AST attributes.
        return $this->currentNamespace ?? 'global';
    }

    /**
     * Push a new scope locator onto the stack.
     */
    private function pushScope(NodeLocator $locator): void
    {
        $this->scopeStack[] = $locator->toString();
    }

    /**
     * Pop current scope locator from the stack.
     */
    private function popScope(): void
    {
        array_pop($this->scopeStack);
    }

    /**
     * @return int 0-based index
     */
    private function nextClosureIndex(string $parentScope): int
    {
        $this->closureCounters[$parentScope] = ($this->closureCounters[$parentScope] ?? 0) + 1;

        return $this->closureCounters[$parentScope] - 1;
    }

    /**
     * @return int 0-based index
     */
    private function nextArrowIndex(string $parentScope): int
    {
        $this->arrowCounters[$parentScope] = ($this->arrowCounters[$parentScope] ?? 0) + 1;

        return $this->arrowCounters[$parentScope] - 1;
    }
}
