<?php

declare(strict_types=1);

namespace SuperClosure\Analyzer;

use InvalidArgumentException;

/**
 * A Token object represents an individual token parsed from PHP code.
 *
 * Each Token object is a normalized token created from the result of the
 * token_get_all() function, which is part of PHP's tokenizer.
 *
 * @link http://us2.php.net/manual/en/tokens.php
 */
class Token
{
    /**
     * The token name. Always null for literal tokens.
     *
     * @var ?string
     */
    public ?string $name;

    /**
     * The token's integer value. Always null for literal tokens.
     *
     * @var ?int
     */
    public ?int $value;

    /**
     * The PHP code of the token.
     *
     * @var string
     */
    public string $code;

    /**
     * The line number of the token in the original code.
     *
     * @var ?int
     */
    public ?int $line;

    /**
     * Constructs a token object.
     *
     * @param string|array $code  The token code or an array of token data.
     * @param ?int         $value The token's integer value.
     * @param ?int         $line  The line number of the token.
     *
     * @throws InvalidArgumentException if code is not a string after processing.
     */
    public function __construct(string|array $code, ?int $value = null, ?int $line = null)
    {
        if (is_array($code)) {
            // Expecting an array in the order: [value, code, line]
            [$value, $code, $line] = array_pad($code, 3, null);
        }

        if (!is_string($code)) {
            throw new InvalidArgumentException('Code must be a string.');
        }

        $this->code = $code;
        $this->value = $value;
        $this->line = $line;
        $this->name = $value !== null ? token_name($value) : null;
    }

    /**
     * Determines if the token's code or value is equal to the specified value.
     *
     * @param string|int $value The value to check.
     *
     * @return bool True if the token is equal to the value.
     */
    public function is(string|int $value): bool
    {
        // Compare code as string and value as integer/string.
        return $this->code === (string)$value || $this->value === $value;
    }

    /**
     * Returns the token's code as a string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->code;
    }
}
