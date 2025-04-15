<?php

namespace SuperClosure\Test\Unit\Analyzer\Visitor;

use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\MagicConst\Class_;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\Node\Scalar\MagicConst\File;
use PhpParser\Node\Scalar\MagicConst\Function_;
use PhpParser\Node\Scalar\MagicConst\Line;
use PhpParser\Node\Scalar\MagicConst\Method;
use PhpParser\Node\Scalar\MagicConst\Namespace_;
use PhpParser\Node\Scalar\MagicConst\Trait_;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeAbstract;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SuperClosure\Analyzer\Visitor\MagicConstantVisitor;

#[CoversClass(SuperClosure\Analyzer\Visitor\MagicConstantVisitor::class)] class MagicConstantVisitorTest extends TestCase
{
    public static function classNameProvider(): array
    {
        return [
            [Class_::class, String_::class],
            [Dir::class, String_::class],
            [File::class, String_::class],
            [Function_::class, String_::class],
            [Line::class, LNumber::class],
            [Method::class, String_::class],
            [Namespace_::class, String_::class],
            [Trait_::class, String_::class],
            [String_::class, String_::class],
        ];
    }

    #[DataProvider('classNameProvider')] public function testDataFromClosureLocationGetsUsed($original, $result): void
    {
        $location = [
            'class' => null,
            'directory' => null,
            'file' => null,
            'function' => null,
            'line' => null,
            'method' => null,
            'namespace' => null,
            'trait' => null,
        ];

        $visitor = new MagicConstantVisitor($location);

        $node = $this->getMockParserNode($original, str_replace('\\', '_', substr(rtrim($original, '_'), 15)));
        $resultNode = $visitor->leaveNode($node) ?: $node;

        $this->assertInstanceOf($result, $resultNode);
    }

    /**
     * @param string $class
     * @param string|null $type
     * @param string|null $attribute
     *
     * @return NodeAbstract
     */
    public function getMockParserNode(string $class, ?string $type = null, ?string $attribute = null): NodeAbstract
    {
        $node = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getType', 'getAttribute'])
            ->getMock();
        $node
            ->method('getAttribute')
            ->willReturn($attribute);
        $node
            ->method('getType')
            ->willReturn($type);

        return $node;
    }
}
