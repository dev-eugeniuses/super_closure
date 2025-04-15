<?php

declare(strict_types=1);

namespace SuperClosure\Analyzer\Visitor;

use PhpParser\Node as AstNode;
use PhpParser\Node\Expr\Variable as VariableNode;
use PhpParser\NodeVisitorAbstract;

/**
 * Detects if the closure's AST contains a $this variable.
 *
 * @internal
 */
final class ThisDetectorVisitor extends NodeVisitorAbstract
{
    public bool $detected = false;

    public function leaveNode(AstNode $node): void
    {
        if ($node instanceof VariableNode && $node->name === 'this') {
            $this->detected = true;
        }
    }
}
