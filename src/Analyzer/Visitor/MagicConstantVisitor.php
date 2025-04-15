<?php

declare(strict_types=1);

namespace SuperClosure\Analyzer\Visitor;

use PhpParser\Node\Scalar\LNumber as NumberNode;
use PhpParser\Node\Scalar\String_ as StringNode;
use PhpParser\Node as AstNode;
use PhpParser\NodeVisitorAbstract;

/**
 * This visitor resolves magic constants (e.g., __FILE__) to their
 * intended values within a closure's AST.
 *
 * @internal
 */
final class MagicConstantVisitor extends NodeVisitorAbstract
{
    /**
     * Location information for resolving magic constants.
     *
     * Expected keys: 'class', 'directory', 'file', 'function', 'line', 'method', 'namespace', 'trait'.
     *
     * @var array<string, mixed>
     */
    private array $location;

    /**
     * Constructor.
     *
     * @param array<string, mixed> $location
     */
    public function __construct(array $location)
    {
        $this->location = $location;
    }

    /**
     * Called when leaving a node. Returns a new node for magic constants.
     *
     * @param AstNode $node
     * @return ?AstNode
     */
    public function leaveNode(AstNode $node): ?AstNode
    {
        return match ($node->getType()) {
            'Scalar_MagicConst_Class' => new StringNode($this->location['class'] ?? ''),
            'Scalar_MagicConst_Dir' => new StringNode($this->location['directory'] ?? ''),
            'Scalar_MagicConst_File' => new StringNode($this->location['file'] ?? ''),
            'Scalar_MagicConst_Function' => new StringNode($this->location['function'] ?? ''),
            'Scalar_MagicConst_Line' => new NumberNode((int)($node->getAttribute('startLine') ?? 0)),
            'Scalar_MagicConst_Method' => new StringNode($this->location['method'] ?? ''),
            'Scalar_MagicConst_Namespace' => new StringNode($this->location['namespace'] ?? ''),
            'Scalar_MagicConst_Trait' => new StringNode($this->location['trait'] ?? ''),
            default => null,
        };
    }
}
