<?php

declare(strict_types=1);

namespace BabelForge\PhpSource\Parser\Traversers;

use BabelForge\PhpSource\Parser\AbstractNodeTraverser;
use BabelForge\PhpSource\Parser\Traversers\Visitors\NodeLocatorAttacherVisitor;

/**
 * Attaches stable locators to nodes as attributes:
 * - 'locator' => string (NodeLocator::value()).
 *
 * Supported nodes:
 * - ClassLike (Class_/Interface_/Trait_/Enum) => "Namespace\\Foo"
 * - ClassMethod => "Namespace\\Foo::bar"
 * - Function_ => "Namespace\\fn"
 * - Param => "...::bar#param(0)" or "...\\fn#param(0)" or "...#closure(1)#param(0)"
 * - Property => "Namespace\\Foo::$propName"
 * - Closure => "...::bar#closure(1)"
 * - ArrowFunction => "...::bar#arrow(3)"
 */
final class NodeLocatorAttacher extends AbstractNodeTraverser
{
    public const string ATTR_LOCATOR = 'locator';

    public function __construct()
    {
        parent::__construct(new NodeLocatorAttacherVisitor());
    }
}
