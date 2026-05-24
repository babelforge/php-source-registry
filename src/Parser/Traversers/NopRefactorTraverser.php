<?php

declare(strict_types=1);

namespace BabelForge\PhpSource\Parser\Traversers;

use PhpParser\Node;

/**
 * A utility class for processing nodes with no operational refactoring.
 *
 * This class provides a mechanism to traverse and process an array of nodes
 * using a specific visitor designed for handling spacing or formatting without
 * performing any substantial modifications to the nodes.
 */
class NopRefactorTraverser extends NopSpacingTraverser
{
    /**
     * @param Node[] $nodes
     *
     * @return Node[]
     */
    public function traverse(array $nodes): array
    {
        $stmts = parent::traverse($nodes);

        return new RemoveDoubleNopTraverser()->traverse($stmts);
    }
}
