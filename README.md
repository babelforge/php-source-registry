# PHP Source Registry

Local source/AST registry for PHP code.

The package loads physical PHP files, splits them into virtual source files, exposes PHPParser AST nodes for inspection or transformation, and can reassemble updated virtual files back into their original physical file.

## Status

This package is currently extracted locally from the main project.
It is not treated as a published package yet, but it already uses its target namespace:

```php
namespace PhpNoobs\PhpSource;
```

## Main Concepts

### `PhpSourceRegistryInstance`

Stateful registry instance.

Use it when several services must share the same loaded source state.
This is the preferred API for application code and higher-level packages.

### `PhpSourceRegistry`

Static facade around a current registry instance.

It remains useful for simple scripts, tests, and compatibility paths, but package-level code should prefer receiving a `PhpSourceRegistryInstance` explicitly.

### `PhpSourceFile`

Represents one physical PHP file.

It owns the collection of virtual source files produced from that physical file and can reassemble them when saving.

### `VirtualPhpSourceFile`

Represents one source unit extracted from a physical PHP file.

Typical units are class-like declarations or namespace/global chunks. Each virtual source file keeps:

- the physical file path;
- the virtual file path;
- the parsed AST nodes;
- the original non-transformed AST nodes;
- an updated flag.

### `VirtualPhpSourceFileCollection`

Collection of virtual source files.

It implements `Countable` and `IteratorAggregate`.

## Basic Usage

Additional package documentation starts in [doc/README.md](doc/README.md).

```php
use PhpNoobs\PhpSource\PhpSourceRegistryInstance;

$registry = new PhpSourceRegistryInstance();

$virtualFiles = $registry->getVirtualFiles('/project/src/UserService.php');

foreach ($virtualFiles as $virtualFile) {
    $nodes = $virtualFile->getAst();

    // Inspect or transform PHPParser nodes here.
}
```

## Updating a Virtual File

```php
use PhpNoobs\PhpSource\PhpSourceRegistryInstance;

$registry = new PhpSourceRegistryInstance();
$virtualFiles = $registry->getVirtualFiles('/project/src/UserService.php');
$virtualFile = $virtualFiles->get(0);

if (null !== $virtualFile) {
    $nodes = $virtualFile->getAst();

    // Transform $nodes here.

    $registry->updateVirtualFileAst($virtualFile->virtualFilePath, $nodes);
    $registry->save();
}
```

`save()` reassembles only physical files that contain at least one updated virtual file.
By default, it writes through `Writer\NativeFileWriter` and reboots updated virtual files after writing.

## File Writing

`PhpSourceRegistryInstance` uses `PhpNoobs\PhpSource\Writer\NativeFileWriter` by default:

```php
use PhpNoobs\PhpSource\PhpSourceRegistryInstance;

$registry = new PhpSourceRegistryInstance();

// Mutate virtual files, then write updated physical files.
$registry->save();
```

You may still inject a custom writer, for example in tests:

```php
use PhpNoobs\PhpSource\Contracts\FileWriterInterface;
use PhpNoobs\PhpSource\PhpSourceRegistryInstance;

/** @var FileWriterInterface $writer */
$registry = new PhpSourceRegistryInstance($writer);
```

The static facade also uses `NativeFileWriter` by default. To override it:

```php
use PhpNoobs\PhpSource\PhpSourceRegistry;
use PhpNoobs\PhpSource\Writer\NativeFileWriter;

PhpSourceRegistry::setFileWriter(new NativeFileWriter());
```

`save()` only writes physical files that contain at least one updated virtual file.
After a physical file is written, its updated virtual files are rebooted so their update flags are cleared and their AST state is reparsed from the written code.

To write only one known physical source file:

```php
$registry->saveSourceFile('/project/src/UserService.php');
```

`saveSourceFile()` writes the file only when at least one of its virtual files is updated.
It normalizes existing source file paths before lookup, so equivalent paths pointing to the same physical file target the same registered source file.
It throws `RuntimeException` when the physical source file is not known by the current registry instance.

## Static Facade Usage

```php
use PhpNoobs\PhpSource\PhpSourceRegistry;

PhpSourceRegistry::clear();

$virtualFiles = PhpSourceRegistry::getVirtualFiles('/project/src/UserService.php');
```

Prefer the instance API when source state must be shared explicitly between packages.

## Advanced Services

### `VirtualPhpSourceFileProducer`

Splits a PHPParser AST into `VirtualPhpSourceFile` instances.

Use it only when the caller already owns parsed nodes and wants to bypass the registry loading flow.

### `VirtualPhpSourceFileAssembler`

Reassembles a `VirtualPhpSourceFileCollection` into one physical-file AST.

Use it only when the caller needs explicit control over the reassembly step.

## Included Infrastructure

The package also contains the small parser/printer infrastructure required by the registry:

- `Contracts\FileWriterInterface`;
- `Contracts\ParserInterface`;
- `Parser\UserLandParser`;
- parser traversers and visitors;
- `Printer\NopPrinter`;
- node and locator helpers.

These classes support the registry behavior and may be promoted to a stricter public API later if repeated external usage justifies it.

## Boundaries

This package owns source files, virtual files, AST storage, parsing, printing, updates, and physical-file reassembly.

It does not own higher-level analysis concepts such as dependency graphs, semantic indexes, impact queries, topology, or rebuild strategies.
Those concerns should live in packages built above this source registry.
