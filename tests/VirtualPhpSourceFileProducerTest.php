<?php

declare(strict_types=1);

namespace BabelForge\PhpSource\Tests;

use BabelForge\PhpSource\Parser\UserLandParser;
use BabelForge\PhpSource\VirtualPhpSourceFile;
use BabelForge\PhpSource\VirtualPhpSourceFileCollection;
use BabelForge\PhpSource\VirtualPhpSourceFileProducer;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Trait_;
use PHPUnit\Framework\TestCase;

/**
 * Covers virtual source file production from PHPParser nodes.
 */
final class VirtualPhpSourceFileProducerTest extends TestCase
{
    /**
     * Ensures namespaced class-like declarations are split into separate virtual files.
     */
    public function testItSplitsNamespacedClassLikeDeclarations(): void
    {
        $nodes = new UserLandParser()->simpleParseCode(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App;

            use DateTimeImmutable;

            function helper(): void
            {
            }

            final class First
            {
            }

            interface Second
            {
            }
            PHP, '/project/src/Example.php');

        $virtualFiles = new VirtualPhpSourceFileProducer()->produceVirtualPhpSourceFiles('/project/src/Example.php', $nodes);
        $firstVirtualFile = $this->getVirtualFile($virtualFiles, 0);
        $secondVirtualFile = $this->getVirtualFile($virtualFiles, 1);

        self::assertCount(2, $virtualFiles);
        self::assertSame('/project/src/Example.php.virtual.0', $firstVirtualFile->virtualFilePath);
        self::assertSame('/project/src/Example.php.virtual.1', $secondVirtualFile->virtualFilePath);
        self::assertTrue($this->containsStatement($firstVirtualFile->nodes, Function_::class));
        self::assertTrue($this->containsStatement($firstVirtualFile->nodes, Class_::class));
        self::assertFalse($this->containsStatement($firstVirtualFile->nodes, Interface_::class));
        self::assertTrue($this->containsStatement($secondVirtualFile->nodes, Interface_::class));
    }

    /**
     * Ensures a namespace without class-like declarations stays as one virtual file.
     */
    public function testItKeepsFunctionOnlyNamespaceAsOneVirtualFile(): void
    {
        $nodes = new UserLandParser()->simpleParseCode(<<<'PHP'
            <?php

            namespace App\Support;

            function helper(): void
            {
            }
            PHP, '/project/src/functions.php');

        $virtualFiles = new VirtualPhpSourceFileProducer()->produceVirtualPhpSourceFiles('/project/src/functions.php', $nodes);
        $virtualFile = $this->getVirtualFile($virtualFiles, 0);

        self::assertCount(1, $virtualFiles);
        self::assertSame('/project/src/functions.php.virtual.0', $virtualFile->virtualFilePath);
        self::assertTrue($this->containsStatement($virtualFile->nodes, Function_::class));
    }

    /**
     * Ensures global-scope class-like declarations are split into separate virtual files.
     */
    public function testItSplitsGlobalScopeClassLikeDeclarations(): void
    {
        $nodes = new UserLandParser()->simpleParseCode(<<<'PHP'
            <?php

            function helper(): void
            {
            }

            final class First
            {
            }

            final class Second
            {
            }
            PHP, '/project/src/Global.php');

        $virtualFiles = new VirtualPhpSourceFileProducer()->produceVirtualPhpSourceFiles('/project/src/Global.php', $nodes);
        $firstVirtualFile = $this->getVirtualFile($virtualFiles, 0);
        $secondVirtualFile = $this->getVirtualFile($virtualFiles, 1);

        self::assertCount(2, $virtualFiles);
        self::assertSame('/project/src/Global.php.virtual.0', $firstVirtualFile->virtualFilePath);
        self::assertSame('/project/src/Global.php.virtual.1', $secondVirtualFile->virtualFilePath);
        self::assertTrue($this->containsStatement($firstVirtualFile->nodes, Function_::class));
        self::assertTrue($this->containsStatement($firstVirtualFile->nodes, Class_::class));
        self::assertTrue($this->containsClassNamed($firstVirtualFile->nodes, 'First'));
        self::assertTrue($this->containsClassNamed($secondVirtualFile->nodes, 'Second'));
    }

    /**
     * Ensures multiple namespaces keep stable virtual file indexes and isolated declarations.
     */
    public function testItSplitsMultipleNamespacesWithStableIndexes(): void
    {
        $nodes = new UserLandParser()->simpleParseCode(<<<'PHP'
            <?php

            namespace App\One;

            final class First
            {
            }

            namespace App\Two;

            final class Second
            {
            }
            PHP, '/project/src/MultiNamespace.php');

        $virtualFiles = new VirtualPhpSourceFileProducer()->produceVirtualPhpSourceFiles('/project/src/MultiNamespace.php', $nodes);
        $firstVirtualFile = $this->getVirtualFile($virtualFiles, 0);
        $secondVirtualFile = $this->getVirtualFile($virtualFiles, 1);

        self::assertCount(2, $virtualFiles);
        self::assertSame('/project/src/MultiNamespace.php.virtual.0', $firstVirtualFile->virtualFilePath);
        self::assertSame('/project/src/MultiNamespace.php.virtual.1', $secondVirtualFile->virtualFilePath);
        self::assertTrue($this->containsNamespace($firstVirtualFile->nodes, 'App\\One'));
        self::assertTrue($this->containsNamespace($secondVirtualFile->nodes, 'App\\Two'));
        self::assertTrue($this->containsClassNamed($firstVirtualFile->nodes, 'First'));
        self::assertFalse($this->containsClassNamed($firstVirtualFile->nodes, 'Second'));
        self::assertTrue($this->containsClassNamed($secondVirtualFile->nodes, 'Second'));
    }

    /**
     * Ensures traits and enums are treated as top-level class-like declarations.
     */
    public function testItSplitsTraitsAndEnumsAsClassLikeDeclarations(): void
    {
        $nodes = new UserLandParser()->simpleParseCode(<<<'PHP'
            <?php

            namespace App;

            trait Timestamped
            {
            }

            enum Status
            {
                case Active;
            }
            PHP, '/project/src/ClassLikeKinds.php');

        $virtualFiles = new VirtualPhpSourceFileProducer()->produceVirtualPhpSourceFiles('/project/src/ClassLikeKinds.php', $nodes);

        self::assertCount(2, $virtualFiles);
        $traitVirtualFile = $this->getVirtualFile($virtualFiles, 0);
        $enumVirtualFile = $this->getVirtualFile($virtualFiles, 1);

        self::assertTrue($this->containsStatement($traitVirtualFile->nodes, Trait_::class));
        self::assertTrue($this->containsStatement($enumVirtualFile->nodes, Enum_::class));
    }

    /**
     * Returns a virtual file from a collection.
     *
     * @param VirtualPhpSourceFileCollection $virtualFiles the virtual file collection
     * @param int                            $index        the expected file index
     */
    private function getVirtualFile(VirtualPhpSourceFileCollection $virtualFiles, int $index): VirtualPhpSourceFile
    {
        $virtualFile = $virtualFiles->get($index);
        self::assertNotNull($virtualFile);

        return $virtualFile;
    }

    /**
     * Checks whether a virtual file node list contains a statement type.
     *
     * @param array<object> $nodes          the node list
     * @param class-string  $statementClass the statement class to find
     */
    private function containsStatement(array $nodes, string $statementClass): bool
    {
        foreach ($nodes as $node) {
            if ($node instanceof $statementClass) {
                return true;
            }

            if ($node instanceof Namespace_) {
                foreach ($node->stmts as $statement) {
                    if ($statement instanceof $statementClass) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Checks whether a virtual file node list contains a namespace.
     *
     * @param array<object> $nodes         the node list
     * @param string        $namespaceName the namespace name to find
     */
    private function containsNamespace(array $nodes, string $namespaceName): bool
    {
        foreach ($nodes as $node) {
            if ($node instanceof Namespace_ && $node->name?->toString() === $namespaceName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether a virtual file node list contains a class by name.
     *
     * @param array<object> $nodes     the node list
     * @param string        $className the class name to find
     */
    private function containsClassNamed(array $nodes, string $className): bool
    {
        foreach ($nodes as $node) {
            if ($node instanceof Class_ && $node->name?->toString() === $className) {
                return true;
            }

            if ($node instanceof Namespace_) {
                foreach ($node->stmts as $statement) {
                    if ($statement instanceof Class_ && $statement->name?->toString() === $className) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
