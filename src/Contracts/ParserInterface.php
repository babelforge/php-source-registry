<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Contracts;

use PhpParser\Node;

interface ParserInterface
{
    /**
     * Parses a userland class/trait/interface file (Reflection-based).
     *
     * @param class-string $fqcn The FQCN to parse.
     *
     * @return Node[] File-level AST.
     */
    public function parseFqcn(string $fqcn): array;

    /**
     * Parses a file with possible extra processing.
     *
     * @param string $filePath
     * @return Node[]
     */
    public function parseFile(string $filePath): array;

    /**
     * Parses a file without any extra processing.
     *
     * @param string $filePath
     * @return Node[]
     */
    public function simpleParseFile(string $filePath): array;

    /**
     * Parses de code string with possible extra processing.
     *
     * @param string $code
     * @param string $filePath
     * @return Node[]
     */
    public function parseCode(string $code, string $filePath = ''): array;

    /**
     * Parses de code string without any extra processing.
     *
     * @param string $code
     * @param string $filePath
     * @return Node[]
     */
    public function simpleParseCode(string $code, string $filePath = ''): array;

    /**
     * @param Node[] $ast
     * @return Node[]
     */
    public function processExtraVisitors(array $ast): array;


    /**
     * @param Node[] $ast
     * @return Node[]
     */
    public function prettifyAndParse(array $ast): array;
}
