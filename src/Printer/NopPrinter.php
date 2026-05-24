<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace BabelForge\PhpSource\Printer;

use BabelForge\PhpSource\Parser\Traversers\ReadyToPrintTraverser;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\Token;

/**
 * Standard printer that moves FQCNs to use imports, adds spaces between blocks and removes double lines.
 */
class NopPrinter extends Standard
{
    /**
     * @param Node[] $stmts
     */
    public function prettyPrintFile(array $stmts): string
    {
        return parent::prettyPrintFile(
            self::prepare($stmts),
        );
    }

    /**
     * @param Node[] $stmts
     */
    public function prettyPrint(array $stmts): string
    {
        return parent::prettyPrint(
            self::prepare($stmts),
        );
    }

    /**
     * @param Node[]  $stmts
     * @param Node[]  $origStmts
     * @param Token[] $origTokens
     */
    public function printFormatPreserving(array $stmts, array $origStmts, array $origTokens): string
    {
        return parent::printFormatPreserving(
            self::prepare($stmts),
            $origStmts,
            $origTokens
        );
    }

    /**
     * @param Node[] $stmts
     *
     * @return Node[]
     */
    private static function prepare(array $stmts): array
    {
        return new ReadyToPrintTraverser()->traverse($stmts);
        // return new NopRefactorTraverser()->traverse($stmts);
    }
}
