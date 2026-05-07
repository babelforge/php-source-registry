<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Tests;

use PhpNoobs\PhpSource\Parser\UserLandParser;
use PhpNoobs\PhpSource\VirtualPhpSourceFileAssembler;
use PhpNoobs\PhpSource\VirtualPhpSourceFileProducer;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\TestCase;

/**
 * Covers physical-file AST reassembly from virtual source files.
 */
final class VirtualPhpSourceFileAssemblerTest extends TestCase
{
    /**
     * Ensures split virtual files can be assembled back into one file without duplicate imports.
     *
     * @return void
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
}
