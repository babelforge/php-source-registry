<?php

declare(strict_types=1);

namespace BabelForge\PhpSource\Parser;

use PhpParser\NodeVisitorAbstract;

/**
 * Class AbstractAstUpdaterNodeVisitor.
 */
abstract class AbstractNodeVisitor extends NodeVisitorAbstract
{
    protected bool $updatedAst = false;

    /**
     * Indicates whether the AST was modified during traversal.
     *
     * @return bool true when the AST was updated, false otherwise
     */
    public function updatedAst(): bool
    {
        return $this->updatedAst;
    }

    protected function markAstUpdated(): void
    {
        $this->updatedAst = true;
    }
}
