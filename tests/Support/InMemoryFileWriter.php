<?php

declare(strict_types=1);

namespace BabelForge\PhpSource\Tests\Support;

use BabelForge\PhpSource\Contracts\FileWriterInterface;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;

/**
 * Stores written file contents in memory for tests.
 */
final class InMemoryFileWriter implements FileWriterInterface
{
    /**
     * @var array<string, string>
     */
    private array $contentsByPath = [];

    private int $writeCount = 0;

    /**
     * Writes an AST for one path.
     *
     * @param Node[] $ast      the AST to write
     * @param string $filePath the written file path
     */
    public function writeAst(array $ast, string $filePath): void
    {
        $this->writeContent($filePath, new Standard()->prettyPrintFile($ast));
    }

    /**
     * Writes content for one path.
     *
     * @param string $filePath the written file path
     * @param string $content  the written file content
     */
    public function writeContent(string $filePath, string $content): void
    {
        ++$this->writeCount;
        $this->contentsByPath[$filePath] = $content;
    }

    /**
     * Creates a directory.
     *
     * @param string $dir the directory path
     */
    public function createDirectory(string $dir): void
    {
    }

    /**
     * Checks that a directory exists.
     *
     * @param string $dir the directory path
     */
    public function checkDirExists(string $dir): void
    {
    }

    /**
     * Returns written content for one path.
     *
     * @param string $path the file path
     */
    public function contentFor(string $path): ?string
    {
        return $this->contentsByPath[$path] ?? null;
    }

    /**
     * Counts writes performed by the writer.
     */
    public function writeCount(): int
    {
        return $this->writeCount;
    }
}
