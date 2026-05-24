# PhpSourceRegistry Documentation

Navigation: [Next: Overview](01-overview.md)

This documentation describes `PhpSourceRegistry`, its source registry model, its virtual file lifecycle, and its local filesystem writing support.

`PhpSourceRegistry` owns physical PHP source loading, PHPParser AST storage, virtual PHP source files, physical-file reassembly, and low-level source writing.

It does not own rename policy, dependency analysis, transaction policy, semantic graph cache refresh, or physical path moves.

## Pages

1. [Overview](01-overview.md)
2. [Public Usage](02-public-usage.md)
3. [File Writing](03-file-writing.md)
4. [Testing And Maintenance](04-testing-and-maintenance.md)

## External Consumers

`babelforge/php-rename` can mutate `VirtualPhpSourceFile` AST nodes in memory and then rely on this package to write updated physical files.

`babelforge/member-graph` can consume virtual files and AST facts, but source writing remains in this package boundary.

## Current Layout

The general rule is:

- `PhpSourceRegistryInstance` owns stateful source registry operations.
- `PhpSourceRegistry` provides a static facade around a current instance.
- `VirtualPhpSourceFile*` classes model split source units and reassembly.
- `Parser/` and `Printer/` contain the PHPParser integration.
- `Writer/` contains local source-writing primitives.

Navigation: [Next: Overview](01-overview.md)
