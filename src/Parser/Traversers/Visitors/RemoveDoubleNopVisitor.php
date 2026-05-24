<?php

declare(strict_types=1);

namespace BabelForge\PhpSource\Parser\Traversers\Visitors;

use BabelForge\PhpSource\Parser\AbstractNodeVisitor;
use PhpParser\Node;
use PhpParser\Node\Stmt\Nop;

/**
 * Removes consecutive Nop statements (blank lines) from the AST.
 *
 * This visitor ensures that multiple sequential Nop nodes
 * are reduced to a single Nop.
 */
final class RemoveDoubleNopVisitor extends AbstractNodeVisitor
{
    /**
     * Cleans consecutive Nop statements inside any node that contains "stmts".
     *
     * @param Node $node the current AST node
     *
     * @return Node|null the possibly modified node
     */
    public function leaveNode(Node $node): ?Node
    {
        if (!property_exists($node, 'stmts') || !is_array($node->stmts)) {
            return null;
        }

        $filtered = [];
        $previousWasNop = false;

        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Nop) {
                if ($previousWasNop) {
                    continue;
                }

                $previousWasNop = true;
                $filtered[] = $stmt;
                continue;
            }

            $previousWasNop = false;
            $filtered[] = $stmt;
            $this->markAstUpdated();
        }

        $node->stmts = $filtered;

        return $node;
    }
}
