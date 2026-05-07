<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Parser\Collectors\Visitors;

use PhpNoobs\PhpSource\Parser\AbstractNodeVisitor;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;

/**
 * Collects "use" imports and stores them as a map (alias => FQCN) in node attributes.
 *
 * Strategy:
 * - When entering a namespace, start with an empty map.
 * - For each Use_ / GroupUse statement, add entries to the map.
 * - Attach the current map to:
 *   - Namespace_ node (authoritative for the namespace scope)
 *   - Class_ nodes encountered (so consumers can read directly from the class node)
 *
 * Limitations:
 * - This collector targets class/type imports only; it ignores function and constant imports.
 */
final class CollectUseImportsVisitor extends AbstractNodeVisitor
{
    public const string IMPORTS_ATTRIBUTE_KEY = 'retype.imports.map';

    /**
     * @var array<string, class-string>
     */
    private array $imports = [];

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof Namespace_) {
            $this->imports = [];

            // Attach empty imports map early for consumers.
            $node->setAttribute(self::IMPORTS_ATTRIBUTE_KEY, $this->imports);

            return null;
        }

        if ($node instanceof Use_) {
            if (Use_::TYPE_NORMAL !== $node->type) {
                return null;
            }

            $this->collectUseUses($node->uses, prefix: null);

            return null;
        }

        if ($node instanceof GroupUse) {
            if (Use_::TYPE_NORMAL !== $node->type) {
                return null;
            }

            $prefix = $node->prefix->toString();
            $this->collectUseUses($node->uses, prefix: $prefix);

            return null;
        }

        if ($node instanceof Class_) {
            // Attach current imports map to the class node so consumers don't need to walk parents.
            $node->setAttribute(self::IMPORTS_ATTRIBUTE_KEY, $this->imports);

            return null;
        }

        return null;
    }

    /**
     * Collects imports from a list of UseUse entries.
     *
     * @param list<UseItem> $uses The use entries.
     * @param non-empty-string|null $prefix Optional group use prefix.
     */
    private function collectUseUses(array $uses, ?string $prefix): void
    {
        foreach ($uses as $useUse) {
            $this->collectUseUse($useUse, $prefix);
        }
    }

    /**
     * Collects a single import statement.
     *
     * Example:
     * - use Foo\Bar;            => alias Bar => Foo\Bar
     * - use Foo\Bar as Baz;     => alias Baz => Foo\Bar
     * - use Foo\{Bar, Baz as Q} => alias Bar => Foo\Bar, alias Q => Foo\Baz
     *
     * @param UseItem $useUse The use entry.
     * @param non-empty-string|null $prefix Optional group use prefix.
     */
    private function collectUseUse(UseItem $useUse, ?string $prefix): void
    {
        $name = $useUse->name->toString();

        $fqcn = null !== $prefix && '' !== $prefix
            ? $prefix . '\\' . $name
            : $name;

        $alias = $this->inferAlias($useUse->alias, $fqcn);

        // Defensive: alias can theoretically be empty, but we skip in that case.
        if ('' === $alias) {
            return;
        }

        /** @var class-string $fqcn */
        $this->imports[$alias] = ltrim($fqcn, '\\');
    }

    /**
     * Infers the import alias.
     *
     * @param Identifier|null $explicitAlias Explicit alias node (if provided).
     * @param non-empty-string $fqcn The fully-qualified class name.
     *
     * @return non-empty-string The alias.
     */
    private function inferAlias(?Identifier $explicitAlias, string $fqcn): string
    {
        if (null !== $explicitAlias) {
            $alias = $explicitAlias->toString();

            return '' !== $alias ? $alias : $this->lastSegment($fqcn);
        }

        return $this->lastSegment($fqcn);
    }

    /**
     * Returns the last namespace segment for a FQCN.
     *
     * @param non-empty-string $fqcn The fully-qualified class name.
     *
     * @return non-empty-string The last segment.
     */
    private function lastSegment(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        if (false === $pos) {
            return $fqcn;
        }

        return substr($fqcn, $pos + 1);
    }
}
