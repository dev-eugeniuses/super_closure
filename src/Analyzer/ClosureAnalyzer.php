<?php

declare(strict_types=1);

namespace SuperClosure\Analyzer;

use ReflectionException;
use SuperClosure\Exception\ClosureAnalysisException;
use Closure;
use ReflectionFunction;
use stdClass;

/**
 * Abstract class for analyzing a given closure.
 */
abstract class ClosureAnalyzer
{
    /**
     * Analyzes a given closure and returns an array of its details.
     *
     * @param Closure $closure The closure to analyze.
     *
     * @return array<string, mixed> An associative array containing the closure's details.
     * @throws ReflectionException
     *
     * @throws ClosureAnalysisException
     */
    public function analyze(Closure $closure): array
    {
        $data = [
            'reflection' => new ReflectionFunction($closure),
            'code'       => null,
            'hasThis'    => false,
            'context'    => [],
            'hasRefs'    => false,
            'binding'    => null,
            'scope'      => null,
            'isStatic'   => $this->isClosureStatic($closure),
        ];

        $this->determineCode($data);
        $this->determineContext($data);
        $this->determineBinding($data);

        return $data;
    }

    /**
     * Determines the code for the closure.
     *
     * @param array<string, mixed> $data An array passed by reference containing closure details.
     *
     * @return void
     */
    abstract protected function determineCode(array &$data): void;

    /**
     * Determines the context (the variables in the "use" clause) of the closure.
     *
     * @param array<string, mixed> $data An array passed by reference containing closure details.
     *
     * @return void
     */
    abstract protected function determineContext(array &$data): void;

    /**
     * Determines the binding (the $this object) and the scope (class name) of the closure.
     *
     * @param array<string, mixed> $data An array passed by reference containing closure details.
     *
     * @return void
     */
    private function determineBinding(array &$data): void
    {
        $data['binding'] = $data['reflection']->getClosureThis();
        if ($scope = $data['reflection']->getClosureScopeClass()) {
            $data['scope'] = $scope->getName();
        }
    }

    /**
     * Determines if the given closure is static.
     *
     * This is done by attempting to bind the closure to a dummy object.
     *
     * @param Closure $closure The closure to test.
     *
     * @return bool True if the closure is static, false otherwise.
     * @throws ReflectionException
     */
    private function isClosureStatic(Closure $closure): bool
    {
        // Using the error suppression operator here because bindTo might trigger a warning.
        $boundClosure = @$closure->bindTo(new stdClass());
        if ($boundClosure === null) {
            return true;
        }

        $rebound = new ReflectionFunction($boundClosure);
        return $rebound->getClosureThis() === null;
    }
}
