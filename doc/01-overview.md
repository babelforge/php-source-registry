# Overview

Navigation: [Documentation](README.md) | [Previous: Documentation](README.md) | [Next: Public Usage](02-public-usage.md)

`PhpSourceRegistry` is a small source/AST registry for PHP code.

It loads physical PHP files, parses them with PHPParser, splits them into virtual PHP source files, lets consumers mutate AST nodes, and reassembles updated virtual files back into their original physical file.

## Package Boundary

This package owns:

- physical PHP file loading;
- virtual PHP source file production;
- AST storage and update flags;
- physical-file AST reassembly;
- parser and printer support required by the registry;
- low-level local filesystem writing.

This package does not own:

- rename policy;
- semantic dependency analysis;
- transaction orchestration;
- graph cache refresh;
- physical path moves;
- namespace-wide refactor orchestration.

Those concerns belong to packages built above this source registry.

## Main Runtime Flow

The normal runtime flow is:

1. Load a physical PHP file through `PhpSourceRegistryInstance::getVirtualFiles()`.
2. Mutate one or more returned `VirtualPhpSourceFile` AST node lists.
3. Mark mutations through `updateVirtualFileAst()`.
4. Call `save()`.
5. The registry reassembles only updated physical files and writes them through its configured writer.
6. Written virtual files are rebooted so their update flags are cleared and their AST state is reparsed from the written code.

## Preferred API

Prefer `PhpSourceRegistryInstance` when source state must be shared explicitly between services.

Use `PhpSourceRegistry` only for simple scripts, tests, or compatibility paths that benefit from a static facade.

Navigation: [Documentation](README.md) | [Previous: Documentation](README.md) | [Next: Public Usage](02-public-usage.md)
