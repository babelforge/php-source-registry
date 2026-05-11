<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;

/**
 * Class VirtualPhpSourceFileAssembler.
 *
 * Rebuilds a single AST file from a collection of VirtualPhpSourceFile.
 */
final class VirtualPhpSourceFileAssembler
{
    /**
     * Rebuilds a single AST file from virtual registry files.
     *
     * @param VirtualPhpSourceFileCollection $virtualFiles     the virtual files to assemble
     * @param bool                           $useOriginalNodes whether original non-transformed nodes should be used
     *
     * @return Node[] the rebuilt file AST
     */
    public function assemble(VirtualPhpSourceFileCollection $virtualFiles, bool $useOriginalNodes = false): array
    {
        $namespaces = [];
        /** @var Stmt[] $globalStatements */
        $globalStatements = [];
        $declareStatements = [];

        foreach ($virtualFiles as $virtualFile) {
            $processedNodes = $useOriginalNodes
                ? $virtualFile->originalNonTransformedNodes
                : $virtualFile->nodes;

            foreach ($processedNodes as $node) {
                if ($node instanceof Stmt\Declare_) {
                    $declareStatements[] = $node;

                    continue;
                }

                if ($node instanceof Namespace_) {
                    $namespaceName = $node->name?->toString() ?? '__global__';

                    if (!isset($namespaces[$namespaceName])) {
                        $namespaces[$namespaceName] = [
                            'uses' => [],
                            'stmts' => [],
                        ];
                    }

                    foreach ($node->stmts as $statement) {
                        if ($statement instanceof Use_) {
                            $namespaces[$namespaceName]['uses'][] = $statement;

                            continue;
                        }

                        $namespaces[$namespaceName]['stmts'][] = $statement;
                    }

                    continue;
                }

                if ($node instanceof Stmt) {
                    $globalStatements[] = $node;
                }
            }
        }

        $declareStatements = array_slice($declareStatements, 0, 1);

        $result = [];

        foreach ($declareStatements as $declareStatement) {
            $result[] = $declareStatement;
        }

        if ([] === $namespaces) {
            return array_merge(
                $result,
                $this->deduplicateUsesFromStatements($globalStatements),
                $this->removeUsesFromStatements($globalStatements),
            );
        }

        foreach ($namespaces as $namespaceName => $data) {
            $uses = $this->deduplicateUses($data['uses']);
            $statements = $data['stmts'];

            if ('__global__' === $namespaceName) {
                $result = array_merge($result, $uses, $statements);

                continue;
            }

            $result[] = new Namespace_(
                new Name($namespaceName),
                array_merge($uses, $statements),
            );
        }

        return $result;
    }

    /**
     * Deduplicates use statements extracted from a raw statement list.
     *
     * @param Stmt[] $statements the statements to inspect
     *
     * @return Use_[] the deduplicated use statements
     */
    private function deduplicateUsesFromStatements(array $statements): array
    {
        $uses = [];

        foreach ($statements as $statement) {
            if ($statement instanceof Use_) {
                $uses[] = $statement;
            }
        }

        return $this->deduplicateUses($uses);
    }

    /**
     * Removes use statements from a raw statement list.
     *
     * @param Stmt[] $statements the statements to filter
     *
     * @return Stmt[] the statements without use declarations
     */
    private function removeUsesFromStatements(array $statements): array
    {
        $result = [];

        foreach ($statements as $statement) {
            if ($statement instanceof Use_) {
                continue;
            }

            $result[] = $statement;
        }

        return $result;
    }

    /**
     * Deduplicates imports while preserving their type and attributes.
     *
     * This method keeps imports that are semantically part of the original file,
     * even if they target symbols declared elsewhere in the reassembled result.
     * This is required because all virtual files originate from the same split source file.
     *
     * @param Use_[] $uses the imports to deduplicate
     *
     * @return Use_[] the deduplicated imports
     */
    private function deduplicateUses(array $uses): array
    {
        $seen = [];
        $result = [];

        foreach ($uses as $use) {
            $keptUseItems = [];

            foreach ($use->uses as $useItem) {
                $key = $this->buildUseDeduplicationKey($use, $useItem);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $keptUseItems[] = $useItem;
            }

            if ([] !== $keptUseItems) {
                $result[] = new Use_($keptUseItems, $use->type, $use->getAttributes());
            }
        }

        return $result;
    }

    /**
     * Builds a stable deduplication key for a use item.
     *
     * The key includes:
     * - the use type (`use`, `use function`, `use const`)
     * - the imported fully-qualified name
     * - the explicit alias when present
     *
     * @param Use_         $use     the parent use statement
     * @param Node\UseItem $useItem the imported item
     *
     * @return string the deduplication key
     */
    private function buildUseDeduplicationKey(Use_ $use, Node\UseItem $useItem): string
    {
        return $use->type
            .'|'
            .$useItem->name->toString()
            .'|'
            .($useItem->alias?->toString() ?? '');
    }
}
