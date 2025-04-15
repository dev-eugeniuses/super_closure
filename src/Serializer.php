<?php

declare(strict_types=1);

namespace SuperClosure;

use ReflectionException;
use ReflectionFunction;
use ReflectionObject;
use Serializable;
use stdClass;
use SuperClosure\Analyzer\AstAnalyzer as DefaultAnalyzer;
use SuperClosure\Analyzer\ClosureAnalyzer;
use SuperClosure\Exception\ClosureSerializationException;
use SuperClosure\Exception\ClosureUnserializationException;
use Closure;

class Serializer implements SerializerInterface
{
    public const string RECURSION = "{{RECURSION}}";

    /** @var array<string, bool> */
    private static array $dataToKeep = [
        'code'     => true,
        'context'  => true,
        'binding'  => true,
        'scope'    => true,
        'isStatic' => true,
    ];

    private ClosureAnalyzer $analyzer;
    private ?string $signingKey;

    /**
     * Create a new serializer instance.
     *
     * @param ClosureAnalyzer|null $analyzer   Closure analyzer instance.
     * @param string|null          $signingKey HMAC key to sign closure data.
     */
    public function __construct(?ClosureAnalyzer $analyzer = null, ?string $signingKey = null)
    {
        $this->analyzer = $analyzer ?? new DefaultAnalyzer();
        $this->signingKey = $signingKey;
    }

    /**
     * Serializes a Closure.
     *
     * @param Closure $closure Closure to serialize.
     * @return string Serialized closure.
     * @throws ClosureSerializationException
     */
    public function serialize(Closure $closure): string
    {
        $serialized = serialize(new SerializableClosure($closure, $this));
        // serialize() always returns a string, so no need to check for null.
        if ($this->signingKey !== null) {
            $signature = $this->calculateSignature($serialized);
            $serialized = '%' . base64_encode($signature) . $serialized;
        }
        return $serialized;
    }

    /**
     * Unserializes a Closure.
     *
     * @param string $serialized Serialized closure.
     * @return Closure Unserialized closure.
     * @throws ClosureUnserializationException
     */
    public function unserialize(string $serialized): Closure
    {
        $signature = '';
        if ($serialized[0] === '%') {
            $signature = base64_decode(substr($serialized, 1, 44));
            $serialized = substr($serialized, 45);
        }

        if ($this->signingKey !== null) {
            $this->verifySignature($signature, $serialized);
        }

        // Suppress errors during unserialization.
        set_error_handler(static fn() => null);
        $unserialized = unserialize($serialized);
        restore_error_handler();

        if ($unserialized === false) {
            throw new ClosureUnserializationException('The closure could not be unserialized.');
        }

        if (!$unserialized instanceof SerializableClosure) {
            throw new ClosureUnserializationException('The closure did not unserialize to a SuperClosure.');
        }

        return $unserialized->getClosure();
    }

    /**
     * Retrieves data about a closure.
     *
     * @param Closure $closure Closure to analyze.
     * @param bool $forSerialization Include only serialization data.
     *
     * @return array Closure data.
     * @throws ReflectionException
     */
    public function getData(Closure $closure, bool $forSerialization = false): array
    {
        $data = $this->analyzer->analyze($closure);

        if ($forSerialization) {
            if (!$data['hasThis']) {
                $data['binding'] = null;
            }

            // Keep only the keys defined in self::$dataToKeep.
            $data = array_intersect_key($data, self::$dataToKeep);

            // Wrap any closures in the context.
            foreach ($data['context'] as &$value) {
                if ($value instanceof Closure) {
                    $value = ($value === $closure)
                        ? self::RECURSION
                        : new SerializableClosure($value, $this);
                }
            }
        }

        return $data;
    }

    /**
     * Recursively traverses and wraps all Closure objects within a variable.
     *
     * @param mixed &$data Any variable that contains closures.
     * @param SerializerInterface $serializer The serializer to use.
     *
     * @throws ReflectionException
     */
    public static function wrapClosures(mixed &$data, SerializerInterface $serializer): void
    {
        if ($data instanceof Closure) {
            $reflection = new ReflectionFunction($data);
            if ($binding = $reflection->getClosureThis()) {
                self::wrapClosures($binding, $serializer);
                $scope = $reflection->getClosureScopeClass();
                $scope = $scope ? $scope->getName() : 'static';
                $data = $data->bindTo($binding, $scope);
            }
            $data = new SerializableClosure($data, $serializer);
        } elseif (is_iterable($data) || $data instanceof stdClass) {
            foreach ($data as &$value) {
                self::wrapClosures($value, $serializer);
            }
            unset($value);
        } elseif (is_object($data) && !$data instanceof Serializable) {
            $reflection = new ReflectionObject($data);
            if (!$reflection->hasMethod('__sleep')) {
                foreach ($reflection->getProperties() as $property) {
                    if ($property->isPrivate() || $property->isProtected()) {
                        $property->setAccessible(true);
                    }
                    $value = $property->getValue($data);
                    self::wrapClosures($value, $serializer);
                    $property->setValue($data, $value);
                }
            }
        }
    }

    /**
     * Calculates a signature for a closure's serialized data.
     *
     * @param string $data Serialized closure data.
     * @return string Signature of the closure's data.
     */
    private function calculateSignature(string $data): string
    {
        return hash_hmac('sha256', $data, $this->signingKey ?? '', true);
    }

    /**
     * Verifies the signature for a closure's serialized data.
     *
     * @param string $signature The provided signature.
     * @param string $data      The serialized closure data.
     * @throws ClosureUnserializationException If the signature is invalid.
     */
    private function verifySignature(string $signature, string $data): void
    {
        if (!hash_equals($signature, $this->calculateSignature($data))) {
            throw new ClosureUnserializationException(
                'The signature of the closure\'s data is invalid, which means the serialized closure has been modified and is unsafe to unserialize.'
            );
        }
    }
}
