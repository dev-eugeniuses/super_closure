<?php

namespace SuperClosure\Test\Unit;

use ArrayObject;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionFunction;
use stdClass;
use SuperClosure\Analyzer\ClosureAnalyzer;
use SuperClosure\Analyzer\TokenAnalyzer;
use SuperClosure\Exception\ClosureUnserializationException;
use SuperClosure\SerializableClosure;
use SuperClosure\Serializer;

/**
 * @covers \SuperClosure\Serializer
 */
class SerializerTest extends TestCase
{
    public function testSerializingAndUnserializing(): void
    {
        $serializer = new Serializer(new TokenAnalyzer());
        $originalFn = static function ($n) { return $n + 5; };
        $serializedFn = $serializer->serialize($originalFn);
        $unserializedFn = $serializer->unserialize($serializedFn);

        $this->assertEquals(10, $originalFn(5));
        $this->assertEquals(10, $unserializedFn(5));
    }

    public function testUnserializingFailsWithInvalidSignature(): void
    {
        // Create a serializer with a signing key.
        $serializer = new Serializer(null, 'foobar');
        $originalFn = function ($n) { return $n + 5; };
        $serializedFn = $serializer->serialize($originalFn);

        // Modify the serialized closure.
        $serializedFn[5] = 'x';

        // Unserialization should fail on invalid signature.
        $this->expectException(ClosureUnserializationException::class);
        $serializer->unserialize($serializedFn);
    }

    public function testUnserializingFailsWithInvalidData(): void
    {
        $this->expectException(ClosureUnserializationException::class);
        $serializer = new Serializer(new TokenAnalyzer());
        $data = 'foobar' . serialize('foobar');
        $serializer->unserialize($data);
    }

    public function testUnserializingFailsWhenSuperClosureIsNotReturned(): void
    {
        $this->expectException(ClosureUnserializationException::class);
        $serializer = new Serializer(new TokenAnalyzer());
        $data = serialize('foobar');
        $serializer->unserialize($data);
    }

    public function testSerializingAndUnserializingWithSignature(): void
    {
        // Create a serializer with a signing key.
        $serializer = new Serializer(null, 'foobar');
        $originalFn = static function ($n) { return $n + 5; };
        $serializedFn = $serializer->serialize($originalFn);

        // Check data to make sure it looks like an array(2).
        $this->assertEquals('%', $serializedFn[0]);
        $unserializedData = unserialize(substr($serializedFn, 45));
        $this->assertInstanceOf(SerializableClosure::class, $unserializedData);

        // Make sure unserialization still works.
        $unserializedFn = $serializer->unserialize($serializedFn);
        $this->assertEquals(10, $originalFn(5));
        $this->assertEquals(10, $unserializedFn(5));
    }

    /**
     * @throws ReflectionException
     */
    public function testGettingClosureData(): void
    {
        $adjustment = 2;
        $fn = function ($n) use (&$fn, $adjustment) {
            $result = $n > 1 ? $n * $fn($n - 1) : 1;
            return $result + $adjustment;
        };

        $serializer = new Serializer(new TokenAnalyzer());

        // Test getting full closure data.
        $data = $serializer->getData($fn);
        $this->assertCount(9, $data);
        $this->assertInstanceOf('ReflectionFunction', $data['reflection']);
        $this->assertGreaterThan(0, strpos($data['code'], '$adjustment'));
        $this->assertFalse($data['hasThis']);
        $this->assertCount(2, $data['context']);
        $this->assertTrue($data['hasRefs']);
        $this->assertInstanceOf(__CLASS__, $data['binding']);
        $this->assertEquals(__CLASS__, $data['scope']);
        $this->assertIsArray($data['tokens'], 'array');

        // Test getting serializable closure data.
        $data = $serializer->getData($fn, true);
        $this->assertCount(5, $data);
        $this->assertContains(Serializer::RECURSION, $data['context']);
        $this->assertNull($data['binding']);
        $this->assertEquals(__CLASS__, $data['scope']);
        $this->assertArrayNotHasKey('reflection', $data);
    }

    /**
     * @throws ReflectionException|Exception
     */
    public function testWrappingClosuresWithinVariables(): void
    {
        $serializer = new Serializer(
            $this->createMock(ClosureAnalyzer::class)
        );

        $value1 = function () { };
        Serializer::wrapClosures($value1, $serializer);
        $this->assertInstanceOf(SerializableClosure::class, $value1);

        $value2 = ['fn' => function () { }];
        Serializer::wrapClosures($value2, $serializer);
        $this->assertInstanceOf(SerializableClosure::class, $value2['fn']);

        $value3 = new stdClass;
        $value3->fn = function () { };
        Serializer::wrapClosures($value3, $serializer);
        $this->assertInstanceOf(SerializableClosure::class, $value3->fn);

        if (!defined('HHVM_VERSION')) {
            $value4 = new ArrayObject([function () { }]);
            Serializer::wrapClosures($value4, $serializer);
            $this->assertInstanceOf(SerializableClosure::class, $value4[0]);
        }

        $thing = new Serializer();
        $fn = function () { return $this->analyzer; };

        /** @var SerializableClosure $value5 */
        $value5 = $fn->bindTo($thing, Serializer::class);
        Serializer::wrapClosures($value5, $serializer);
        $reflection = new ReflectionFunction($value5->getClosure());
        $this->assertSame($thing, $reflection->getClosureThis());
        $this->assertEquals(get_class($thing), $reflection->getClosureScopeClass()->getName());

        /** @var SerializableClosure $value6 */
        $value6 = $fn->bindTo($thing);
        Serializer::wrapClosures($value6, $serializer);
        $reflection = new ReflectionFunction($value6->getClosure());
        $this->assertEquals(__CLASS__, $reflection->getClosureScopeClass()->getName());
    }
}
