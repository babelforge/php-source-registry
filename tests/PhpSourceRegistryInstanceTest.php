<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Tests;

use PhpNoobs\PhpSource\PhpSourceRegistryInstance;
use PhpNoobs\PhpSource\Tests\Support\InMemoryFileWriter;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PHPUnit\Framework\TestCase;

/**
 * Covers registry loading, virtual file updates, and saving.
 */
final class PhpSourceRegistryInstanceTest extends TestCase
{
    /**
     * Ensures the registry loads virtual files, updates one AST, and saves the physical file.
     *
     * @return void
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
     * Renames the first class found in a node list.
     *
     * @param array<object> $nodes The node list.
     * @param string $newName The new class name.
     *
     * @return void
     */
    private function renameFirstClass(array $nodes, string $newName): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof Class_) {
                $node->name = new Identifier($newName);

                return;
            }

            if (property_exists($node, 'stmts') && is_array($node->stmts)) {
                $this->renameFirstClass($node->stmts, $newName);
            }
        }
    }
}
