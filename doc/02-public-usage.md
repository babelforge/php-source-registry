# Public Usage

Navigation: [Documentation](README.md) | [Previous: Overview](01-overview.md) | [Next: File Writing](03-file-writing.md)

The public API should stay small: load virtual files, mutate AST nodes, register updates, and save updated physical files.

## Stateful Instance

Use `PhpSourceRegistryInstance` when application services need to share the same loaded source state:

```php
use PhpNoobs\PhpSource\PhpSourceRegistryInstance;

$registry = new PhpSourceRegistryInstance();
$virtualFiles = $registry->getVirtualFiles('/project/src/UserService.php');
```

The instance uses `NativeFileWriter` by default, so `save()` writes to disk unless a custom writer is injected.

## Custom Writer

Inject a custom writer when tests or integrations need to capture writes:

```php
use PhpNoobs\PhpSource\Contracts\FileWriterInterface;
use PhpNoobs\PhpSource\PhpSourceRegistryInstance;

/** @var FileWriterInterface $writer */
$registry = new PhpSourceRegistryInstance($writer);
```

## Static Facade

The static facade keeps a current registry instance:

```php
use PhpNoobs\PhpSource\PhpSourceRegistry;

PhpSourceRegistry::clear();
$virtualFiles = PhpSourceRegistry::getVirtualFiles('/project/src/UserService.php');
```

The facade also uses `NativeFileWriter` by default. Override it before loading files when needed:

```php
use PhpNoobs\PhpSource\PhpSourceRegistry;
use PhpNoobs\PhpSource\Writer\NativeFileWriter;

PhpSourceRegistry::clear();
PhpSourceRegistry::setFileWriter(new NativeFileWriter());
```

## Save Semantics

`save()` writes only physical files that contain at least one updated virtual file.

After a physical file is written, updated virtual files are rebooted. This clears their update flags and reparses AST state from the written code.

Navigation: [Documentation](README.md) | [Previous: Overview](01-overview.md) | [Next: File Writing](03-file-writing.md)
