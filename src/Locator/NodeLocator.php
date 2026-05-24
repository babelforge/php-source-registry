<?php

declare(strict_types=1);

namespace BabelForge\PhpSource\Locator;

/**
 * Represents a stable, semantic locator for an AST node.
 *
 * The locator is designed to survive formatting changes (e.g. docblocks insertion)
 * as long as code is not reordered.
 */
final class NodeLocator
{
    public string $namespace;

    public function __construct(
        public bool $isNull = false,
        public bool $isFqcn = false,
        public bool $isClassLike = false,
        public bool $isFunctionLike = false,
        public bool $isMethodLike = false,
        public bool $isClosureLike = false,
        public bool $isArrowFunctionLike = false,
        public bool $isPropertyLike = false,
        public bool $isParamLike = false,
        public ?self $parent = null,
        ?string $namespace = null,
        public string $className = '',
        public string $functionName = '',
        public string $methodName = '',
        public string $propertyName = '',
        public string $parentScope = '',
        public int $index = 0,
        public int $newLine = 0,
    ) {
        $namespace ??= '';
        $this->namespace = $namespace;
    }

    public function setNewLine(int $newLine): void
    {
        $this->newLine = $newLine;
    }

    public function equals(self $other): bool
    {
        if ((null === $this->parent && null !== $other->parent) || (null === $other->parent && null !== $this->parent)) {
            return false;
        }

        if (null !== $this->parent && null !== $other->parent && !$this->parent->equals($other->parent)) {
            return false;
        }

        $found = array_all(self::checkedProps(), function ($prop) use ($other) {
            $a = $this->$prop;
            $b = $other->$prop;

            return $a === $b;
        });

        if ($found) {
            return true;
        }

        return false;
    }

    /**
     * @return string[]
     */
    private static function checkedProps(): array
    {
        return ['isNull', 'isFqcn', 'isClassLike', 'isFunctionLike', 'isMethodLike', 'isClosureLike', 'isArrowFunctionLike',
            'isPropertyLike', 'isParamLike', 'namespace', 'className', 'functionName', 'methodName', 'propertyName',
            'parentScope', 'index'];
    }

    public function toString(): string
    {
        try {
            return json_encode($this, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return '{}';
        }
    }

    public static function fromString(string $locator): self
    {
        try {
            $obj = json_decode($locator, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($obj)) {
                return self::null();
            }

            if (isset($obj['parent']) && is_array($obj['parent'])) {
                $obj['parent'] = self::fromArray($obj['parent']);
            }

            return self::fromArray($obj);
        } catch (\JsonException) {
            return self::null();
        }
    }

    /**
     * Build a locator from decoded JSON data.
     *
     * @param array<mixed> $data the decoded locator data
     */
    private static function fromArray(array $data): self
    {
        return new self(
            isNull: true === ($data['isNull'] ?? false),
            isFqcn: true === ($data['isFqcn'] ?? false),
            isClassLike: true === ($data['isClassLike'] ?? false),
            isFunctionLike: true === ($data['isFunctionLike'] ?? false),
            isMethodLike: true === ($data['isMethodLike'] ?? false),
            isClosureLike: true === ($data['isClosureLike'] ?? false),
            isArrowFunctionLike: true === ($data['isArrowFunctionLike'] ?? false),
            isPropertyLike: true === ($data['isPropertyLike'] ?? false),
            isParamLike: true === ($data['isParamLike'] ?? false),
            parent: ($data['parent'] ?? null) instanceof self ? $data['parent'] : null,
            namespace: is_string($data['namespace'] ?? null) ? $data['namespace'] : null,
            className: is_string($data['className'] ?? null) ? $data['className'] : '',
            functionName: is_string($data['functionName'] ?? null) ? $data['functionName'] : '',
            methodName: is_string($data['methodName'] ?? null) ? $data['methodName'] : '',
            propertyName: is_string($data['propertyName'] ?? null) ? $data['propertyName'] : '',
            parentScope: is_string($data['parentScope'] ?? null) ? $data['parentScope'] : '',
            index: is_int($data['index'] ?? null) ? $data['index'] : 0,
            newLine: is_int($data['newLine'] ?? null) ? $data['newLine'] : 0,
        );
    }

    public function isNull(): bool
    {
        return $this->isNull;
    }

    public function classLikeFqcn(): self
    {
        if (!$this->isClassLike) {
            return self::null();
        }

        return self::fqcn($this->namespace, $this->className);
    }

    public static function fqcn(string $namespace, string $className): self
    {
        return new self(isFqcn: true, namespace: $namespace, className: $className);
    }

    public static function classLike(?string $namespace, string $className): self
    {
        return new self(isClassLike: true, namespace: $namespace, className: $className);
    }

    public static function functionLike(?string $namespace, string $functionName): self
    {
        return new self(isFunctionLike: true, namespace: $namespace, functionName: $functionName);
    }

    public static function methodLike(?string $namespace, string $methodName): self
    {
        return new self(isMethodLike: true, namespace: $namespace, methodName: $methodName);
    }

    public static function closureLike(string $parentScope, int $closureIndex): self
    {
        return new self(isClosureLike: true, parentScope: $parentScope, index: $closureIndex);
    }

    public static function arrowFunctionLike(string $parentScope, int $closureIndex): self
    {
        return new self(isArrowFunctionLike: true, parentScope: $parentScope, index: $closureIndex);
    }

    public static function propertyLike(self $parent, string $propertyName): self
    {
        return new self(isPropertyLike: true, parent: $parent, propertyName: $propertyName);
    }

    public static function paramLike(self $parent, int $paramIndex): self
    {
        return new self(isParamLike: true, parent: $parent, index: $paramIndex);
    }

    public static function null(): self
    {
        return new self(isNull: true);
    }
}
