<?php

namespace SuperClosure\Test\Unit\Analyzer\Visitor;

use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Trait_;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionFunction;
use RuntimeException;
use SuperClosure\Analyzer\Visitor\ClosureLocatorVisitor;

/**
 * @covers SuperClosure\Analyzer\Visitor\ClosureLocatorVisitor
 */
class ClosureLocatorVisitorTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    public function testClosureNodeIsDiscoveredByVisitor(): void
    {
        $closure = function () { }; $startLine = __LINE__;
        $reflectedClosure = new ReflectionFunction($closure);
        $closureFinder = new ClosureLocatorVisitor($reflectedClosure);
        $closureNode = new Closure([], ['startLine' => $startLine]);
        $closureFinder->enterNode($closureNode);

        $this->assertNotNull($closureFinder->closureNode);
        $this->assertSame($closureNode, $closureFinder->closureNode);
    }

    /**
     * @throws ReflectionException
     */
    public function testClosureNodeIsAmbiguousIfMultipleClosuresOnLine(): void
    {
        $this->expectException(RuntimeException::class);

        $closure = function () { }; function () { }; $startLine = __LINE__;
        $closureFinder = new ClosureLocatorVisitor(new ReflectionFunction($closure));
        $closureFinder->enterNode(new Closure([], ['startLine' => $startLine]));
        $closureFinder->enterNode(new Closure([], ['startLine' => $startLine]));
    }

    /**
     * @throws ReflectionException
     */
    public function testCalculatesClosureLocation(): void
    {
        $closure = function () { };
        $closureFinder = new ClosureLocatorVisitor(new ReflectionFunction($closure));

        $closureFinder->beforeTraverse([]);

        $node = new Namespace_(new Name(['Foo', 'Bar']));
        $closureFinder->enterNode($node);
        $closureFinder->leaveNode($node);

        $node = new Trait_('Fizz');
        $closureFinder->enterNode($node);
        $closureFinder->leaveNode($node);

        $node = new Class_('Buzz');
        $closureFinder->enterNode($node);
        $closureFinder->leaveNode($node);

        $closureFinder->afterTraverse([]);

        $actualLocationKeys = array_filter($closureFinder->location);
        $expectedLocationKeys = ['directory', 'file', 'function', 'line'];

        $this->assertEquals($expectedLocationKeys, array_keys($actualLocationKeys));
    }

    /**
     * @throws ReflectionException
     */
    public function testCanDetermineClassOrTraitInfo(): void
    {
        $closure = function () { };
        $closureFinder = new ClosureLocatorVisitor(new ReflectionFunction($closure));
        $closureFinder->location['namespace'] = __NAMESPACE__;

        $closureFinder->location['class'] = 'FooClass';
        $closureFinder->afterTraverse([]);
        $class = $closureFinder->location['class'];
        $this->assertEquals(__NAMESPACE__ . '\FooClass', $class);

        $closureFinder->location['class'] = null;
        $closureFinder->location['trait'] = 'FooTrait';
        $closureFinder->afterTraverse([]);
        $trait = $closureFinder->location['trait'];
        $this->assertEquals(__NAMESPACE__ . '\FooTrait', $trait);
    }
}