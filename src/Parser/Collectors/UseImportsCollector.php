<?php

declare(strict_types=1);

namespace BabelForge\PhpSource\Parser\Collectors;

use BabelForge\PhpSource\Parser\AbstractNodeTraverser;
use BabelForge\PhpSource\Parser\Collectors\Visitors\CollectUseImportsVisitor;
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
     * @return Node[] the traversed nodes
     */
    public function collect(array $nodes): array
    {
        return $this->traverse($nodes);
    }
}
