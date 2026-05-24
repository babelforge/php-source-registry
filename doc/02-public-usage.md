# Public Usage

Navigation: [Documentation](README.md) | [Previous: Overview](01-overview.md) | [Next: File Writing](03-file-writing.md)

The public API loads virtual files, exposes AST nodes, registers updates, and saves updated physical files.

## Stateful Instance

Use `PhpSourceRegistryInstance` when application services need to share the same loaded source state:

```php
use BabelForge\PhpSource\PhpSourceRegistryInstance;

$registry = new PhpSourceRegistryInstance();
$virtualFiles = $registry->getVirtualFiles('/project/src/UserService.php');
```

The instance uses `NativeFileWriter` by default, so `save()` writes to disk unless a custom writer is injected.

## Custom Writer

Inject a custom writer when tests or integrations need to capture writes:

```php
use BabelForge\PhpSource\Contracts\FileWriterInterface;
use BabelForge\PhpSource\PhpSourceRegistryInstance;

/** @var FileWriterInterface $writer */
$registry = new PhpSourceRegistryInstance($writer);
```

## Static Facade

The static facade keeps a current registry instance:

```php
use BabelForge\PhpSource\PhpSourceRegistry;

PhpSourceRegistry::clear();
$virtualFiles = PhpSourceRegistry::getVirtualFiles('/project/src/UserService.php');
```

The facade also uses `NativeFileWriter` by default. Override it before loading files when needed:

```php
use BabelForge\PhpSource\PhpSourceRegistry;
use BabelForge\PhpSource\Writer\NativeFileWriter;

PhpSourceRegistry::clear();
PhpSourceRegistry::setFileWriter(new NativeFileWriter());
```

## Save Semantics

`save()` writes only physical files that contain at least one updated virtual file.

After a physical file is written, updated virtual files are rebooted. This clears their update flags and reparses AST state from the written code.

## Save One Source File

Use `saveSourceFile()` to save one known physical source file:

```php
$registry->saveSourceFile('/project/src/UserService.php');
```

The source file must already be loaded in the current registry instance.
Existing source file paths are normalized before lookup, so equivalent paths pointing to the same physical file resolve to the registered source file.

The method writes only when at least one virtual file from that physical source file is updated. After a successful write, the same reboot semantics as `save()` apply.

If the source file is not known by the registry, `saveSourceFile()` throws `RuntimeException`.

Navigation: [Documentation](README.md) | [Previous: Overview](01-overview.md) | [Next: File Writing](03-file-writing.md)
