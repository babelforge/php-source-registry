<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Tests\Writer;

use PhpNoobs\PhpSource\Parser\UserLandParser;
use PhpNoobs\PhpSource\Writer\NativeFileWriter;
use PHPUnit\Framework\TestCase;

/**
 * Covers native filesystem writes.
 */
final class NativeFileWriterTest extends TestCase
{
    /**
     * Ensures content can be written to an existing file.
     */
    public function testItWritesContentToExistingFile(): void
    {
        $directory = $this->createTemporaryDirectory();
        $filePath = $directory.'/Existing.php';
        file_put_contents($filePath, 'old content');

        new NativeFileWriter()->writeContent($filePath, '<?php echo "new";');

        self::assertSame('<?php echo "new";', file_get_contents($filePath));

        $this->removeDirectory($directory);
    }

    /**
     * Ensures content can be written to a file in a missing nested directory.
     */
    public function testItWritesContentToMissingNestedDirectory(): void
    {
        $directory = $this->createTemporaryDirectory();
        $filePath = $directory.'/nested/source/NewFile.php';

        new NativeFileWriter()->writeContent($filePath, '<?php echo "created";');

        self::assertSame('<?php echo "created";', file_get_contents($filePath));

        $this->removeDirectory($directory);
    }

    /**
     * Ensures AST nodes can be written to a file.
     */
    public function testItWritesAstToFile(): void
    {
        $directory = $this->createTemporaryDirectory();
        $filePath = $directory.'/AstFile.php';
        $ast = new UserLandParser()->simpleParseCode(<<<'PHP'
            <?php

            namespace App;

            final class WrittenFromAst
            {
            }
            PHP);

        new NativeFileWriter()->writeAst($ast, $filePath);

        $content = file_get_contents($filePath);
        self::assertIsString($content);
        self::assertStringContainsString('namespace App;', $content);
        self::assertStringContainsString('final class WrittenFromAst', $content);

        $this->removeDirectory($directory);
    }

    /**
     * Ensures invalid parent paths fail clearly.
     */
    public function testItFailsWhenParentPathCannotBeCreated(): void
    {
        $directory = $this->createTemporaryDirectory();
        $invalidParent = $directory.'/not-a-directory';
        file_put_contents($invalidParent, 'content');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Path "%s" exists but is not a directory.', $invalidParent));

        try {
            new NativeFileWriter()->writeContent($invalidParent.'/File.php', '<?php');
        } finally {
            $this->removeDirectory($directory);
        }
    }

    /**
     * Ensures missing directories can be created recursively.
     */
    public function testItCreatesDirectoryRecursively(): void
    {
        $directory = $this->createTemporaryDirectory();
        $nestedDirectory = $directory.'/a/b/c';

        new NativeFileWriter()->createDirectory($nestedDirectory);

        self::assertDirectoryExists($nestedDirectory);

        $this->removeDirectory($directory);
    }

    /**
     * Ensures directory checks fail for missing directories.
     */
    public function testItFailsWhenDirectoryDoesNotExist(): void
    {
        $directory = $this->createTemporaryDirectory();
        $missingDirectory = $directory.'/missing';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Directory "%s" does not exist.', $missingDirectory));

        try {
            new NativeFileWriter()->checkDirExists($missingDirectory);
        } finally {
            $this->removeDirectory($directory);
        }
    }

    /**
     * Creates a temporary directory for filesystem tests.
     */
    private function createTemporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().'/php-source-registry-writer-'.bin2hex(random_bytes(8));
        mkdir($directory, 0o777, true);

        return $directory;
    }

    /**
     * Removes a directory recursively.
     *
     * @param string $directory the directory path
     */
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                continue;
            }

            if ($fileInfo->isDir()) {
                rmdir($fileInfo->getPathname());

                continue;
            }

            unlink($fileInfo->getPathname());
        }

        rmdir($directory);
    }
}
