<?php
namespace SuperClosure\Test\Unit\Analyzer\Visitor;

use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Variable;
use PHPUnit\Framework\TestCase;
use SuperClosure\Analyzer\Visitor\ThisDetectorVisitor;

/**
 * @covers SuperClosure\Analyzer\Visitor\ThisDetectorVisitor
 */
class ThisDetectorVisitorTest extends TestCase
{
    public function testThisIsDiscovered(): void
    {
        $visitor = new ThisDetectorVisitor();

        $visitor->leaveNode(new Variable('this'));

        $this->assertTrue($visitor->detected);
    }

    public function testThisIsNotDiscovered(): void
    {
        $visitor = new ThisDetectorVisitor();

        $visitor->leaveNode(new Variable('foo'));

        $this->assertFalse($visitor->detected);
    }

    public function testThisIsNotDiscoveredWithNonVariable(): void
    {
        $visitor = new ThisDetectorVisitor();

        $visitor->leaveNode(new Closure());

        $this->assertFalse($visitor->detected);
    }
}
