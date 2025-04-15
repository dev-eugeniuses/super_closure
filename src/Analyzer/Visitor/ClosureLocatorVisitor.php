<?php

declare(strict_types=1);

namespace SuperClosure\Analyzer\Visitor;

use ReflectionFunction;
use SuperClosure\Exception\ClosureAnalysisException;
use PhpParser\Node\Stmt\Namespace_ as NamespaceNode;
use PhpParser\Node\Stmt\Trait_ as TraitNode;
use PhpParser\Node\Stmt\Class_ as ClassNode;
use PhpParser\Node\Expr\Closure as ClosureNode;
use PhpParser\Node as AstNode;
use PhpParser\NodeVisitorAbstract;

/**
 * This is a visitor that extends the nikic/php-parser library and locates a closure node and its location.
 *
 * @internal
 */
final class ClosureLocatorVisitor extends NodeVisitorAbstract
{
    private ReflectionFunction $reflection;

    public ?ClosureNode $closureNode = null;

    /**
     * @var array{class: ?string, directory: string, file: string, function: string, line: int, method: ?string, namespace: ?string, trait: ?string}
     */
    public array $location;

    public function __construct(ReflectionFunction $reflection)
    {
        $this->reflection = $reflection;
        $this->location = [
            'class'     => null,
            'directory' => dirname($this->reflection->getFileName()),
            'file'      => $this->reflection->getFileName(),
            'function'  => $this->reflection->getName(),
            'line'      => $this->reflection->getStartLine(),
            'method'    => null,
            'namespace' => null,
            'trait'     => null,
        ];
    }

    public function enterNode(AstNode $node): void
    {
        // If we haven't located the closure node yet, update location info.
        if ($this->closureNode === null) {
            if ($node instanceof NamespaceNode) {
                $namespace = $node->name?->toString();
                $this->location['namespace'] = $namespace;
            }
            if ($node instanceof TraitNode) {
                $this->location['trait'] = (string)$node->name;
                $this->location['class'] = null;
            } elseif ($node instanceof ClassNode) {
                $this->location['class'] = (string)$node->name;
                $this->location['trait'] = null;
            }
        }

        // Locate the node that represents the closure.
        if ($node instanceof ClosureNode) {
            $startLine = $node->getAttribute('startLine');
            if ($startLine === $this->location['line']) {
                if ($this->closureNode !== null) {
                    $lineInfo = $this->location['file'] . ':' . $startLine;
                    throw new ClosureAnalysisException(
                        "Two closures were declared on the same line ({$lineInfo}) of code. Cannot determine which closure was the intended target."
                    );
                }

                $this->closureNode = $node;
            }
        }
    }

    public function leaveNode(AstNode $node): void
    {
        // Reset location info if the closure hasn't been located.
        if ($this->closureNode === null) {
            if ($node instanceof NamespaceNode) {
                $this->location['namespace'] = null;
            }
            if ($node instanceof TraitNode) {
                $this->location['trait'] = null;
            } elseif ($node instanceof ClassNode) {
                $this->location['class'] = null;
            }
        }
    }

    public function afterTraverse(array $nodes): void
    {
        if ($this->location['class'] !== null) {
            $namespace = $this->location['namespace'] ?? '';
            $this->location['class'] = $namespace . '\\' . $this->location['class'];
            $this->location['method'] = "{$this->location['class']}::{$this->location['function']}";
        } elseif ($this->location['trait'] !== null) {
            $namespace = $this->location['namespace'] ?? '';
            $this->location['trait'] = $namespace . '\\' . $this->location['trait'];
            $this->location['method'] = "{$this->location['trait']}::{$this->location['function']}";
            $closureScope = $this->reflection->getClosureScopeClass();
            if ($closureScope !== null) {
                $this->location['class'] = $closureScope->getName();
            } elseif ($closureThis = $this->reflection->getClosureThis()) {
                $this->location['class'] = get_class($closureThis);
            }
        }
    }
}
