<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource;

use PhpNoobs\PhpSource\Contracts\FileWriterInterface;
use PhpNoobs\PhpSource\Contracts\ParserInterface;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;

/**
 * Class PhpSourceRegistry
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
        self::$instance ??= new PhpSourceRegistryInstance(self::$fileWriter);

        return self::$instance;
    }

    public static function setFileWriter(FileWriterInterface $fileWriter): void
    {
        self::$fileWriter = $fileWriter;
    }

    /**
     * @param string $filePath
     * @param Node[] $nodes
     * @return void
     */
    public static function updateVirtualFileAst(string $filePath, array $nodes): void
    {
        self::new()->updateVirtualFileAst($filePath, $nodes);
    }

    /**
     * @param string $virtualFilePath
     * @return Node[]
     */
    public static function getVirtualFileAst(string $virtualFilePath): array
    {
        return self::new()->getVirtualFileAst($virtualFilePath);
    }

    /**
     * @param string $filePath
     * @return VirtualPhpSourceFileCollection
     */
    public static function getVirtualFiles(string $filePath): VirtualPhpSourceFileCollection
    {
        return self::new()->getVirtualFiles($filePath);
    }

    /**
     * @return VirtualPhpSourceFileCollection
     */
    public static function getAllVirtualFiles(): VirtualPhpSourceFileCollection
    {
        return self::new()->getAllVirtualFiles();
    }

    public static function save(): void
    {
        self::new()->save();
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
     * @param ParserInterface $parser
     * @return void
     * @api
     */
    public static function setParser(ParserInterface $parser): void
    {
        self::new()->setParser($parser);
    }

    /**
     * @param Standard $printer
     * @return void
     * @api
     */
    public static function setPrinter(Standard $printer): void
    {
        self::new()->setPrinter($printer);
    }

    /**
     * @param Node[] $nodes
     * @return string
     */
    public static function print(array $nodes): string
    {
        return self::new()->print($nodes);
    }

    /**
     * @param Node[] $nodes
     * @return string
     */
    public static function standardPrint(array $nodes): string
    {
        return self::new()->standardPrint($nodes);
    }
}
