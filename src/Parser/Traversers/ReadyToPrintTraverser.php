<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Parser\Traversers;

use PhpNoobs\PhpSource\Parser\Traversers\Visitors\ImportFullyQualifiedNameVisitor;

/**
 * Class ReadyToPrintTraverser.
 */
final class ReadyToPrintTraverser extends NopRefactorTraverser
{
    protected function getVisitors(): array
    {
        return array_merge(parent::getVisitors(), [new ImportFullyQualifiedNameVisitor()]);
    }
}
