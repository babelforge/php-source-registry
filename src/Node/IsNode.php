<?php

declare(strict_types=1);

namespace BabelForge\PhpSource\Node;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Const_;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Function_ as StmtFunction;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\TraitUseAdaptation\Alias;
use PhpParser\Node\Stmt\TraitUseAdaptation\Precedence;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UnionType;

/**
 * @param Node|null $node
 *
 * @return bool
 */
class IsNode
{
    public static function null(?Node $stmt): bool
    {
        return null === $stmt;
    }

    public static function node(?Node $stmt): bool
    {
        return $stmt instanceof Node;
    }

    public static function identifier(?Node $stmt): bool
    {
        return $stmt instanceof Identifier;
    }

    public static function precedence(?Node $stmt): bool
    {
        return $stmt instanceof Precedence;
    }

    public static function nop(?Node $node): bool
    {
        return $node instanceof Nop;
    }

    public static function use(?Node $node): bool
    {
        return $node instanceof Use_;
    }

    public static function normalUse(?Node $node): bool
    {
        return $node instanceof Use_ && Use_::TYPE_NORMAL === $node->type;
    }

    public static function groupUse(?Node $node): bool
    {
        return $node instanceof GroupUse;
    }

    public static function normalGroupUse(?Node $node): bool
    {
        return $node instanceof GroupUse && Use_::TYPE_NORMAL === $node->type;
    }

    public static function useOrGroupUse(?Node $node): bool
    {
        return self::use($node) || self::groupUse($node);
    }

    public static function declare(?Node $stmt): bool
    {
        return $stmt instanceof Declare_;
    }

    public static function trait(?Node $node): bool
    {
        return $node instanceof Trait_;
    }

    public static function traitUse(?Node $node): bool
    {
        return $node instanceof TraitUse;
    }

    public static function enum(?Node $node): bool
    {
        return $node instanceof Enum_;
    }

    public static function const(?Node $node): bool
    {
        return $node instanceof Const_;
    }

    public static function classConstant(?Node $node): bool
    {
        return $node instanceof ClassConst;
    }

    public static function constOrClassConstant(?Node $node): bool
    {
        return self::const($node) || self::classConstant($node);
    }

    public static function function(?Node $node): bool
    {
        return $node instanceof StmtFunction;
    }

    public static function closure(?Node $node): bool
    {
        return $node instanceof Closure;
    }

    public static function arrowFunction(?Node $node): bool
    {
        return $node instanceof ArrowFunction;
    }

    public static function classMethod(?Node $node): bool
    {
        return $node instanceof ClassMethod;
    }

    public static function functionOrClassMethod(?Node $node): bool
    {
        return self::function($node) || self::classMethod($node);
    }

    public static function namespace(?Node $node): bool
    {
        return $node instanceof Namespace_;
    }

    public static function class(?Node $node): bool
    {
        return $node instanceof Class_;
    }

    public static function classLike(?Node $node): bool
    {
        return $node instanceof ClassLike;
    }

    /**
     * Recognizes statements that act as class members.
     */
    public static function classMemberStmt(?Node $node): bool
    {
        if (null === $node) {
            return false;
        }

        return self::traitUse($node)
            || self::property($node)
            || self::classConstant($node)
            || self::classMethod($node);
    }

    public static function classLikeOrFunctionOrConst(?Node $node): bool
    {
        if (null === $node) {
            return false;
        }

        return self::class($node)
            || self::interface($node)
            || self::trait($node)
            || self::enum($node)
            || self::function($node)
            || self::const($node);
    }

    public static function interface(?Node $node): bool
    {
        return $node instanceof Interface_;
    }

    public static function property(?Node $node): bool
    {
        return $node instanceof Property;
    }

    public static function param(?Node $node): bool
    {
        return $node instanceof Param;
    }

    public static function name(?Node $node): bool
    {
        return $node instanceof Name;
    }

    public static function relative(?Node $node): bool
    {
        return $node instanceof Name\Relative;
    }

    public static function string(?Node $node): bool
    {
        return $node instanceof String_;
    }

    public static function int(?Node $node): bool
    {
        return $node instanceof Int_;
    }

    public static function float(?Node $node): bool
    {
        return $node instanceof Float_;
    }

    public static function expressionConstFetch(?Node $node): bool
    {
        return $node instanceof ConstFetch;
    }

    public static function expressionArray(?Node $node): bool
    {
        return $node instanceof Array_;
    }

    public static function classConstFetch(?Node $node): bool
    {
        return $node instanceof ClassConstFetch;
    }

    public static function magicConst(?Node $node): bool
    {
        return $node instanceof MagicConst;
    }

    public static function complexType(?Node $node): bool
    {
        return $node instanceof ComplexType;
    }

    public static function nullableType(?Node $node): bool
    {
        return $node instanceof NullableType;
    }

    public static function unionType(?Node $node): bool
    {
        return $node instanceof UnionType;
    }

    public static function intersectionType(?Node $node): bool
    {
        return $node instanceof IntersectionType;
    }

    public static function new(?Node $node): bool
    {
        return $node instanceof New_;
    }

    public static function catch(?Node $node): bool
    {
        return $node instanceof Catch_;
    }

    public static function instanceof(?Node $node): bool
    {
        return $node instanceof Instanceof_;
    }

    public static function staticCall(?Node $node): bool
    {
        return $node instanceof StaticCall;
    }

    public static function staticPropertyFetch(?Node $node): bool
    {
        return $node instanceof StaticPropertyFetch;
    }

    public static function attribute(?Node $node): bool
    {
        return $node instanceof Attribute;
    }

    public static function alias(?Node $node): bool
    {
        return $node instanceof Alias;
    }

    public static function documentableStmt(?Node $node): bool
    {
        return self::class($node)
            || self::trait($node)
            || self::interface($node)
            || self::enum($node)
            || self::function($node)
            || self::classMethod($node)
            || self::property($node)
            || self::classConstant($node);
    }
}
