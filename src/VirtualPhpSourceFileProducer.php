<?php

declare(strict_types=1);

namespace BabelForge\PhpSource;

use BabelForge\PhpSource\Contracts\ParserInterface;
use BabelForge\PhpSource\Parser\UserLandParser;
use BabelForge\PhpSource\Printer\NopPrinter;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\PrettyPrinter\Standard;

/**
 * Class VirtualPhpSourceFileProducer.
 *
 * Splits a PHP file AST into one or more virtual files.
 *
 * The splitting rules are the following:
 * - file-level statements located before the first namespace are copied into every produced file;
 * - each namespace is processed independently;
 * - each top-level class, interface, trait, or enum produces its own virtual file;
 * - statements located between two declarations are attached only to the next declaration;
 * - a namespace without any top-level class-like declaration still produces one virtual file containing its full content.
 */
final readonly class VirtualPhpSourceFileProducer
{
    public function __construct(
        private ParserInterface $parser = new UserLandParser(),
        private Standard $printer = new NopPrinter(),
        private Standard $standardPrinter = new Standard(),
    ) {
    }

    /**
     * Produce virtual files from a PHP AST.
     *
     * @param string $filePath the source file path
     * @param Node[] $nodes    the AST nodes of the source file
     */
    public function produceVirtualPhpSourceFiles(string $filePath, array $nodes): VirtualPhpSourceFileCollection
    {
        $globalPrelude = [];
        $virtualFiles = new VirtualPhpSourceFileCollection();
        $index = 0;
        $hasNamespaceStatements = false;

        foreach ($nodes as $node) {
            if ($node instanceof Namespace_) {
                $hasNamespaceStatements = true;
                $virtualFiles->merge(
                    $this->produceNamespaceVirtualFiles(
                        filePath: $filePath,
                        globalPrelude: $globalPrelude,
                        namespace: $node,
                        startIndex: $index,
                    ),
                );

                $index = $virtualFiles->count();

                continue;
            }

            if (!$hasNamespaceStatements) {
                $globalPrelude[] = $node;
            }
        }

        if ($hasNamespaceStatements) {
            return $virtualFiles;
        }

        return $this->produceGlobalScopeVirtualFiles(filePath: $filePath, nodes: $nodes);
    }

    /**
     * Produce virtual files for a namespace scope.
     *
     * @param string     $filePath      the source file path
     * @param Node[]     $globalPrelude the file-level statements to prepend to every produced file
     * @param Namespace_ $namespace     the namespace node to split
     * @param int        $startIndex    the starting virtual file index
     */
    private function produceNamespaceVirtualFiles(
        string $filePath,
        array $globalPrelude,
        Namespace_ $namespace,
        int $startIndex,
    ): VirtualPhpSourceFileCollection {
        $namespaceStatements = $namespace->stmts;
        $virtualFiles = new VirtualPhpSourceFileCollection();
        $pendingStatements = [];
        $localIndex = $startIndex;
        $classLikeFound = false;

        foreach ($namespaceStatements as $statement) {
            if ($this->isTopLevelClassLike($statement)) {
                $classLikeFound = true;

                $virtualFiles->add(
                    $this->createVirtualPhpSourceFile(
                        $filePath,
                        $this->buildVirtualFilePath($filePath, $localIndex),
                        [
                            ...$globalPrelude,
                            new Namespace_($namespace->name, [
                                ...$pendingStatements,
                                $statement,
                            ], $namespace->getAttributes()),
                        ]
                    )
                );

                ++$localIndex;
                $pendingStatements = [];

                continue;
            }

            $pendingStatements[] = $statement;
        }

        if ($classLikeFound) {
            return $virtualFiles;
        }

        return new VirtualPhpSourceFileCollection()->add(
            $this->createVirtualPhpSourceFile(
                $filePath,
                $this->buildVirtualFilePath($filePath, $startIndex),
                [
                    ...$globalPrelude,
                    new Namespace_($namespace->name, $namespaceStatements, $namespace->getAttributes()),
                ],
            ),
        );
    }

    /**
     * Produce virtual files for the global scope.
     *
     * @param string $filePath the source file path
     * @param Node[] $nodes    the file AST nodes
     */
    private function produceGlobalScopeVirtualFiles(string $filePath, array $nodes): VirtualPhpSourceFileCollection
    {
        $virtualFiles = new VirtualPhpSourceFileCollection();
        $pendingStatements = [];
        $index = 0;
        $classLikeFound = false;

        foreach ($nodes as $node) {
            if ($this->isTopLevelClassLike($node)) {
                $classLikeFound = true;

                $virtualFiles->add(
                    $this->createVirtualPhpSourceFile(
                        $filePath,
                        $this->buildVirtualFilePath($filePath, $index),
                        [
                            ...$pendingStatements,
                            $node,
                        ],
                    )
                );

                ++$index;
                $pendingStatements = [];

                continue;
            }

            $pendingStatements[] = $node;
        }

        if ($classLikeFound) {
            return $virtualFiles;
        }

        return new VirtualPhpSourceFileCollection()->add(
            $this->createVirtualPhpSourceFile(
                $filePath,
                $this->buildVirtualFilePath($filePath, 0),
                $nodes
            )
        );
    }

    /**
     * @param Node[] $nodes
     */
    private function createVirtualPhpSourceFile(string $filePath, string $virtualFilePath, array $nodes): VirtualPhpSourceFile
    {
        return new VirtualPhpSourceFile(
            $filePath,
            $virtualFilePath,
            $nodes,
            $this->parser,
            $this->printer,
            $this->standardPrinter,
        );
    }

    /**
     * Determine whether the given node is a top-level class-like declaration.
     *
     * Anonymous classes are ignored because they are expressions, not top-level statements.
     *
     * @param Node $node the node to inspect
     */
    private function isTopLevelClassLike(Node $node): bool
    {
        return $node instanceof ClassLike;
    }

    /**
     * Build the virtual file path.
     *
     * @param string $filePath the source file path
     * @param int    $index    the virtual file index
     */
    private function buildVirtualFilePath(string $filePath, int $index): string
    {
        return sprintf('%s.virtual.%d', $filePath, $index);
    }
}
