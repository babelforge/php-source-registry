<?php

declare(strict_types=1);

namespace PhpNoobs\PhpSource\Parser\Traversers\Visitors;

use PhpNoobs\PhpSource\Node\IsNode;
use PhpNoobs\PhpSource\Parser\AbstractNodeVisitor;
use PhpParser\Comment;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Nop;

/**
 * Inserts blank lines (Stmt\Nop) in the AST to improve readability with minimal formatting rules.
 *
 * Rules:
 * - In namespace statement lists: ensure a blank line after the last use statement before the next block.
 * - In class-like bodies: ensure
 *   - One blank line after the last trait-use (Stmt\TraitUse) before the next member,
 *   - One blank line between successive members (const/property/method/trait-use blocks).
 */
final class NopSpacingVisitor extends AbstractNodeVisitor
{
    public function updatedAst(): bool
    {
        return true;
    }

    /**
     * @param Node $node
     *
     * @return Node|null
     */
    public function enterNode(Node $node): ?Node
    {
        if (IsNode::namespace($node)) {
            /**
             * @var Namespace_ $node
             */
            $node->stmts = $this->addSpacingInStmtList($node->stmts);

            return null;
        }

        if (IsNode::classLike($node)) {
            /**
             * @var ClassLike $node
             */
            $node->stmts = $this->addSpacingInClassLike($node->stmts);

            return null;
        }

        return null;
    }

    /**
     * @param Stmt[] $nodes
     *
     * @return Stmt[]
     */
    public function beforeTraverse(array $nodes): array
    {
        $this->markAstUpdated();
        return $this->addSpacingInStmtList($nodes);
    }

    /**
     * Adds spacing in a namespace/global statement list:
     * inserts a Nop between a use-block and the next non-use statement.
     *
     * @param list<Stmt> $stmts
     *
     * @return list<Stmt>
     */
    private function addSpacingInStmtList(array $stmts): array
    {
        $out = [];

        foreach ($stmts as $i => $stmt) {
            $current = $stmt;

            if (IsNode::declare($current)) {
                $comments = $current->getComments();
                if ([] !== $comments) {
                    // 1) Move comments into a standalone Nop before declare
                    $out[] = $this->newComment($comments);

                    // 2) Clear comments on Declare_ so they don't stick to it
                    $current->setAttribute('comments', []);

                    // 3) Emit: header nop + blank line
                    $this->appendSingleNop($out);
                }
            }

            $out[] = $current;

            $next = $stmts[$i + 1] ?? null;

            if (IsNode::useOrGroupUse($current) && (null !== $next && !IsNode::useOrGroupUse($next))) {
                $this->appendSingleNop($out);
            }

            if (IsNode::classLikeOrFunctionOrConst($current) && IsNode::classLikeOrFunctionOrConst($next)) {
                $this->appendSingleNop($out);
            }

            if (IsNode::declare($current) && !IsNode::null($next) && !IsNode::nop($next)) {
                $this->appendSingleNop($out);
            }

            if (
                $this->hasStandaloneDocBlock($current)
                && !IsNode::null($next)
                && !IsNode::documentableStmt($current)
                && !IsNode::nop($next)
            ) {
                $this->appendSingleNop($out);
            }
        }

        return $out;
    }

    /**
     * Adds spacing inside class-like bodies.
     *
     * @param list<Stmt> $stmts
     *
     * @return list<Stmt>
     */
    private function addSpacingInClassLike(?array $stmts): array
    {
        $out = [];
        if (null === $stmts) {
            return $out;
        }

        foreach ($stmts as $i => $stmt) {
            $current = $stmt;
            $out[] = $current;

            $next = $stmts[$i + 1] ?? null;

            // After the last trait-use statement, add a blank line before the next non-trait-use member.
            if (IsNode::traitUse($current) && !IsNode::null($next) && !IsNode::traitUse($next)) {
                $this->appendSingleNop($out);
                continue;
            }

            // Between two successive members (property/const/method/trait-use blocks), add a blank line.
            if (IsNode::classMemberStmt($current) && IsNode::classMemberStmt($next)) {
                // But keep consecutive trait uses together (blank line is handled after the block).
                if (IsNode::traitUse($current) && IsNode::traitUse($next)) {
                    continue;
                }

                $this->appendSingleNop($out);
            }
        }

        return $out;
    }

    /**
     * @param Comment[] $comments
     * @return Nop
     */
    private function newComment(array $comments): Nop
    {
        $nop = new Nop();
        $nop->setAttribute('comments', $comments);
        return $nop;
    }

    /**
     * Appends a single Nop unless the last statement is already a Nop.
     *
     * @param list<Stmt> $out
     *
     * @return void
     */
    private function appendSingleNop(array &$out): void
    {
        $last = $out[array_key_last($out)] ?? null;

        if ((null !== $last) && IsNode::nop($last) && $last->getComments() === []) {
            // If it already contains a comment, it already yields spacing.
            return;
        }

        $nop = new Nop();
        $nop->setAttribute('comments', [new Comment('')]);

        $out[] = $nop;
    }

    /**
     * @param Node|null $node
     * @return bool
     */
    private function hasStandaloneDocBlock(?Node $node): bool
    {
        if ((null === $node) || !IsNode::nop($node)) {
            return false;
        }

        return array_any($node->getComments(), fn ($comment) => $comment instanceof Doc);
    }
}
