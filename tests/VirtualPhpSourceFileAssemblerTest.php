<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Tests;

use PhpNoobs\PhpSource\Parser\UserLandParser;
use PhpNoobs\PhpSource\VirtualPhpSourceFileAssembler;
use PhpNoobs\PhpSource\VirtualPhpSourceFileProducer;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\TestCase;

/**
 * Covers physical-file AST reassembly from virtual source files.
 */
final class VirtualPhpSourceFileAssemblerTest extends TestCase
{
    /**
     * Ensures split virtual files can be assembled back into one file without duplicate imports.
     */
    public function testItAssemblesVirtualFilesWithoutDuplicateImports(): void
    {
        $nodes = new UserLandParser()->simpleParseCode(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App;

            use DateTimeImmutable;

            final class First
            {
            }

            final class Second
            {
            }
            PHP, '/project/src/Example.php');

        $virtualFiles = new VirtualPhpSourceFileProducer()->produceVirtualPhpSourceFiles('/project/src/Example.php', $nodes);
        $assembledNodes = new VirtualPhpSourceFileAssembler()->assemble($virtualFiles);
        $code = new Standard()->prettyPrintFile($assembledNodes);

        self::assertStringContainsString('declare (strict_types=1);', $code);
        self::assertSame(1, substr_count($code, 'use DateTimeImmutable;'));
        self::assertStringContainsString('final class First', $code);
        self::assertStringContainsString('final class Second', $code);
    }

    /**
     * Ensures declare statements are kept only once when several virtual files contain them.
     */
    public function testItKeepsDeclareStatementOnlyOnce(): void
    {
        $nodes = new UserLandParser()->simpleParseCode(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App;

            final class First
            {
            }

            final class Second
            {
            }
            PHP, '/project/src/DeclareExample.php');

        $virtualFiles = new VirtualPhpSourceFileProducer()->produceVirtualPhpSourceFiles('/project/src/DeclareExample.php', $nodes);
        $code = new Standard()->prettyPrintFile(new VirtualPhpSourceFileAssembler()->assemble($virtualFiles));

        self::assertSame(1, substr_count($code, 'declare (strict_types=1);'));
    }

    /**
     * Ensures use statement variants are deduplicated by type, name, and alias.
     */
    public function testItDeduplicatesUseVariantsWithoutMergingDifferentImports(): void
    {
        $nodes = new UserLandParser()->simpleParseCode(<<<'PHP'
            <?php

            namespace App;

            use DateTimeImmutable;
            use DateTimeImmutable as Clock;
            use function count;
            use const PHP_VERSION;

            final class First
            {
            }

            final class Second
            {
            }
            PHP, '/project/src/UseVariants.php');

        $virtualFiles = new VirtualPhpSourceFileProducer()->produceVirtualPhpSourceFiles('/project/src/UseVariants.php', $nodes);
        $code = new Standard()->prettyPrintFile(new VirtualPhpSourceFileAssembler()->assemble($virtualFiles));

        self::assertSame(1, substr_count($code, 'use DateTimeImmutable;'));
        self::assertSame(1, substr_count($code, 'use DateTimeImmutable as Clock;'));
        self::assertSame(1, substr_count($code, 'use function count;'));
        self::assertSame(1, substr_count($code, 'use const PHP_VERSION;'));
    }

    /**
     * Ensures original-node mode ignores in-memory virtual file updates.
     */
    public function testItCanAssembleFromOriginalNodes(): void
    {
        $nodes = new UserLandParser()->simpleParseCode(<<<'PHP'
            <?php

            namespace App;

            final class OriginalName
            {
            }
            PHP, '/project/src/OriginalMode.php');

        $virtualFiles = new VirtualPhpSourceFileProducer()->produceVirtualPhpSourceFiles('/project/src/OriginalMode.php', $nodes);
        $virtualFile = $virtualFiles->get(0);
        self::assertNotNull($virtualFile);

        $updatedNodes = $virtualFile->getAst();
        $this->renameFirstClass($updatedNodes, 'UpdatedName');
        $virtualFile->update($updatedNodes);

        $assembledOriginalCode = new Standard()->prettyPrintFile(new VirtualPhpSourceFileAssembler()->assemble(
            virtualFiles: $virtualFiles,
            useOriginalNodes: true,
        ));
        $assembledUpdatedCode = new Standard()->prettyPrintFile(new VirtualPhpSourceFileAssembler()->assemble($virtualFiles));

        self::assertStringContainsString('final class OriginalName', $assembledOriginalCode);
        self::assertStringNotContainsString('final class UpdatedName', $assembledOriginalCode);
        self::assertStringContainsString('final class UpdatedName', $assembledUpdatedCode);
    }

    /**
     * Ensures global-scope virtual files can be reassembled with imports and declarations.
     */
    public function testItAssemblesGlobalScopeVirtualFiles(): void
    {
        $nodes = new UserLandParser()->simpleParseCode(<<<'PHP'
            <?php

            use DateTimeImmutable;

            final class First
            {
            }

            final class Second
            {
            }
            PHP, '/project/src/GlobalScope.php');

        $virtualFiles = new VirtualPhpSourceFileProducer()->produceVirtualPhpSourceFiles('/project/src/GlobalScope.php', $nodes);
        $code = new Standard()->prettyPrintFile(new VirtualPhpSourceFileAssembler()->assemble($virtualFiles));

        self::assertSame(1, substr_count($code, 'use DateTimeImmutable;'));
        self::assertStringContainsString('final class First', $code);
        self::assertStringContainsString('final class Second', $code);
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
