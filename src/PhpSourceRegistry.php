<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource;

use PhpNoobs\PhpSource\Contracts\FileWriterInterface;
use PhpNoobs\PhpSource\Contracts\ParserInterface;
use PhpNoobs\PhpSource\Writer\NativeFileWriter;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;

/**
 * Class PhpSourceRegistry.
 */
final class PhpSourceRegistry
{
    private static ?PhpSourceRegistryInstance $instance = null;

    private static ?FileWriterInterface $fileWriter = null;
    public static bool $log = false;

    public static function clear(): void
    {
        self::$instance = null;
        self::$fileWriter = null;
    }

    private static function new(): PhpSourceRegistryInstance
    {
        self::$instance ??= new PhpSourceRegistryInstance(self::fileWriter());

        return self::$instance;
    }

    private static function fileWriter(): FileWriterInterface
    {
        return self::$fileWriter ??= new NativeFileWriter();
    }

    public static function setFileWriter(FileWriterInterface $fileWriter): void
    {
        self::$fileWriter = $fileWriter;
    }

    /**
     * @param Node[] $nodes
     */
    public static function updateVirtualFileAst(string $filePath, array $nodes): void
    {
        self::new()->updateVirtualFileAst($filePath, $nodes);
    }

    /**
     * @return Node[]
     */
    public static function getVirtualFileAst(string $virtualFilePath): array
    {
        return self::new()->getVirtualFileAst($virtualFilePath);
    }

    public static function getVirtualFiles(string $filePath): VirtualPhpSourceFileCollection
    {
        return self::new()->getVirtualFiles($filePath);
    }

    public static function getAllVirtualFiles(): VirtualPhpSourceFileCollection
    {
        return self::new()->getAllVirtualFiles();
    }

    public static function save(): void
    {
        self::new()->save();
    }

    public static function saveSourceFile(string $filePath): void
    {
        self::new()->saveSourceFile($filePath);
    }

    public static function addWatchedFile(string $file): void
    {
        self::new()->addWatchedFile($file);
    }

    public static function isWatchedFile(string $testedFile): bool
    {
        return self::new()->isWatchedFile($testedFile);
    }

    public static function code(string $filePath): string
    {
        return self::new()->code($filePath);
    }

    /**
     * @api
     */
    public static function setParser(ParserInterface $parser): void
    {
        self::new()->setParser($parser);
    }

    /**
     * @api
     */
    public static function setPrinter(Standard $printer): void
    {
        self::new()->setPrinter($printer);
    }

    /**
     * @param Node[] $nodes
     */
    public static function print(array $nodes): string
    {
        return self::new()->print($nodes);
    }

    /**
     * @param Node[] $nodes
     */
    public static function standardPrint(array $nodes): string
    {
        return self::new()->standardPrint($nodes);
    }
}
