<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Tests;

use PhpNoobs\PhpSource\Parser\UserLandParser;
use PhpNoobs\PhpSource\VirtualPhpSourceFileProducer;
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
     *
     * @return void
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

        self::assertCount(2, $virtualFiles);
        self::assertSame('/project/src/Example.php.virtual.0', $virtualFiles->get(0)?->virtualFilePath);
        self::assertSame('/project/src/Example.php.virtual.1', $virtualFiles->get(1)?->virtualFilePath);
        self::assertTrue($this->containsStatement($virtualFiles->get(0)?->nodes ?? [], Function_::class));
        self::assertTrue($this->containsStatement($virtualFiles->get(0)?->nodes ?? [], Class_::class));
        self::assertFalse($this->containsStatement($virtualFiles->get(0)?->nodes ?? [], Interface_::class));
        self::assertTrue($this->containsStatement($virtualFiles->get(1)?->nodes ?? [], Interface_::class));
    }

    /**
     * Ensures a namespace without class-like declarations stays as one virtual file.
     *
     * @return void
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

        self::assertCount(1, $virtualFiles);
        self::assertSame('/project/src/functions.php.virtual.0', $virtualFiles->get(0)?->virtualFilePath);
        self::assertTrue($this->containsStatement($virtualFiles->get(0)?->nodes ?? [], Function_::class));
    }

    /**
     * Ensures global-scope class-like declarations are split into separate virtual files.
     *
     * @return void
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

        self::assertCount(2, $virtualFiles);
        self::assertSame('/project/src/Global.php.virtual.0', $virtualFiles->get(0)?->virtualFilePath);
        self::assertSame('/project/src/Global.php.virtual.1', $virtualFiles->get(1)?->virtualFilePath);
        self::assertTrue($this->containsStatement($virtualFiles->get(0)?->nodes ?? [], Function_::class));
        self::assertTrue($this->containsStatement($virtualFiles->get(0)?->nodes ?? [], Class_::class));
        self::assertTrue($this->containsClassNamed($virtualFiles->get(0)?->nodes ?? [], 'First'));
        self::assertTrue($this->containsClassNamed($virtualFiles->get(1)?->nodes ?? [], 'Second'));
    }

    /**
     * Ensures multiple namespaces keep stable virtual file indexes and isolated declarations.
     *
     * @return void
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

        self::assertCount(2, $virtualFiles);
        self::assertSame('/project/src/MultiNamespace.php.virtual.0', $virtualFiles->get(0)?->virtualFilePath);
        self::assertSame('/project/src/MultiNamespace.php.virtual.1', $virtualFiles->get(1)?->virtualFilePath);
        self::assertTrue($this->containsNamespace($virtualFiles->get(0)?->nodes ?? [], 'App\\One'));
        self::assertTrue($this->containsNamespace($virtualFiles->get(1)?->nodes ?? [], 'App\\Two'));
        self::assertTrue($this->containsClassNamed($virtualFiles->get(0)?->nodes ?? [], 'First'));
        self::assertFalse($this->containsClassNamed($virtualFiles->get(0)?->nodes ?? [], 'Second'));
        self::assertTrue($this->containsClassNamed($virtualFiles->get(1)?->nodes ?? [], 'Second'));
    }

    /**
     * Ensures traits and enums are treated as top-level class-like declarations.
     *
     * @return void
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
        self::assertTrue($this->containsStatement($virtualFiles->get(0)?->nodes ?? [], Trait_::class));
        self::assertTrue($this->containsStatement($virtualFiles->get(1)?->nodes ?? [], Enum_::class));
    }

    /**
     * Checks whether a virtual file node list contains a statement type.
     *
     * @param array<object> $nodes The node list.
     * @param class-string $statementClass The statement class to find.
     *
     * @return bool
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
     * @param array<object> $nodes The node list.
     * @param string $namespaceName The namespace name to find.
     *
     * @return bool
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
     * @param array<object> $nodes The node list.
     * @param string $className The class name to find.
     *
     * @return bool
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
