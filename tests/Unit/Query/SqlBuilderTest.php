<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query;

use InvalidArgumentException;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use JardisSupport\DbQuery\Query\SqlBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use UnexpectedValueException;

/**
 * Unit Tests for SqlBuilder
 *
 * Tests the core SQL building functionality.
 */
class SqlBuilderTest extends TestCase
{
    private SqlBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new SqlBuilder(new BuilderRegistry());

        // Initialize dialect property since SqlBuilder doesn't set it by default
        $reflection = new ReflectionClass($this->builder);
        $dialectProperty = $reflection->getProperty('dialect');
        $dialectProperty->setAccessible(true);
        $dialectProperty->setValue($this->builder, 'mysql');
    }

    // ==================== quoteIdentifier() Tests ====================

    public function testQuoteIdentifierBasic(): void
    {
        $quoted = $this->builder->quoteIdentifier('users');

        $this->assertStringContainsString('users', $quoted);
    }

    // ==================== formatValue() Tests ====================

    public function testFormatValueWithNull(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('formatValue');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, null);

        $this->assertEquals('NULL', $result);
    }

    public function testFormatValueWithTrue(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('formatValue');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, true);

        $this->assertEquals('1', $result);
    }

    public function testFormatValueWithFalse(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('formatValue');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, false);

        $this->assertEquals('0', $result);
    }

    public function testFormatValueWithInteger(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('formatValue');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 123);

        $this->assertEquals('123', $result);
    }

    public function testFormatValueWithFloat(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('formatValue');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 45.67);

        $this->assertEquals('45.67', $result);
    }

    public function testFormatValueWithString(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('formatValue');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'hello');

        $this->assertEquals("'hello'", $result);
    }

    public function testFormatValueWithSubquery(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('formatValue');
        $method->setAccessible(true);

        $subquery = new DbQuery();
        $result = $method->invoke($this->builder, $subquery);

        $this->assertStringStartsWith('(', $result);
        $this->assertStringEndsWith(')', $result);
    }

    // ==================== escapeString() Tests ====================

    public function testEscapeStringWithSingleQuote(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('escapeString');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, "O'Brien");

        $this->assertEquals("O''Brien", $result);
    }

    public function testEscapeStringWithBackslash(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('escapeString');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, "path\\to\\file");

        $this->assertEquals("path\\\\to\\\\file", $result);
    }

    public function testEscapeStringWithBoth(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('escapeString');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, "\\O'Brien\\");

        $this->assertStringContainsString("''", $result);
        $this->assertStringContainsString("\\\\", $result);
    }

    // ==================== validateValue() Tests ====================

    public function testValidateValueThrowsExceptionForSqlComment(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('validateValue');
        $method->setAccessible(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Potentially unsafe SQL value detected');

        $method->invoke($this->builder, "test-- comment");
    }

    public function testValidateValueThrowsExceptionForBlockComment(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('validateValue');
        $method->setAccessible(true);

        $this->expectException(InvalidArgumentException::class);

        $method->invoke($this->builder, "test /* comment */ value");
    }

    public function testValidateValueThrowsExceptionForHashComment(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('validateValue');
        $method->setAccessible(true);

        $this->expectException(InvalidArgumentException::class);

        $method->invoke($this->builder, "test # comment");
    }

    public function testValidateValueThrowsExceptionForUnion(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('validateValue');
        $method->setAccessible(true);

        $this->expectException(InvalidArgumentException::class);

        $method->invoke($this->builder, "'; UNION SELECT * FROM users--");
    }

    public function testValidateValueThrowsExceptionForLoadFile(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('validateValue');
        $method->setAccessible(true);

        $this->expectException(InvalidArgumentException::class);

        $method->invoke($this->builder, "LOAD_FILE('/etc/passwd')");
    }

    public function testValidateValueThrowsExceptionForHexLiteral(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('validateValue');
        $method->setAccessible(true);

        $this->expectException(InvalidArgumentException::class);

        $method->invoke($this->builder, "0x41646D696E");
    }

    public function testValidateValueAcceptsSafeString(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('validateValue');
        $method->setAccessible(true);

        // Should not throw exception
        $method->invoke($this->builder, "John O'Brien");
        $this->addToAssertionCount(1);
    }

    // ==================== normalizeWhitespace() Tests ====================

    public function testNormalizeWhitespaceCollapsesMultipleSpaces(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('normalizeWhitespace');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, "SELECT  *   FROM    users");

        $this->assertEquals("SELECT * FROM users", $result);
    }

    public function testNormalizeWhitespaceRemovesNewlines(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('normalizeWhitespace');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, "SELECT *\nFROM\nusers");

        $this->assertEquals("SELECT * FROM users", $result);
    }

    public function testNormalizeWhitespaceRemovesTabs(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('normalizeWhitespace');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, "SELECT\t*\tFROM\tusers");

        $this->assertEquals("SELECT * FROM users", $result);
    }

    public function testNormalizeWhitespaceTrimsSides(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('normalizeWhitespace');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, "  SELECT * FROM users  ");

        $this->assertEquals("SELECT * FROM users", $result);
    }

    // ==================== processJsonPlaceholders() Tests ====================

    public function testProcessJsonPlaceholdersWithExtract(): void
    {
        $reflection = new ReflectionClass($this->builder);

        $bindingsProperty = $reflection->getProperty('bindings');
        $bindingsProperty->setAccessible(true);
        $bindingsProperty->setValue($this->builder, []);

        $method = $reflection->getMethod('processJsonPlaceholders');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'WHERE data{{JSON_EXTRACT::$.age}}');

        $this->assertStringContainsString('JSON_EXTRACT', $result);
        $this->assertStringContainsString('$.age', $result);
        $this->assertStringNotContainsString('{{', $result);
        $this->assertStringNotContainsString('}}', $result);
    }

    public function testProcessJsonPlaceholdersWithContains(): void
    {
        $reflection = new ReflectionClass($this->builder);

        $bindingsProperty = $reflection->getProperty('bindings');
        $bindingsProperty->setAccessible(true);
        $bindingsProperty->setValue($this->builder, ['admin']);

        $method = $reflection->getMethod('processJsonPlaceholders');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'WHERE tags {{JSON_CONTAINS::?}}');

        $this->assertStringContainsString('JSON_CONTAINS', $result);
        $this->assertStringNotContainsString('{{', $result);
    }

    public function testProcessJsonPlaceholdersWithContainsAndPath(): void
    {
        $reflection = new ReflectionClass($this->builder);

        $bindingsProperty = $reflection->getProperty('bindings');
        $bindingsProperty->setAccessible(true);
        $bindingsProperty->setValue($this->builder, ['admin']);

        $method = $reflection->getMethod('processJsonPlaceholders');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'WHERE data {{JSON_CONTAINS::?::$.roles}}');

        $this->assertStringContainsString('JSON_CONTAINS', $result);
        $this->assertStringContainsString('$.roles', $result);
        $this->assertStringNotContainsString('{{', $result);
    }

    public function testProcessJsonPlaceholdersWithLength(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('processJsonPlaceholders');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'WHERE tags{{JSON_LENGTH}}');

        $this->assertStringContainsString('JSON_LENGTH', $result);
        $this->assertStringNotContainsString('{{', $result);
    }

    public function testProcessJsonPlaceholdersWithLengthAndPath(): void
    {
        $reflection = new ReflectionClass($this->builder);

        $bindingsProperty = $reflection->getProperty('bindings');
        $bindingsProperty->setAccessible(true);
        $bindingsProperty->setValue($this->builder, []);

        $method = $reflection->getMethod('processJsonPlaceholders');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'WHERE data{{JSON_LENGTH::$.items}}');

        $this->assertStringContainsString('JSON_LENGTH', $result);
        $this->assertStringContainsString('$.items', $result);
        $this->assertStringNotContainsString('{{', $result);
    }

    // ==================== formatBoolean() Tests ====================

    public function testFormatBooleanDefaultImplementation(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('formatBoolean');
        $method->setAccessible(true);

        $trueResult = $method->invoke($this->builder, true);
        $falseResult = $method->invoke($this->builder, false);

        // MySQL uses 1/0 for booleans
        $this->assertEquals('1', $trueResult);
        $this->assertEquals('0', $falseResult);
    }

    // ==================== Integration Tests ====================

    public function testFormatValueHandlesAllTypes(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('formatValue');
        $method->setAccessible(true);

        $testCases = [
            [null, 'NULL'],
            [true, '1'],
            [false, '0'],
            [123, '123'],
            [45.67, '45.67'],
            ['text', "'text'"],
        ];

        foreach ($testCases as [$input, $expected]) {
            $result = $method->invoke($this->builder, $input);
            $this->assertEquals($expected, $result, "Failed for input: " . var_export($input, true));
        }
    }

    public function testEscapeStringHandlesComplexStrings(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('escapeString');
        $method->setAccessible(true);

        $complex = "It's a path\\to\\O'Brien's file\\";
        $result = $method->invoke($this->builder, $complex);

        $this->assertStringContainsString("''", $result);
        $this->assertStringContainsString("\\\\", $result);
    }

    public function testFormatValueThrowsExceptionForUnsupportedType(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('formatValue');
        $method->setAccessible(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported parameter type');

        $method->invoke($this->builder, new \stdClass());
    }
}
