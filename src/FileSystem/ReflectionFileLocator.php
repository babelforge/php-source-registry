<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\FileSystem;

/**
 * Resolves a class/trait/interface FQCN to its source file using PHP Reflection.
 *
 * This locator is intended only for userland code.
 */
final class ReflectionFileLocator
{
    /**
     * @var array<class-string, ?string>
     */
    private static array $classCache = [];
    /**
     * @var array<class-string, bool>
     */
    private static array $externalFunctionsCache = [];

    /**
     * Locates the source file for a given FQCN.
     *
     * @param class-string $fqcn fully-qualified class/trait/interface name
     *
     * @return string|null absolute file path, or null if not resolvable
     */
    public function locate(string $fqcn): ?string
    {
        if (isset(self::$classCache[$fqcn])) {
            return self::$classCache[$fqcn];
        }

        try {
            $ref = new \ReflectionClass($fqcn);
            $file = $ref->getFileName();

            if (false === $file) {
                $file = null;
            }
        } catch (\ReflectionException) {
            $file = null;
        }

        self::$classCache[$fqcn] = $file;

        return self::$classCache[$fqcn];
    }

    /**
     * Locate a function by its FQCN.
     *
     * @param class-string $fqcn fully-qualified function name
     */
    public function isExternalFunction(string $fqcn): bool
    {
        if (isset(self::$externalFunctionsCache[$fqcn])) {
            return self::$externalFunctionsCache[$fqcn];
        }

        try {
            $ref = new \ReflectionFunction($fqcn);
            self::$externalFunctionsCache[$fqcn] = !$ref->isInternal();
        } catch (\ReflectionException) {
            self::$externalFunctionsCache[$fqcn] = true;
        }

        return self::$externalFunctionsCache[$fqcn];
    }

    /**
     * Retrieves the source code content of a file.
     *
     * @param string $filePath absolute path to the file
     *
     * @return string|false file content as string, or false on failure
     */
    public function getContent(string $filePath): string|false
    {
        return file_get_contents($filePath);
    }
}
