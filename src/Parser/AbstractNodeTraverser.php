<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Parser;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Class AbstractTraverser
 */
abstract class AbstractNodeTraverser
{
    protected NodeTraverser $traverser;
    /**
     * @var AbstractNodeVisitor[]
     */
    private array $builtInVisitors;
    public function __construct(AbstractNodeVisitor ...$builtInVisitors)
    {
        $this->builtInVisitors = $builtInVisitors;
        $this->traverser = new NodeTraverser(...$this->builtInVisitors);
    }

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function traverse(array $nodes): array
    {
        $this->addVisitors();

        return $this->traverser->traverse($nodes);
    }

    /**
     * @param Node $node
     * @return ?Node
     */
    public function traverseNode(Node $node): ?Node
    {
        return $this->traverse([$node])[0];
    }

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function attach(array $nodes): array
    {
        return $this->traverse($nodes);
    }

    protected function addVisitors(): void
    {
        foreach ($this->getVisitors() as $visitor) {
            $this->traverser->addVisitor($visitor);
        }
    }

    /**
     * @return array<AbstractNodeVisitor|NodeVisitorAbstract>
     */
    protected function getVisitors(): array
    {
        return [];
    }

    public function updatedAst(): bool
    {
        return array_any(
            array_merge(
                $this->builtInVisitors,
                $this->getVisitors()
            ),
            fn ($visitor) => ($visitor instanceof AbstractNodeVisitor) && $visitor->updatedAst()
        );
    }
}
