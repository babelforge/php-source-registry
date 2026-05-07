<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Parser\Collectors;

use PhpNoobs\PhpSource\Parser\AbstractNodeTraverser;
use PhpNoobs\PhpSource\Parser\Collectors\Visitors\CollectUseImportsVisitor;
use PhpParser\Node;

/**
 * Builds a NodeTraverser configured with UseImportsCollectorVisitor.
 *
 * This traverser collects `use` imports (alias => FQCN) and stores them in node attributes
 * so that context factories can later read them.
 */
final class UseImportsCollector extends AbstractNodeTraverser
{
    public function __construct()
    {
        parent::__construct(new CollectUseImportsVisitor());
    }
    /**
     * Collects imports for the given AST nodes.
     *
     * @param Node[] $nodes
     *
     * @return Node[] The traversed nodes.
     */
    public function collect(array $nodes): array
    {
        return $this->traverse($nodes);
    }

}
