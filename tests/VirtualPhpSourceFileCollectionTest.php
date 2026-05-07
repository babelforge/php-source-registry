<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Tests;

use PhpNoobs\PhpSource\VirtualPhpSourceFile;
use PhpNoobs\PhpSource\VirtualPhpSourceFileCollection;
use PHPUnit\Framework\TestCase;

/**
 * Covers virtual source file collection behavior.
 */
final class VirtualPhpSourceFileCollectionTest extends TestCase
{
    /**
     * Ensures files can be added, counted, iterated, and fetched by virtual path.
     *
     * @return void
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
     *
     * @return void
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
}
