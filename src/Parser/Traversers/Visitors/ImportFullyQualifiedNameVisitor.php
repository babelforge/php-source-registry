<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Parser\Traversers\Visitors;

use PhpNoobs\PhpSource\Parser\AbstractNodeVisitor;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use RuntimeException;

/**
 * Rewrites fully-qualified class names used in code into short names and
 * injects corresponding use statements into the file.
 *
 * Example:
 * - "\App\Dto\UserDto" becomes "UserDto"
 * - "use App\Dto\UserDto;" is added if missing
 *
 * This visitor intentionally ignores names located inside namespace and use
 * declarations, and only rewrites names that appear in executable/code-level
 * positions.
 */
final class ImportFullyQualifiedNameVisitor extends AbstractNodeVisitor
{
    /**
     * @var array<string, string>
     */
    private array $existingUsesByAlias = [];

    /**
     * @var array<string, string>
     */
    private array $plannedUsesByAlias = [];

    /**
     * @var Namespace_|null
     */
    private ?Namespace_ $namespace = null;

    /**
     * @var array<string, string> fqcn => alias
     */
    private array $existingAliasesByFqcn = [];

    public function updatedAst(): bool
    {
        return true;
    }

    /**
     * Resets the visitor state before traversing a new file AST.
     * @param Node[] $nodes
     * @return ?Node[]
     */
    public function beforeTraverse(array $nodes): ?array
    {
        $this->existingUsesByAlias = [];
        $this->plannedUsesByAlias = [];
        $this->namespace = null;

        $this->collectExistingUses($nodes);

        return null;
    }

    /**
     * Rewrites fully-qualified names found in code into short names when safe.
     *
     * @throws RuntimeException When the FQCN short name cannot be determined.
     */
    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof Namespace_) {
            $this->namespace = $node;

            return null;
        }

        if ($node instanceof TraitUse) {
            $this->processTraitUse($node);

            return null;
        }

        if (!$node instanceof FullyQualified) {
            return null;
        }

        if ($this->isNamespaceOrUseDeclarationName($node)) {
            return null;
        }

        $fqcn = $node->toString();
        $alias = $this->existingAliasesByFqcn[$fqcn] ?? $node->getLast();

        if ('' === $alias) {
            throw new RuntimeException(sprintf('Unable to determine alias for FQCN "%s".', $fqcn));
        }

        if (isset($this->existingUsesByAlias[$alias])) {
            if ($this->existingUsesByAlias[$alias] === $fqcn) {
                return new Name($alias, $node->getAttributes());
            }

            return null;
        }

        if (isset($this->plannedUsesByAlias[$alias])) {
            if ($this->plannedUsesByAlias[$alias] === $fqcn) {
                return new Name($alias, $node->getAttributes());
            }

            return null;
        }

        $this->plannedUsesByAlias[$alias] = $fqcn;

        return new Name($alias, $node->getAttributes());
    }

    /**
     * Injects the missing use statements after traversal.
     *
     * @param Node[] $nodes
     *
     * @return ?Node[]
     */
    public function afterTraverse(array $nodes): ?array
    {
        if ([] === $this->plannedUsesByAlias) {
            return null;
        }

        if (null !== $this->namespace) {
            $this->injectUsesIntoNamespace($this->namespace);

            return $nodes;
        }

        return $this->injectUsesIntoRoot($nodes);
    }

    /**
     * Collects existing imports from the file to avoid duplicates and collisions.
     *
     * @param Node[] $nodes
     *
     * @return void
     */
    private function collectExistingUses(array $nodes): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof Namespace_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Use_) {
                        $this->registerUseStatement($stmt);
                        continue;
                    }

                    if ($stmt instanceof GroupUse) {
                        $this->registerGroupUseStatement($stmt);
                    }
                }

                continue;
            }

            if ($node instanceof Use_) {
                $this->registerUseStatement($node);
                continue;
            }

            if ($node instanceof GroupUse) {
                $this->registerGroupUseStatement($node);
            }
        }
    }

    /**
     * Registers aliases already declared by use statements.
     *
     * @param Use_ $use
     * @return void
     */
    private function registerUseStatement(Use_ $use): void
    {
        foreach ($use->uses as $useUse) {
            $alias = $useUse->getAlias()->toString();
            $fqcn = $useUse->name->toString();

            $this->existingUsesByAlias[$alias] = $fqcn;
            $this->existingAliasesByFqcn[$fqcn] = $alias;
        }
    }

    /**
     * Registers aliases already declared by one grouped use statement.
     *
     * @param GroupUse $groupUse
     *
     * @return void
     */
    private function registerGroupUseStatement(GroupUse $groupUse): void
    {
        $prefix = ltrim($groupUse->prefix->toString(), '\\');

        foreach ($groupUse->uses as $useItem) {
            $suffix = ltrim($useItem->name->toString(), '\\');

            $fqcn = '' === $prefix
                ? $suffix
                : $prefix . '\\' . $suffix;

            $alias = $useItem->getAlias()->toString();

            $this->existingUsesByAlias[$alias] = $fqcn;
            $this->existingAliasesByFqcn[$fqcn] = $alias;
        }
    }

    /**
     * Determines whether the given name node is the name of a namespace
     * declaration or of a use declaration.
     */
    private function isNamespaceOrUseDeclarationName(Node $node): bool
    {
        $parent = $node->getAttribute('parent');

        return
            ($parent instanceof Namespace_ && $parent->name === $node)
            || ($parent instanceof UseItem && $parent->name === $node);
    }

    /**
     * Injects planned imports inside a namespace block.
     *
     * @param Namespace_ $namespace
     * @return void
     */
    private function injectUsesIntoNamespace(Namespace_ $namespace): void
    {
        $uses = $this->createUseStatements();

        if ([] === $uses) {
            return;
        }

        $firstNonUseIndex = null;

        foreach ($namespace->stmts as $index => $stmt) {
            if (!$stmt instanceof Use_) {
                $firstNonUseIndex = $index;

                break;
            }
        }

        if (null === $firstNonUseIndex) {
            $namespace->stmts = [
                ...$namespace->stmts,
                ...$uses,
                //new Stmt\Nop()
            ];

            return;
        }

        array_splice($namespace->stmts, $firstNonUseIndex, 0, $uses);
    }

    /**
     * Injects planned imports at root level for files without namespace.
     *
     * @param array<int, Node> $nodes
     *
     * @return array<int, Node>
     */
    private function injectUsesIntoRoot(array $nodes): array
    {
        $uses = $this->createUseStatements();

        if ([] === $uses) {
            return $nodes;
        }

        $firstNonDeclareOrUseIndex = 0;

        foreach ($nodes as $index => $node) {
            if ($node instanceof Stmt\Declare_ || $node instanceof Use_) {
                $firstNonDeclareOrUseIndex = $index + 1;

                continue;
            }

            break;
        }

        array_splice($nodes, $firstNonDeclareOrUseIndex, 0, $uses);

        return $nodes;
    }

    /**
     * Builds the use statements to inject, sorted alphabetically by alias.
     *
     * @return array<int, Use_>
     */
    private function createUseStatements(): array
    {
        if ([] === $this->plannedUsesByAlias) {
            return [];
        }

        ksort($this->plannedUsesByAlias);

        $uses = [];

        foreach ($this->plannedUsesByAlias as $alias => $fqcn) {
            $useItem = new UseItem(new Name($fqcn));

            if (substr($fqcn, strrpos($fqcn, '\\') + 1) !== $alias) {
                $useItem->alias = new Identifier($alias);
            }

            $uses[] = new Use_([$useItem]);
        }

        return $uses;
    }

    /**
     * Processes one TraitUse statement and normalizes fully-qualified trait names.
     *
     * @param TraitUse $traitUse
     *
     * @return void
     */
    private function processTraitUse(TraitUse $traitUse): void
    {
        foreach ($traitUse->traits as $i => $traitName) {
            if (!$traitName instanceof FullyQualified) {
                continue;
            }

            $fqcn = $traitName->toString();

            $alias = $this->existingAliasesByFqcn[$fqcn] ?? $traitName->getLast();

            if (isset($this->existingUsesByAlias[$alias])) {
                if ($this->existingUsesByAlias[$alias] === $fqcn) {
                    $traitUse->traits[$i] = new Name($alias, $traitName->getAttributes());
                }

                continue;
            }

            if (isset($this->plannedUsesByAlias[$alias])) {
                if ($this->plannedUsesByAlias[$alias] === $fqcn) {
                    $traitUse->traits[$i] = new Name($alias, $traitName->getAttributes());
                }

                continue;
            }

            $this->plannedUsesByAlias[$alias] = $fqcn;

            $traitUse->traits[$i] = new Name($alias, $traitName->getAttributes());
        }
    }
}
