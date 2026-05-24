<?php

declare(strict_types=1);

namespace BabelForge\PhpSource\Writer;

use BabelForge\PhpSource\Contracts\FileWriterInterface;
use BabelForge\PhpSource\Printer\NopPrinter;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;

/**
 * Writes PHP source files to the local filesystem.
 */
final readonly class NativeFileWriter implements FileWriterInterface
{
    /**
     * Constructor.
     *
     * @param Standard $printer the printer used to render AST nodes
     */
    public function __construct(
        private Standard $printer = new NopPrinter(),
    ) {
    }

    /**
     * Writes an AST to a file path.
     *
     * @param Node[] $ast      the AST nodes to write
     * @param string $filePath the target file path
     *
     * @throws \RuntimeException when printing or writing fails
     */
    public function writeAst(array $ast, string $filePath): void
    {
        $this->writeContent($filePath, $this->printer->prettyPrintFile($ast));
    }

    /**
     * Writes content to a file path.
     *
     * @param string $filePath the target file path
     * @param string $content  the content to write
     *
     * @throws \RuntimeException when the parent directory or file write fails
     */
    public function writeContent(string $filePath, string $content): void
    {
        $directory = dirname($filePath);

        $this->createDirectory($directory);
        $this->checkDirExists($directory);

        $temporaryFile = tempnam($directory, basename($filePath).'.tmp.');

        if (false === $temporaryFile) {
            throw new \RuntimeException(sprintf('Unable to create temporary file in "%s".', $directory));
        }

        if (false === file_put_contents($temporaryFile, $content)) {
            $this->removeTemporaryFile($temporaryFile);

            throw new \RuntimeException(sprintf('Unable to write temporary file "%s".', $temporaryFile));
        }

        if (!rename($temporaryFile, $filePath)) {
            $this->removeTemporaryFile($temporaryFile);

            throw new \RuntimeException(sprintf('Unable to move temporary file "%s" to "%s".', $temporaryFile, $filePath));
        }
    }

    /**
     * Creates a directory recursively when missing.
     *
     * @param string $dir the directory path
     *
     * @throws \RuntimeException when the directory cannot be created
     */
    public function createDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (file_exists($dir)) {
            throw new \RuntimeException(sprintf('Path "%s" exists but is not a directory.', $dir));
        }

        if (!mkdir($dir, 0o777, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Unable to create directory "%s".', $dir));
        }
    }

    /**
     * Checks that a path exists and is a directory.
     *
     * @param string $dir the directory path
     *
     * @throws \RuntimeException when the path is not an existing directory
     */
    public function checkDirExists(string $dir): void
    {
        if (!is_dir($dir)) {
            throw new \RuntimeException(sprintf('Directory "%s" does not exist.', $dir));
        }
    }

    /**
     * Removes a temporary file after a failed write step.
     *
     * @param string $temporaryFile the temporary file path
     */
    private function removeTemporaryFile(string $temporaryFile): void
    {
        if (is_file($temporaryFile)) {
            unlink($temporaryFile);
        }
    }
}
