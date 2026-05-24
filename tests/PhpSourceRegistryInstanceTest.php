<?php

declare(strict_types=1);

namespace BabelForge\PhpSource\Tests;

use BabelForge\PhpSource\PhpSourceRegistry;
use BabelForge\PhpSource\PhpSourceRegistryInstance;
use BabelForge\PhpSource\Tests\Support\InMemoryFileWriter;
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
     * Ensures the registry writes updated virtual files with the default native writer.
     */
    public function testSaveWritesWithDefaultNativeFileWriter(): void
    {
        $sourcePath = $this->createTemporaryPhpFile(<<<'PHP'
            <?php

            namespace App;

            final class NativeBeforeSave
            {
            }
            PHP);

        $registry = new PhpSourceRegistryInstance();
        $virtualFile = $registry->getVirtualFiles($sourcePath)->get(0);
        self::assertNotNull($virtualFile);

        $nodes = $virtualFile->getAst();
        $this->renameFirstClass($nodes, 'NativeAfterSave');
        $registry->updateVirtualFileAst($virtualFile->virtualFilePath, $nodes);

        $registry->save();

        $content = file_get_contents($sourcePath);
        self::assertIsString($content);
        self::assertStringContainsString('final class NativeAfterSave', $content);
        self::assertFalse($virtualFile->isUpdated());

        unlink($sourcePath);
    }

    /**
     * Ensures one source file can be saved without writing other updated files.
     */
    public function testItSavesOneSourceFile(): void
    {
        $firstSourcePath = $this->createTemporaryPhpFile(<<<'PHP'
            <?php

            namespace App;

            final class FirstBeforeSave
            {
            }
            PHP);
        $secondSourcePath = $this->createTemporaryPhpFile(<<<'PHP'
            <?php

            namespace App;

            final class SecondBeforeSave
            {
            }
            PHP);

        $fileWriter = new InMemoryFileWriter();
        $registry = new PhpSourceRegistryInstance($fileWriter);

        $firstVirtualFile = $registry->getVirtualFiles($firstSourcePath)->get(0);
        $secondVirtualFile = $registry->getVirtualFiles($secondSourcePath)->get(0);
        self::assertNotNull($firstVirtualFile);
        self::assertNotNull($secondVirtualFile);

        $firstNodes = $firstVirtualFile->getAst();
        $this->renameFirstClass($firstNodes, 'FirstAfterSave');
        $registry->updateVirtualFileAst($firstVirtualFile->virtualFilePath, $firstNodes);

        $secondNodes = $secondVirtualFile->getAst();
        $this->renameFirstClass($secondNodes, 'SecondAfterSave');
        $registry->updateVirtualFileAst($secondVirtualFile->virtualFilePath, $secondNodes);

        $registry->saveSourceFile($firstSourcePath);

        self::assertSame(1, $fileWriter->writeCount());
        self::assertStringContainsString('final class FirstAfterSave', $fileWriter->contentFor($firstSourcePath) ?? '');
        self::assertNull($fileWriter->contentFor($secondSourcePath));
        self::assertFalse($firstVirtualFile->isUpdated());
        self::assertTrue($secondVirtualFile->isUpdated());

        unlink($firstSourcePath);
        unlink($secondSourcePath);
    }

    /**
     * Ensures saving one known unchanged source file does not write.
     */
    public function testSaveSourceFileDoesNotWriteUnchangedSourceFile(): void
    {
        $sourcePath = $this->createTemporaryPhpFile(<<<'PHP'
            <?php

            namespace App;

            final class UnchangedSingleFile
            {
            }
            PHP);

        $fileWriter = new InMemoryFileWriter();
        $registry = new PhpSourceRegistryInstance($fileWriter);
        $registry->getVirtualFiles($sourcePath);

        $registry->saveSourceFile($sourcePath);

        self::assertSame(0, $fileWriter->writeCount());
        self::assertNull($fileWriter->contentFor($sourcePath));

        unlink($sourcePath);
    }

    /**
     * Ensures saving one source file accepts an equivalent non-normalized path.
     */
    public function testSaveSourceFileNormalizesEquivalentSourceFilePath(): void
    {
        $sourceDirectory = sys_get_temp_dir().'/php-source-registry-source-'.bin2hex(random_bytes(8));
        $nestedDirectory = $sourceDirectory.'/src/nested';
        mkdir($nestedDirectory, 0o777, true);

        $sourcePath = $sourceDirectory.'/src/Mailer.php';
        file_put_contents($sourcePath, <<<'PHP'
            <?php

            namespace App;

            final class MailerBeforeSave
            {
            }
            PHP);

        $fileWriter = new InMemoryFileWriter();
        $registry = new PhpSourceRegistryInstance($fileWriter);
        $virtualFile = $registry->getVirtualFiles($sourcePath)->get(0);
        self::assertNotNull($virtualFile);

        $nodes = $virtualFile->getAst();
        $this->renameFirstClass($nodes, 'MailerAfterSave');
        $registry->updateVirtualFileAst($virtualFile->virtualFilePath, $nodes);

        $registry->saveSourceFile($nestedDirectory.'/../Mailer.php');

        $normalizedSourcePath = realpath($sourcePath);
        self::assertIsString($normalizedSourcePath);
        self::assertStringContainsString('final class MailerAfterSave', $fileWriter->contentFor($normalizedSourcePath) ?? '');
        self::assertFalse($virtualFile->isUpdated());

        unlink($sourcePath);
        rmdir($nestedDirectory);
        rmdir($sourceDirectory.'/src');
        rmdir($sourceDirectory);
    }

    /**
     * Ensures saving an unknown source file fails clearly.
     */
    public function testSaveSourceFileFailsForUnknownSourceFile(): void
    {
        $registry = new PhpSourceRegistryInstance(new InMemoryFileWriter());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Source file /project/src/Missing.php not found');

        $registry->saveSourceFile('/project/src/Missing.php');
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
