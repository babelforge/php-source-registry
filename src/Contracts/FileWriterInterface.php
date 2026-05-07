<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Contracts;

use PhpParser\Node;
use RuntimeException;

interface FileWriterInterface
{
    /**
     * @param Node[] $ast
     * @param string $filePath
     * @return void
     *
     * @throws RuntimeException
     */
    public function writeAst(array $ast, string $filePath): void;

    public function writeContent(string $filePath, string $content): void;

    public function createDirectory(string $dir): void;

    public function checkDirExists(string $dir): void;

}
