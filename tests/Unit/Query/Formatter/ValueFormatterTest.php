<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Formatter;

use InvalidArgumentException;
use JardisSupport\DbQuery\Query\Formatter\ValueFormatter;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ValueFormatter class
 *
 * Coverage:
 * - NULL value formatting
 * - Boolean value formatting (dialect-specific)
 * - Numeric value formatting (int, float)
 * - String value formatting (validation + escaping)
 * - Subquery formatting
 * - Invalid type handling
 * - Callback-based formatting
 */
class ValueFormatterTest extends TestCase
{
    private ValueFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ValueFormatter();
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withNullValue_returnsNull(): void
    {
        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $result = ($this->formatter)(null, 'mysql', $formatBoolean, $escapeString, $validateValue);

        $this->assertSame('NULL', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withTrueBoolean_usesFormatCallback(): void
    {
        $formatBoolean = fn($value) => $value ? 'TRUE' : 'FALSE';
        $escapeString = fn($str) => addslashes($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $result = ($this->formatter)(true, 'postgres', $formatBoolean, $escapeString, $validateValue);

        $this->assertSame('TRUE', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withFalseBoolean_usesFormatCallback(): void
    {
        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $result = ($this->formatter)(false, 'mysql', $formatBoolean, $escapeString, $validateValue);

        $this->assertSame('0', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withInteger_returnsStringRepresentation(): void
    {
        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $result = ($this->formatter)(42, 'mysql', $formatBoolean, $escapeString, $validateValue);

        $this->assertSame('42', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withNegativeInteger_returnsStringRepresentation(): void
    {
        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $result = ($this->formatter)(-123, 'sqlite', $formatBoolean, $escapeString, $validateValue);

        $this->assertSame('-123', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withFloat_returnsStringRepresentation(): void
    {
        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $result = ($this->formatter)(3.14159, 'postgres', $formatBoolean, $escapeString, $validateValue);

        $this->assertSame('3.14159', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withZero_returnsStringZero(): void
    {
        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $result = ($this->formatter)(0, 'mysql', $formatBoolean, $escapeString, $validateValue);

        $this->assertSame('0', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withSimpleString_validatesAndEscapes(): void
    {
        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);
        $validatedCalled = false;
        $validateValue = function ($str) use (&$validatedCalled) {
            $validatedCalled = true;
        };

        $result = ($this->formatter)('John Doe', 'mysql', $formatBoolean, $escapeString, $validateValue);

        $this->assertSame("'John Doe'", $result);
        $this->assertTrue($validatedCalled);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withStringContainingQuotes_escapesCorrectly(): void
    {
        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $result = ($this->formatter)("O'Reilly", 'mysql', $formatBoolean, $escapeString, $validateValue);

        $this->assertSame("'O\\'Reilly'", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withStringContainingBackslashes_escapesCorrectly(): void
    {
        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $result = ($this->formatter)('C:\\Program Files\\', 'sqlite', $formatBoolean, $escapeString, $validateValue);

        $this->assertSame("'C:\\\\Program Files\\\\'", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withEmptyString_returnsQuotedEmpty(): void
    {
        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $result = ($this->formatter)('', 'postgres', $formatBoolean, $escapeString, $validateValue);

        $this->assertSame("''", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withInvalidString_throwsFromValidator(): void
    {
        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);
        $validateValue = function ($str) {
            throw new InvalidArgumentException('SQL injection detected');
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL injection detected');

        ($this->formatter)('DROP TABLE users', 'mysql', $formatBoolean, $escapeString, $validateValue);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withSubquery_returnsSubquerySql(): void
    {
        $subquery = $this->createMock(DbQueryBuilderInterface::class);
        $subquery->method('sql')->with('mysql', false)->willReturn('SELECT id FROM users WHERE active = 1');

        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $result = ($this->formatter)($subquery, 'mysql', $formatBoolean, $escapeString, $validateValue);

        $this->assertSame('(SELECT id FROM users WHERE active = 1)', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withSubqueryForPostgres_usesCorrectDialect(): void
    {
        $subquery = $this->createMock(DbQueryBuilderInterface::class);
        $subquery->method('sql')->with('postgres', false)->willReturn('SELECT id FROM users WHERE active = true');

        $formatBoolean = fn($value) => $value ? 'true' : 'false';
        $escapeString = fn($str) => pg_escape_string($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $result = ($this->formatter)($subquery, 'postgres', $formatBoolean, $escapeString, $validateValue);

        $this->assertSame('(SELECT id FROM users WHERE active = true)', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withSubqueryForSqlite_usesCorrectDialect(): void
    {
        $subquery = $this->createMock(DbQueryBuilderInterface::class);
        $subquery->method('sql')->with('sqlite', false)->willReturn('SELECT id FROM users LIMIT 10');

        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $result = ($this->formatter)($subquery, 'sqlite', $formatBoolean, $escapeString, $validateValue);

        $this->assertSame('(SELECT id FROM users LIMIT 10)', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withArray_throwsInvalidArgumentException(): void
    {
        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported parameter type: array');

        ($this->formatter)([1, 2, 3], 'mysql', $formatBoolean, $escapeString, $validateValue);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withObject_throwsInvalidArgumentException(): void
    {
        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported parameter type: object');

        ($this->formatter)(new \stdClass(), 'mysql', $formatBoolean, $escapeString, $validateValue);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withResource_throwsInvalidArgumentException(): void
    {
        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $resource = fopen('php://memory', 'r');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported parameter type: resource');

        try {
            ($this->formatter)($resource, 'mysql', $formatBoolean, $escapeString, $validateValue);
        } finally {
            fclose($resource);
        }
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withMySqlDialect_usesMySqlBooleanFormat(): void
    {
        // MySQL typically uses 1/0 for booleans
        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $resultTrue = ($this->formatter)(true, 'mysql', $formatBoolean, $escapeString, $validateValue);
        $resultFalse = ($this->formatter)(false, 'mysql', $formatBoolean, $escapeString, $validateValue);

        $this->assertSame('1', $resultTrue);
        $this->assertSame('0', $resultFalse);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withPostgresDialect_usesPostgresBooleanFormat(): void
    {
        // PostgreSQL uses true/false
        $formatBoolean = fn($value) => $value ? 'true' : 'false';
        $escapeString = fn($str) => pg_escape_string($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $resultTrue = ($this->formatter)(true, 'postgres', $formatBoolean, $escapeString, $validateValue);
        $resultFalse = ($this->formatter)(false, 'postgres', $formatBoolean, $escapeString, $validateValue);

        $this->assertSame('true', $resultTrue);
        $this->assertSame('false', $resultFalse);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withCustomEscaping_usesProvidedCallback(): void
    {
        $formatBoolean = fn($value) => $value ? '1' : '0';
        // Custom escaping that replaces spaces with underscores
        $escapeString = fn($str) => str_replace(' ', '_', $str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $result = ($this->formatter)('Hello World', 'mysql', $formatBoolean, $escapeString, $validateValue);

        $this->assertSame("'Hello_World'", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_validationIsCalledBeforeEscaping(): void
    {
        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);

        $callOrder = [];
        $validateValue = function ($str) use (&$callOrder) {
            $callOrder[] = 'validate';
            if (strpos($str, 'DROP') !== false) {
                throw new InvalidArgumentException('Dangerous SQL');
            }
        };

        // This will call validate before escaping
        try {
            ($this->formatter)('normal string', 'mysql', $formatBoolean, $escapeString, $validateValue);
            $this->assertSame(['validate'], $callOrder);
        } catch (InvalidArgumentException $e) {
            $this->fail('Should not throw for normal string');
        }
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function invoke_withComplexString_handlesCorrectly(): void
    {
        $formatBoolean = fn($value) => $value ? '1' : '0';
        $escapeString = fn($str) => addslashes($str);
        $validateValue = function ($str) {
            // No-op validator
        };

        $complexString = "Line 1\nLine 2\tTabbed\r\nLine 3 with 'quotes' and \"double quotes\"";
        $result = ($this->formatter)($complexString, 'mysql', $formatBoolean, $escapeString, $validateValue);

        $expected = "'" . addslashes($complexString) . "'";
        $this->assertSame($expected, $result);
    }
}
