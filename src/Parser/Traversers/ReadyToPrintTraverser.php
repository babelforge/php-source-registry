<?php

declare(strict_types=1);

namespace BabelForge\PhpSource\Parser\Traversers;

use BabelForge\PhpSource\Parser\Traversers\Visitors\ImportFullyQualifiedNameVisitor;

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
