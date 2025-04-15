<?php

declare(strict_types=1);

namespace SuperClosure;

use Closure;
use SuperClosure\Exception\ClosureUnserializationException;

/**
 * Interface for a serializer used to serialize Closure objects.
 */
interface SerializerInterface
{
    /**
     * Serializes a Closure.
     *
     * Takes a Closure object, decorates it with a SerializableClosure, then
     * performs the serialization.
     *
     * @param Closure $closure Closure to serialize.
     * @return string Serialized closure.
     */
    public function serialize(Closure $closure): string;

    /**
     * Unserializes a Closure.
     *
     * Takes a serialized closure, performs the unserialization, and then
     * extracts and returns the Closure object.
     *
     * @param string $serialized Serialized closure.
     * @throws ClosureUnserializationException if unserialization fails.
     * @return Closure Unserialized closure.
     */
    public function unserialize(string $serialized): Closure;

    /**
     * Retrieves data about a closure including its code, context, and binding.
     *
     * The data returned is dependent on the `ClosureAnalyzer` implementation
     * and whether the `$forSerialization` parameter is set to true. If true,
     * only data relevant to serializing the closure is returned.
     *
     * @param Closure $closure Closure to analyze.
     * @param bool $forSerialization Include only serialization data.
     * @return array Closure data.
     */
    public function getData(Closure $closure, bool $forSerialization = false): array;
}
