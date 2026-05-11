<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Tests;

use PhpNoobs\PhpSource\PhpSourceRegistry;
use PhpNoobs\PhpSource\PhpSourceRegistryInstance;
use PhpNoobs\PhpSource\Tests\Support\InMemoryFileWriter;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PHPUnit\Framework\TestCase;

/**
 * Covers registry loading, virtual file updates, and saving.
 */
final class PhpSourceRegistryInstanceTest extends TestCase
{
    /**
     * Ensures the registry loads virtual files, updates one AST, and saves the physical file.
     */
    public function testItUpdatesAndSavesVirtualFileAst(): void
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'php-source-registry-');
        self::assertIsString($sourcePath);
        file_put_contents($sourcePath, <<<'PHP'
            <?php

            namespace App;

            final class OldName
            {
            }
            PHP);

        $fileWriter = new InMemoryFileWriter();
        $registry = new PhpSourceRegistryInstance($fileWriter);
        $virtualFiles = $registry->getVirtualFiles($sourcePath);
        $virtualFile = $virtualFiles->get(0);

        self::assertNotNull($virtualFile);

        $nodes = $virtualFile->getAst();
        $this->renameFirstClass($nodes, 'NewName');

        $registry->updateVirtualFileAst($virtualFile->virtualFilePath, $nodes);
        $registry->save();

        self::assertStringContainsString('final class NewName', $fileWriter->contentFor($sourcePath) ?? '');

        unlink($sourcePath);
    }

    /**
     * Ensures the registry keeps one loaded file state for repeated calls.
     */
    public function testItReturnsSameLoadedVirtualFilesForRepeatedCalls(): void
    {
        $sourcePath = $this->createTemporaryPhpFile(<<<'PHP'
            <?php

            namespace App;

            final class CachedFile
            {
            }
            PHP);

        $registry = new PhpSourceRegistryInstance();
        $firstVirtualFiles = $registry->getVirtualFiles($sourcePath);
        $secondVirtualFiles = $registry->getVirtualFiles($sourcePath);

        self::assertSame($firstVirtualFiles, $secondVirtualFiles);
        self::assertSame($firstVirtualFiles->get(0), $secondVirtualFiles->get(0));

        unlink($sourcePath);
    }

    /**
     * Ensures save does not write untouched source files.
     */
    public function testSaveDoesNotWriteUnchangedFiles(): void
    {
        $sourcePath = $this->createTemporaryPhpFile(<<<'PHP'
            <?php

            namespace App;

            final class UnchangedFile
            {
            }
            PHP);

        $fileWriter = new InMemoryFileWriter();
        $registry = new PhpSourceRegistryInstance($fileWriter);
        $registry->getVirtualFiles($sourcePath);

        $registry->save();

        self::assertSame(0, $fileWriter->writeCount());
        self::assertNull($fileWriter->contentFor($sourcePath));

        unlink($sourcePath);
    }

    /**
     * Ensures the static facade clear resets the current registry instance.
     */
    public function testStaticFacadeClearResetsLoadedSourceState(): void
    {
        $sourcePath = $this->createTemporaryPhpFile(<<<'PHP'
            <?php

            namespace App;

            final class FacadeCachedFile
            {
            }
            PHP);

        PhpSourceRegistry::clear();
        $firstVirtualFiles = PhpSourceRegistry::getVirtualFiles($sourcePath);
        PhpSourceRegistry::clear();
        $secondVirtualFiles = PhpSourceRegistry::getVirtualFiles($sourcePath);
        PhpSourceRegistry::clear();

        self::assertNotSame($firstVirtualFiles, $secondVirtualFiles);
        self::assertNotSame($firstVirtualFiles->get(0), $secondVirtualFiles->get(0));

        unlink($sourcePath);
    }

    /**
     * Ensures code returns the assembled source for an unmodified file.
     */
    public function testCodeReturnsAssembledSourceForUnmodifiedFile(): void
    {
        $sourcePath = $this->createTemporaryPhpFile(<<<'PHP'
            <?php

            namespace App;

            final class PrintableFile
            {
            }
            PHP);

        $code = new PhpSourceRegistryInstance()->code($sourcePath);

        self::assertStringContainsString('namespace App;', $code);
        self::assertStringContainsString('final class PrintableFile', $code);

        unlink($sourcePath);
    }

    /**
     * Ensures virtual file AST can be fetched directly and unknown paths fail clearly.
     */
    public function testItReturnsVirtualFileAstAndFailsForUnknownVirtualFile(): void
    {
        $sourcePath = $this->createTemporaryPhpFile(<<<'PHP'
            <?php

            namespace App;

            final class AstFile
            {
            }
            PHP);

        $registry = new PhpSourceRegistryInstance();
        $virtualFile = $registry->getVirtualFiles($sourcePath)->get(0);
        self::assertNotNull($virtualFile);

        $ast = $registry->getVirtualFileAst($virtualFile->virtualFilePath);

        self::assertNotSame([], $ast);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Virtual file /project/missing.php.virtual.0 not found');

        try {
            $registry->getVirtualFileAst('/project/missing.php.virtual.0');
        } finally {
            unlink($sourcePath);
        }
    }

    /**
     * Ensures save reboots updated virtual files after writing.
     */
    public function testSaveRebootsUpdatedVirtualFilesAfterWriting(): void
    {
        $sourcePath = $this->createTemporaryPhpFile(<<<'PHP'
            <?php

            namespace App;

            final class BeforeSave
            {
            }
            PHP);

        $registry = new PhpSourceRegistryInstance(new InMemoryFileWriter());
        $virtualFile = $registry->getVirtualFiles($sourcePath)->get(0);
        self::assertNotNull($virtualFile);

        $nodes = $virtualFile->getAst();
        $this->renameFirstClass($nodes, 'AfterSave');
        $registry->updateVirtualFileAst($virtualFile->virtualFilePath, $nodes);

        self::assertTrue($virtualFile->isUpdated());

        $registry->save();

        self::assertFalse($virtualFile->isUpdated());

        unlink($sourcePath);
    }

    /**
     * Creates a temporary PHP source file.
     *
     * @param string $code the PHP source code
     */
    private function createTemporaryPhpFile(string $code): string
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'php-source-registry-');
        self::assertIsString($sourcePath);
        file_put_contents($sourcePath, $code);

        return $sourcePath;
    }

    /**
     * Renames the first class found in a node list.
     *
     * @param array<object> $nodes   the node list
     * @param string        $newName the new class name
     */
    private function renameFirstClass(array $nodes, string $newName): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof Class_) {
                $node->name = new Identifier($newName);

                return;
            }

            if ($node instanceof Namespace_) {
                $this->renameFirstClass($node->stmts, $newName);
            }
        }
    }
}
