<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Tests;

use PhpNoobs\PhpSource\Parser\UserLandParser;
use PhpNoobs\PhpSource\VirtualPhpSourceFileProducer;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
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
}
