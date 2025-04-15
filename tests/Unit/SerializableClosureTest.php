<?php

namespace SuperClosure\Test\Unit;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestStatus\Notice;
use ReflectionException;
use stdClass;
use SuperClosure\Exception\ClosureAnalysisException;
use SuperClosure\Exception\ClosureUnserializationException;
use SuperClosure\SerializableClosure;
use SuperClosure\Serializer;

class SerializableClosureTestDummy
{
    public Closure $c;

    public function __construct()
    {
        $this->c = static function () {
            return 2;
        };
    }
}

#[CoversClass(\SuperClosure\SerializableClosure::class)] class SerializableClosureTest extends TestCase
{
    public function testGanGetAndInvokeTheClosure(): void
    {
        $closure = function () { return 4; };
        $sc = new SerializableClosure($closure, $this->getMockSerializer());
        $this->assertSame($closure, $sc->getClosure());
        $this->assertEquals(4, $sc());
    }

    /**
     * @param bool $error
     *
     * @return Serializer
     */
    private function getMockSerializer(bool $error = false): Serializer
    {
        $serializer = $this->getMockBuilder(Serializer::class)
            ->onlyMethods(['getData'])
            ->getMock();

        if ($error) {
            $serializer->method('getData')->willThrowException(
                new ClosureAnalysisException
            );
        } else {
            $serializer->method('getData')->willReturn([
                'code' => 'function () {};',
                'context' => [],
                'binding' => null,
                'scope' => null,
                'isStatic' => false,
            ]);
        }

        return $serializer;
    }

    public function testGanBindSerializableClosure(): void
    {
        $obj1 = new stdClass();
        $obj2 = new stdClass();
        $closure = function () { };
        $closure = $closure->bindTo($obj1);
        $sc1 = new SerializableClosure($closure, $this->getMockSerializer());
        $sc2 = $sc1->bindTo($obj2);
        $this->assertInstanceOf(SerializableClosure::class, $sc2);
        $this->assertNotSame($sc1, $sc2);
        $this->assertNotSame($sc1->getClosure(), $sc2->getClosure());
    }

    public function testCanSerializeAClosure(): void
    {
        $closure = function () { };
        $sc = new SerializableClosure($closure, $this->getMockSerializer());
        $serialization = serialize($sc);

        $this->assertGreaterThan(0, strpos($serialization, 'function () {};'));
    }

    public function testSerializationTriggersNoticeOnBadClosure(): void
    {
        $formerLevel = error_reporting(-1);

        set_error_handler(static function (int $errno, string $errstr): bool {
            // Capture the error message.
            $GLOBALS['errorMessage'] = $errstr;
            return true;
        });

        // Create a simple closure.
        $closure = function () { };function () { };

        // Use a mock serializer that simulates a failure.
        $sc = new SerializableClosure($closure, $this->getMockSerializer(true));

        // Call serialize() which should trigger a notice.
        serialize($sc);

        restore_error_handler();
        error_reporting($formerLevel);

        // Retrieve the captured error message.
        $errorMessage = $GLOBALS['errorMessage'] ?? null;

        $this->assertNotNull($errorMessage, "Expected a notice to be triggered.");
        $this->assertStringContainsString('Serialization of closure failed:', $errorMessage);
    }

    public function testSerializationReturnsNullOnBadClosure(): void
    {
        $formerLevel = error_reporting(0);
        $closure = function () { };function () { };
        $sc = new SerializableClosure($closure, $this->getMockSerializer(true));
        $serialization = serialize($sc);
        $this->assertEquals('O:32:"SuperClosure\SerializableClosure":0:{}', $serialization);
        error_reporting($formerLevel);
    }

    /**
     * @throws ReflectionException
     */
    public function testDebuggingCallsSerializer(): void
    {
        $closure = function () { };
        $serializer = $this->getMockSerializer();
        $sc = new SerializableClosure($closure, $serializer);
        $this->assertEquals(
            $sc->__debugInfo(),
            $serializer->getData($closure)
        );
    }

    public function testUnserializationFailsIfClosureCorrupt(): void
    {
        $serialized = file_get_contents(__DIR__ . '/serialized-corrupt.txt');
        $this->expectException(ClosureUnserializationException::class);
        unserialize($serialized);
    }

    public function testUnserializationWorksForRecursiveClosures(): void
    {
        $serialized = file_get_contents(__DIR__ . '/serialized-recursive.txt');
        /** @var Closure $unserialized */
        $unserialized = unserialize($serialized);
        $this->assertEquals(120, $unserialized(5));
    }

    public function testCanSerializeAndUnserializeMultipleTimes(): void
    {
        $closure = function () { };
        $serializer = $this->getMockSerializer();
        $sc = new SerializableClosure($closure, $serializer);
        $s1 = serialize($sc);
        $u1 = unserialize($s1);
        $s2 = serialize($u1);
        $this->assertEquals($s1, $s2);
    }

    public function testSerializationOfClosureProperty(): void
    {
        $obj = new SerializableClosureTestDummy();
        $closure = function () use ($obj) { return 4 * $obj->c->__invoke(); };
        $sc = new SerializableClosure($closure, $this->getMockSerializer());
        $this->assertSame($closure, $sc->getClosure());
        $this->assertEquals(8, $sc());
    }
}
