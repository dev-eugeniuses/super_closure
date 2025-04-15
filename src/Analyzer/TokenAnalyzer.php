<?php

declare(strict_types=1);

namespace SuperClosure\Analyzer;

use SuperClosure\Exception\ClosureAnalysisException;
use SplFileObject;
use ReflectionFunction;

/**
 * This is the token-based analyzer.
 *
 * Uses reflection and tokenization to analyze a closure and determine its code and context.
 * This implementation is significantly faster than the AST-based analyzer.
 */
class TokenAnalyzer extends ClosureAnalyzer
{
    public function determineCode(array &$data): void
    {
        $this->determineTokens($data);
        $data['code'] = implode('', $data['tokens']);
        $data['hasThis'] = (str_contains($data['code'], '$this'));
    }

    private function determineTokens(array &$data): void
    {
        $potential = $this->determinePotentialTokens($data['reflection']);
        $braceLevel = 0;
        $step = 0;
        $insideUse = 0;
        $data['tokens'] = [];
        $data['context'] = [];

        foreach ($potential as $tokenData) {
            // Wrap each token in our Token object.
            $token = new Token($tokenData);
            switch ($step) {
                // Before the function declaration.
                case 0:
                    if ($token->is(T_FUNCTION)) {
                        $data['tokens'][] = $token;
                        $step++;
                    }
                    break;
                // Inside the function signature.
                case 1:
                    $data['tokens'][] = $token;
                    if ($insideUse > 0) {
                        if ($token->is(T_VARIABLE)) {
                            $varName = trim($token->code, '$ ');
                            $data['context'][$varName] = null;
                        } elseif ($token->is('&')) {
                            $data['hasRefs'] = true;
                        }
                    } elseif ($token->is(T_USE)) {
                        $insideUse++;
                    }
                    if ($token->is('{')) {
                        $step++;
                        $braceLevel++;
                    }
                    break;
                // Inside the function body.
                case 2:
                    $data['tokens'][] = $token;
                    if ($token->is('{')) {
                        $braceLevel++;
                    } elseif ($token->is('}')) {
                        $braceLevel--;
                        if ($braceLevel === 0) {
                            $step++;
                        }
                    }
                    break;
                // After the function declaration.
                case 3:
                    if ($token->is(T_FUNCTION)) {
                        throw new ClosureAnalysisException(
                            'Multiple closures were declared on the same line of code. Could not determine which closure was the intended target.'
                        );
                    }
                    break;
            }
        }
    }

    private function determinePotentialTokens(ReflectionFunction $reflection): array
    {
        $fileName = $reflection->getFileName();
        if (!is_readable($fileName)) {
            throw new ClosureAnalysisException(
                "Cannot read the file containing the closure: \"{$fileName}\"."
            );
        }

        $code = '';
        $file = new SplFileObject($fileName);
        // Reflection line numbers are 1-indexed.
        $file->seek($reflection->getStartLine() - 1);
        while ($file->key() < $reflection->getEndLine()) {
            $code .= $file->current();
            $file->next();
        }

        $code = trim($code);
        if (!str_starts_with($code, '<?php')) {
            $code = "<?php\n" . $code;
        }

        return token_get_all($code);
    }

    protected function determineContext(array &$data): void
    {
        // Get the static variables from the closure.
        $values = $data['reflection']->getStaticVariables();

        // Combine the names from the captured "use" clause with the actual values.
        foreach ($data['context'] as $name => &$value) {
            if (isset($values[$name])) {
                $value = $values[$name];
            }
        }
    }
}
