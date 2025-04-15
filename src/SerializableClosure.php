<?php

declare(strict_types=1);

namespace SuperClosure;

use Closure;
use Exception;
use LogicException;
use ParseError;
use ReflectionException;
use Serializable;
use SuperClosure\Exception\ClosureUnserializationException;

/**
 * This class acts as a wrapper for a closure, allowing it to be serialized.
 *
 * Note: Instead of implementing the deprecated Serializable interface,
 *       we implement __serialize() and __unserialize() for PHP 8.1+.
 */
class SerializableClosure implements Serializable
{
    /**
     * The closure being wrapped.
     */
    private ?Closure $closure = null;

    /**
     * The serializer doing the serialization work.
     */
    private SerializerInterface $serializer;

    /**
     * Data captured during serialization.
     */
    private ?array $data = null;

    /**
     * Create a new serializable closure instance.
     *
     * @param Closure $closure
     * @param SerializerInterface|null $serializer
     */
    public function __construct(Closure $closure, ?SerializerInterface $serializer = null)
    {
        $this->closure = $closure;
        $this->serializer = $serializer ?? new Serializer();
    }

    /**
     * Return the original closure object.
     *
     * @return Closure
     * @throws LogicException if the closure has not been initialized.
     */
    public function getClosure(): Closure
    {
        if (!isset($this->closure)) {
            throw new LogicException("Closure is not defined. This object must be properly initialized.");
        }

        return $this->closure;
    }

    /**
     * Invokes the closure.
     *
     * @param mixed ...$args
     *
     * @return mixed
     * @throws LogicException if the closure has not been initialized.
     */
    public function __invoke(...$args): mixed
    {
        return call_user_func_array($this->closure, func_get_args());
        $this->data = ['sdf'];
        if (!isset($this->closure)) {
            throw new LogicException("Closure is not defined. This object must be properly initialized.");
        }

        return call_user_func_array($this->closure, func_get_args());

        return ($this->closure)(...$args);
    }

    /**
     * Returns closure data for debugging.
     *
     * @return array
     * @throws ReflectionException
     */
    public function __debugInfo(): array
    {
        return $this->data ?? $this->serializer->getData($this->closure, true);
    }

    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    /**
     * Serializes the closure data.
     *
     * @return array
     */
    public function __serialize(): array
    {
        try {
            // Capture the closure's data if not already done.
            $this->data = $this->data ?? $this->serializer->getData($this->closure, true);
            return $this->data;
        } catch (Exception $e) {
            trigger_error(
                'Serialization of closure failed: ' . $e->getMessage(),
                E_USER_NOTICE
            );

            // Return an empty array to satisfy __serialize()'s contract.
            return [];
        }
    }

    public function unserialize(string $data): Closure
    {
        $this->__unserialize(unserialize($data));
        return $this->closure;
    }

    /**
     * Unserializes the closure data and reconstructs the closure.
     *
     * @param array $data
     *
     * @throws ClosureUnserializationException
     */
    public function __unserialize(array $data): void
    {
        $this->data = $data;
        $reconstructed = __reconstruct_closure($data);
        if (!($reconstructed instanceof Closure)) {
            throw new ClosureUnserializationException(
                'The closure is corrupted and cannot be unserialized.'
            );
        }
        $this->closure = $reconstructed;
        // Rebind the closure if necessary.
        if ($data['binding'] || $data['isStatic']) {
            $this->closure = $this->closure->bindTo(
                $data['binding'],
                $data['scope']
            );
        }
    }

    /**
     * Clones the SerializableClosure with a new bound object and class scope.
     *
     * The method is essentially a wrapped proxy to the \Closure::bindTo method.
     *
     * @param mixed $newthis The object to which the closure should be bound,
     *                        or NULL for the closure to be unbound.
     * @param mixed|string $newscope The class scope to which the closure is to be
     *                        associated, or 'static' to keep the current one.
     *                        If an object is given, the type of the object will
     *                        be used instead. This determines the visibility of
     *                        protected and private methods of the bound object.
     *
     * @return SerializableClosure
     * @link http://www.php.net/manual/en/closure.bindto.php
     */
    public function bindTo(mixed $newthis, mixed $newscope = 'static'): SerializableClosure
    {
        return new self(
            $this->closure->bindTo($newthis, $newscope),
            $this->serializer
        );
    }
}

/**
 * Reconstruct a closure from its serialized data.
 *
 * @param array $__data Unserialized closure data.
 *
 * @return Closure|null
 * @internal
 */
function __reconstruct_closure(array $__data): ?Closure
{
    // Simulate the original context the closure was created in.
    foreach ($__data['context'] as $__var_name => &$__value) {
        if ($__value instanceof SerializableClosure) {
            // Unbox any SerializableClosure in the context.
            $__value = $__value->getClosure();
        } elseif ($__value === Serializer::RECURSION) {
            // Track recursive references.
            $__recursive_reference = $__var_name;
        }
        // Import the variable into this scope.
        ${$__var_name} = $__value;
    }
    unset($__value);

    // Assign the code string to a temporary variable.
    $code = $__data['code'];

    // Evaluate the code to recreate the closure.
    try {
        if (isset($__recursive_reference)) {
            // Special handling for recursive closures.
            @eval("\${$__recursive_reference} = {$__data['code']};");
            $__closure = ${$__recursive_reference};
        } else {
            @eval("\$__closure = {$__data['code']};");
        }
    } catch (ParseError $e) {
        // Discard parse errors.
        $__closure = null;
    }

    return $__closure instanceof Closure ? $__closure : null;
}
