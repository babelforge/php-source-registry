<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Tests;

use PhpNoobs\PhpSource\VirtualPhpSourceFile;
use PhpNoobs\PhpSource\VirtualPhpSourceFileCollection;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PHPUnit\Framework\TestCase;

/**
 * Covers virtual source file collection behavior.
 */
final class VirtualPhpSourceFileCollectionTest extends TestCase
{
    /**
     * Ensures files can be added, counted, iterated, and fetched by virtual path.
     */
    public function testItStoresVirtualFilesByPath(): void
    {
        $firstFile = new VirtualPhpSourceFile('/project/src/Foo.php', '/project/src/Foo.php.virtual.0', []);
        $secondFile = new VirtualPhpSourceFile('/project/src/Foo.php', '/project/src/Foo.php.virtual.1', []);
        $collection = new VirtualPhpSourceFileCollection();

        $collection->add($firstFile)->add($secondFile);

        self::assertCount(2, $collection);
        self::assertSame($firstFile, $collection->get(0));
        self::assertSame($secondFile, $collection->getByPath('/project/src/Foo.php.virtual.1'));
        self::assertTrue($collection->has('/project/src/Foo.php.virtual.0'));
        self::assertSame([$firstFile, $secondFile], iterator_to_array($collection));
    }

    /**
     * Ensures collections can be merged in insertion order.
     */
    public function testItMergesCollections(): void
    {
        $firstFile = new VirtualPhpSourceFile('/project/src/Foo.php', '/project/src/Foo.php.virtual.0', []);
        $secondFile = new VirtualPhpSourceFile('/project/src/Bar.php', '/project/src/Bar.php.virtual.0', []);

        $collection = new VirtualPhpSourceFileCollection();
        $collection->add($firstFile);
        $collection->merge(new VirtualPhpSourceFileCollection()->add($secondFile));

        self::assertSame([$firstFile, $secondFile], iterator_to_array($collection));
    }

    /**
     * Ensures reboot clears the update flag and reparses the current nodes.
     */
    public function testVirtualFileRebootClearsUpdateFlagAndReparsesNodes(): void
    {
        $virtualFile = new VirtualPhpSourceFile(
            '/project/src/Foo.php',
            '/project/src/Foo.php.virtual.0',
            [
                new Class_('OriginalName'),
            ],
        );

        $virtualFile->update([
            new Class_('UpdatedName'),
        ]);

        self::assertTrue($virtualFile->isUpdated());

        $virtualFile->reboot();

        self::assertFalse($virtualFile->isUpdated());
        self::assertTrue($this->containsClassNamed($virtualFile->nodes, 'UpdatedName'));
        self::assertTrue($this->containsClassNamed($virtualFile->originalNonTransformedNodes, 'UpdatedName'));
    }

    /**
     * Checks whether a node list contains a class by name.
     *
     * @param array<object> $nodes     the node list
     * @param string        $className the class name
     */
    private function containsClassNamed(array $nodes, string $className): bool
    {
        foreach ($nodes as $node) {
            if ($node instanceof Class_ && $node->name instanceof Identifier && $node->name->toString() === $className) {
                return true;
            }
        }

        return false;
    }
}
