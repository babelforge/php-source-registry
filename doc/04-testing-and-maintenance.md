# Testing And Maintenance

Navigation: [Documentation](README.md) | [Previous: File Writing](03-file-writing.md)

`PhpSourceRegistry` should keep behavior covered at the source-registry boundary.

## Quality Commands

Run these commands before accepting changes:

```bash
composer cs
composer analyse
vendor/bin/phpunit
```

`composer analyse` runs PHPStan at max level.

`composer cs` runs PHP-CS-Fixer in dry-run mode.

## Writer Tests

Filesystem writer behavior is covered by `NativeFileWriterTest`.

Important cases:

- writing content to an existing file;
- writing content to a file in a missing nested directory;
- writing AST nodes to a file;
- failing clearly when a parent path is invalid;
- recursively creating missing directories;
- failing clearly when a directory check receives a missing path.

## Registry Save Tests

Registry save behavior should keep proving that:

- unchanged files are not written;
- updated virtual files are written;
- default `NativeFileWriter` writes physical files;
- updated virtual files are rebooted after writing.

## Boundary Discipline

Do not add rename policy, transaction policy, cache refresh policy, or dependency graph behavior here.

Source writing should remain a primitive used by higher-level packages.

Navigation: [Documentation](README.md) | [Previous: File Writing](03-file-writing.md)
