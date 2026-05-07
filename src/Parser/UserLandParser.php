<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Parser;

use PhpNoobs\PhpSource\Contracts\ParserInterface;
use PhpNoobs\PhpSource\FileSystem\ReflectionFileLocator;
use PhpNoobs\PhpSource\Parser\Collectors\UseImportsCollector;
use PhpNoobs\PhpSource\Parser\Traversers\NodeLocatorAttacher;
use PhpNoobs\PhpSource\Parser\Traversers\RemoveDoubleNopTraverser;
use PhpNoobs\PhpSource\Printer\NopPrinter;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;
use PhpParser\PrettyPrinter\Standard;
use RuntimeException;

/**
 * Provides functionality to parse files for userland classes, traits, or interfaces
 * using Reflection-based file locating and PHP AST parsing.
 */
final class UserLandParser implements ParserInterface
{
    private Parser $parser;

    /**
     * @param AbstractNodeTraverser $locatorAttacher
     * @param AbstractNodeTraverser $removeDoubleNopTraverser
     * @param ReflectionFileLocator $locator
     * @param NopPrinter $printer
     */
    public function __construct(
        private readonly AbstractNodeTraverser $locatorAttacher = new NodeLocatorAttacher(),
        private readonly AbstractNodeTraverser $removeDoubleNopTraverser = new RemoveDoubleNopTraverser(),
        private readonly ReflectionFileLocator $locator = new ReflectionFileLocator(),
        private readonly Standard              $printer = new NopPrinter()
    ) {
        $this->parser = ParserFactory::getParser();
    }

    /**
     * Parses a userland class/trait/interface file (Reflection-based).
     *
     * @inheritDoc
     */
    public function parseFqcn(string $fqcn): array
    {
        $file = $this->locateFilePath($fqcn);

        return $this->parseFile($file);
    }

    /**
     * @inheritDoc
     */
    public function parseFile(string $filePath): array
    {
        $ast = $this->simpleParseFile($filePath);

        return $this->processExtraVisitors($ast);
    }

    /**
     * @inheritDoc
     */
    public function simpleParseFile(string $filePath): array
    {
        $code = $this->locator->getContent($filePath);
        if (false === $code) {
            throw new RuntimeException(sprintf('Unable to read file "%s".', $filePath));
        }

        return $this->simpleParseCode($code, $filePath);
    }

    /**
     * @inheritDoc
     */
    public function parseCode(string $code, string $filePath = ''): array
    {
        //return $this->parseFileCode($code);
        $ast = $this->simpleParseCode($code);

        return $this->processExtraVisitors($ast);
    }

    /**
     * @inheritDoc
     */
    public function simpleParseCode(string $code, string $filePath = ''): array
    {
        try {
            $ast = $this->parser->parse($code);
        } catch (Error $e) {
            if (empty($filePath)) {
                throw new RuntimeException(sprintf('Parse error : %s', $e->getMessage()), 0, $e);
            }
            throw new RuntimeException(sprintf('Parse error for file "%s": %s', $filePath, $e->getMessage()), 0, $e);
        }

        return $ast;
    }

    /**
     * @param Node[] $ast
     * @return Node[]
     *
     * TODO : move this to a dedicated class (already exists in NopPrinter)
     */
    public function prettifyAndParse(array $ast): array
    {
        $code = $this->printer->prettyPrintFile($ast);

        return $this->parseCode($code);
    }

    /**
     * @param Node[] $ast
     * @return Node[]
     */
    public function processExtraVisitors(array $ast): array
    {
        $ast = $this->removeDoubleNopTraverser->traverse($ast);
        $ast = $this->locatorAttacher->traverse($ast);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new ParentConnectingVisitor());
        $traverser->addVisitor(new NameResolver());
        $ast = $traverser->traverse($ast);

        return new UseImportsCollector()->collect($ast);

    }

    /**
     * @param class-string $fqcn
     * @return string
     */
    private function locateFilePath(string $fqcn): string
    {
        $file = $this->locator->locate($fqcn);
        if (null === $file) {
            throw new RuntimeException(sprintf('Unable to locate source file for "%s".', $fqcn));
        }

        return $file;
    }
}
