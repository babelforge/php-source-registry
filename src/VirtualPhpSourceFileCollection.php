<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Class VirtualPhpSourceFileCollection
 *
 * @implements IteratorAggregate<VirtualPhpSourceFile>
 */
final class VirtualPhpSourceFileCollection implements IteratorAggregate, Countable
{
    /**
     * @var VirtualPhpSourceFile[]
     */
    private array $files = [];

    public function add(VirtualPhpSourceFile $file): self
    {
        $this->files[] = $file;

        return $this;
    }

    public function get(int $index): ?VirtualPhpSourceFile
    {
        return $this->files[$index] ?? null;
    }

    public function has(string $virtualFilePath): bool
    {
        return array_any($this->files, fn ($file) => $file->virtualFilePath === $virtualFilePath);
    }

    public function getByPath(string $virtualFilePath): ?VirtualPhpSourceFile
    {
        return array_find($this->files, fn ($file) => $file->virtualFilePath === $virtualFilePath);
    }

    public function merge(self $files): self
    {
        foreach ($files as $file) {
            $this->add($file);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        yield from $this->files;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->files);
    }
}
