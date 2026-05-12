# PHP Source Registry

`php-noobs/php-source-registry` is a PHP 8.4+ source/AST registry built on top of `nikic/php-parser`.

It loads physical PHP files, splits them into virtual source files, exposes their PHPParser AST nodes, tracks in-memory updates, reassembles updated virtual files, and writes the resulting physical files through a local filesystem writer.

## Installation

```bash
composer require php-noobs/php-source-registry
```

The package requires PHP 8.4 or later.

## Basic Usage

Use `PhpSourceRegistryInstance` when application services share the same loaded source state:

```php
use PhpNoobs\PhpSource\PhpSourceRegistryInstance;

$registry = new PhpSourceRegistryInstance();

$virtualFiles = $registry->getVirtualFiles('/project/src/UserService.php');

foreach ($virtualFiles as $virtualFile) {
    $nodes = $virtualFile->getAst();

    // Inspect or transform PHPParser nodes here.
}
```

## Update And Save A File

`updateVirtualFileAst()` marks one virtual file as updated. `save()` writes every physical file that contains at least one updated virtual file.

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

After a physical file is written, its updated virtual files are rebooted: their update flags are cleared and their AST state is reparsed from the written code.

## Save One Source File

`saveSourceFile()` writes one known physical source file when at least one of its virtual files is updated:

```php
$registry->saveSourceFile('/project/src/UserService.php');
```

Existing source file paths are normalized before lookup, so equivalent paths pointing to the same physical file target the same registered source file.

## File Writing

`PhpSourceRegistryInstance` uses `PhpNoobs\PhpSource\Writer\NativeFileWriter` by default:

```php
use PhpNoobs\PhpSource\PhpSourceRegistryInstance;

$registry = new PhpSourceRegistryInstance();
$registry->save();
```

`NativeFileWriter` creates missing parent directories and writes through a temporary file in the target directory before renaming it over the destination.

Inject a custom writer when tests or integrations need to capture writes:

```php
use PhpNoobs\PhpSource\Contracts\FileWriterInterface;
use PhpNoobs\PhpSource\PhpSourceRegistryInstance;

/** @var FileWriterInterface $writer */
$registry = new PhpSourceRegistryInstance($writer);
```

## Static Facade

`PhpSourceRegistry` provides a static facade around a current registry instance:

```php
use PhpNoobs\PhpSource\PhpSourceRegistry;

PhpSourceRegistry::clear();

$virtualFiles = PhpSourceRegistry::getVirtualFiles('/project/src/UserService.php');
```

The facade uses `NativeFileWriter` by default. Override it before loading files when a custom writer is required:

```php
use PhpNoobs\PhpSource\PhpSourceRegistry;
use PhpNoobs\PhpSource\Writer\NativeFileWriter;

PhpSourceRegistry::clear();
PhpSourceRegistry::setFileWriter(new NativeFileWriter());
```

## Main Concepts

- `PhpSourceRegistryInstance`: stateful registry instance and preferred application API.
- `PhpSourceRegistry`: static facade for simple scripts, tests, and compatibility paths.
- `PhpSourceFile`: one physical PHP file with its virtual source files.
- `VirtualPhpSourceFile`: one source unit extracted from a physical PHP file.
- `VirtualPhpSourceFileCollection`: iterable collection of virtual source files.
- `VirtualPhpSourceFileProducer`: service that splits PHPParser AST nodes into virtual files.
- `VirtualPhpSourceFileAssembler`: service that reassembles virtual files into one physical-file AST.
- `Writer\NativeFileWriter`: concrete local filesystem writer.

## Documentation

The full package documentation starts in [doc/README.md](doc/README.md).

## Quality Commands

```bash
composer cs
composer analyse
vendor/bin/phpunit
```
