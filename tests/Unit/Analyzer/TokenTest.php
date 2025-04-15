<?php
namespace SuperClosure\Test\Unit\Analyzer;

use PHPUnit\Framework\TestCase;
use SuperClosure\Analyzer\Token;

/**
 * @covers \SuperClosure\Analyzer\Token
 */
class TokenTest extends TestCase
{
    public function testCanInstantiateLiteralToken(): void
    {
        $token = new Token('{');

        $this->assertEquals('{', $token->code);
        $this->assertNull($token->value);
        $this->assertNull($token->name);
        $this->assertNull($token->line);
        $this->assertEquals('{', (string)$token);
    }

    public function testCanInstantiateTokenWithParts(): void
    {
        $token = new Token('function', T_FUNCTION, 2);

        $this->assertEquals('function', $token->code);
        $this->assertEquals(T_FUNCTION, $token->value);
        $this->assertEquals('T_FUNCTION', $token->name);
        $this->assertEquals(2, $token->line);
    }

    public function testCanInstantiateTokenFromTokenizerOutput(): void
    {
        $token = new Token([T_FUNCTION, 'function', 2]);

        $this->assertEquals('function', $token->code);
        $this->assertEquals(T_FUNCTION, $token->value);
        $this->assertEquals('T_FUNCTION', $token->name);
        $this->assertEquals(2, $token->line);
    }

    public function testCanCheckIfTokenMatchesValue(): void
    {
        $token = new Token([T_FUNCTION, 'function', 2]);

        $this->assertTrue($token->is(T_FUNCTION));
        $this->assertTrue($token->is('function'));
        $this->assertFalse($token->is('cat'));
    }
}
