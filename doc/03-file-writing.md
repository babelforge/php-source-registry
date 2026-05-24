# File Writing

Navigation: [Documentation](README.md) | [Previous: Public Usage](02-public-usage.md) | [Next: Testing And Maintenance](04-testing-and-maintenance.md)

`PhpSourceRegistry` provides a concrete local filesystem writer through `BabelForge\PhpSource\Writer\NativeFileWriter`.

## NativeFileWriter

`NativeFileWriter` implements `FileWriterInterface`:

```php
use BabelForge\PhpSource\Writer\NativeFileWriter;

$writer = new NativeFileWriter();
$writer->writeContent('/project/src/Generated.php', '<?php echo "ok";');
```

The writer can also render PHPParser AST nodes:

```php
use BabelForge\PhpSource\Writer\NativeFileWriter;

$writer = new NativeFileWriter();
$writer->writeAst($ast, '/project/src/Generated.php');
```

`writeAst()` uses the same default registry-oriented printer family through `NopPrinter`.

## Atomic-ish Writes

`writeContent()` writes to a temporary file in the target directory, then renames that temporary file over the target path.

The parent directory is created recursively when missing.

This keeps writes simple and local while avoiding partially written target files in normal failure cases.

## Failure Behavior

The writer throws `RuntimeException` when:

- a parent path exists but is not a directory;
- a directory cannot be created;
- a temporary file cannot be created;
- temporary content cannot be written;
- the temporary file cannot be moved over the target file;
- `checkDirExists()` receives a missing or non-directory path.

## Integration With php-rename

`php-rename` can mutate virtual files in memory, then let this package write the physical files:

```php
use BabelForge\PhpSource\PhpSourceRegistryInstance;

$registry = new PhpSourceRegistryInstance();

// php-rename mutates virtual file AST nodes here.

$registry->save();
```

Rename, transaction, and graph cache policies are handled outside this package.

When `php-rename` wants to write one already-loaded physical file, it can call:

```php
$registry->saveSourceFile('/project/src/App/Mailer.php');
```

This uses the same configured writer and reassembly path as `save()`, but only for the requested source file.

Navigation: [Documentation](README.md) | [Previous: Public Usage](02-public-usage.md) | [Next: Testing And Maintenance](04-testing-and-maintenance.md)
