<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query;

use JardisSupport\DbQuery\Data\Dialect;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use JardisSupport\DbQuery\Query\PostgresSql;
use JardisSupport\DbQuery\Query\SqlBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit Tests for PostgresSql
 *
 * Tests PostgreSQL-specific SQL generation with focus on correct JSON operator usage.
 *
 * PostgreSQL JSON Operators:
 * - `->`  : Extracts JSON object/array element, returns JSON/JSONB type
 * - `->>` : Extracts JSON object/array element, returns TEXT type
 * - `@>`  : JSONB containment operator (left JSON contains right JSON)
 *
 * For WHERE clause comparisons, we typically want TEXT output (->>) on the final value.
 * For intermediate steps in nested paths, we use (->) to maintain JSON type.
 * For functions like jsonb_array_length(), we need JSON/JSONB input, so we use (->).
 */
class PostgresSqlTest extends TestCase
{
    private PostgresSql $builder;

    protected function setUp(): void
    {
        $this->builder = new PostgresSql(new BuilderRegistry('postgres', '14'));
    }

    // ==================== Basic Properties Tests ====================

    public function testPostgresSqlExtendsSqlBuilder(): void
    {
        $this->assertInstanceOf(SqlBuilder::class, $this->builder);
    }

    public function testDialectIsSetToPostgres(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $property = $reflection->getProperty('dialect');
        $property->setAccessible(true);

        $this->assertEquals(Dialect::PostgreSQL->value, $property->getValue($this->builder));
    }

    // ==================== quoteIdentifier() Tests ====================

    public function testQuoteIdentifierUsesDoubleQuotes(): void
    {
        $quoted = $this->builder->quoteIdentifier('users');

        $this->assertEquals('"users"', $quoted);
    }

    public function testQuoteIdentifierWithTableAndColumn(): void
    {
        $quoted = $this->builder->quoteIdentifier('users.id');

        // Note: This quotes the entire string, not individual parts
        $this->assertEquals('"users.id"', $quoted);
    }

    public function testQuoteIdentifierEscapesDoubleQuotes(): void
    {
        $quoted = $this->builder->quoteIdentifier('my"table');

        // Double quotes inside identifiers are escaped by doubling them
        $this->assertEquals('"my""table"', $quoted);
    }

    public function testQuoteIdentifierWithEmptyString(): void
    {
        $quoted = $this->builder->quoteIdentifier('');

        $this->assertEquals('""', $quoted);
    }

    public function testQuoteIdentifierWithSpecialCharacters(): void
    {
        $quoted = $this->builder->quoteIdentifier('user-table');

        $this->assertEquals('"user-table"', $quoted);
    }

    public function testQuoteIdentifierWithUnicodeCharacters(): void
    {
        $quoted = $this->builder->quoteIdentifier('benutzér');

        $this->assertEquals('"benutzér"', $quoted);
    }

    public function testMultipleDoubleQuotesAreEscapedCorrectly(): void
    {
        $quoted = $this->builder->quoteIdentifier('my""table');

        // Each pair of double quotes is escaped to four double quotes
        $this->assertEquals('"my""""table"', $quoted);
    }

    public function testQuoteIdentifierIsConsistentAcrossMultipleCalls(): void
    {
        $quoted1 = $this->builder->quoteIdentifier('users');
        $quoted2 = $this->builder->quoteIdentifier('users');

        $this->assertEquals($quoted1, $quoted2);
    }

    // ==================== formatBoolean() Tests ====================

    public function testFormatBooleanReturnsTrueForTrue(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('formatBoolean');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, true);

        // PostgreSQL uses TRUE (not 1) for boolean true
        $this->assertEquals('TRUE', $result);
    }

    public function testFormatBooleanReturnsFalseForFalse(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('formatBoolean');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, false);

        // PostgreSQL uses FALSE (not 0) for boolean false
        $this->assertEquals('FALSE', $result);
    }

    // ==================== buildJsonExtract() Tests ====================
    // These tests verify correct operator usage: ->> for text, -> for JSON

    public function testBuildJsonExtractWithSimplePathDollarPrefix(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '$.age');

        // Simple path with $.age returns TEXT using ->> operator
        $this->assertEquals('"data"->>\'age\'', $result);
    }

    public function testBuildJsonExtractWithSimplePathNoDollarPrefix(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', 'age');

        // Simple path without $ prefix also returns TEXT using ->> operator
        $this->assertEquals('"data"->>\'age\'', $result);
    }

    public function testBuildJsonExtractWithTwoLevelPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '$.user.name');

        // Two-level path: -> for intermediate, ->> for final (returns TEXT)
        $this->assertEquals('"data"->\'user\'->>\'name\'', $result);
    }

    public function testBuildJsonExtractWithThreeLevelPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'profile', '$.address.city.name');

        // Three-level: -> -> ->> pattern
        $this->assertEquals('"profile"->\'address\'->\'city\'->>\'name\'', $result);
    }

    public function testBuildJsonExtractWithFourLevelPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '$.a.b.c.d');

        // Four-level: -> -> -> ->> pattern
        $this->assertEquals('"data"->\'a\'->\'b\'->\'c\'->>\'d\'', $result);
    }

    public function testBuildJsonExtractDollarDotPrefixIsRemoved(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '$.field');

        // Verify $. prefix is properly removed
        $this->assertStringNotContainsString('$.', $result);
        $this->assertStringContainsString('field', $result);
    }

    public function testBuildJsonExtractOperatorPatternInNestedPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '$.a.b');

        // Verify both operators are present in nested paths
        $this->assertStringContainsString('->', $result);
        $this->assertStringContainsString('->>', $result);
        // Verify -> comes before ->>
        $this->assertLessThan(strpos($result, '->>'), strpos($result, '->'));
    }

    public function testBuildJsonExtractEscapesSingleQuotesInPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', "$.field'with'quotes");

        // Single quotes in paths must be escaped by doubling them
        $this->assertStringContainsString("''", $result);
    }

    public function testBuildJsonExtractWithRootPathOnly(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '$');

        // Root path $ should just reference the column
        $this->assertStringContainsString('"data"', $result);
    }

    public function testBuildJsonExtractWithComplexColumnName(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'user_metadata_json', '$.email');

        // Column name with underscores should be properly quoted
        $this->assertEquals('"user_metadata_json"->>\'email\'', $result);
    }

    // ==================== buildJsonContains() Tests ====================
    // Tests for PostgreSQL @> containment operator with JSONB

    public function testBuildJsonContainsWithoutPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'tags', '?', null);

        // Direct containment: column @> value
        $this->assertEquals('"tags" @> to_jsonb(?::text)', $result);
    }

    public function testBuildJsonContainsWithSimplePath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '?', '$.roles');

        // Path-specific containment: extract path first, then check containment
        // Use -> (not ->>) because @> needs JSON/JSONB input
        $this->assertEquals('"data"->\'roles\' @> to_jsonb(?::text)', $result);
    }

    public function testBuildJsonContainsWithNestedPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'config', '?', '$.settings.features');

        // Nested path is treated as single key after conversion
        $this->assertStringContainsString('"config"->\'settings.features\'', $result);
        $this->assertStringContainsString('@> to_jsonb(?::text)', $result);
    }

    public function testBuildJsonContainsUsesContainmentOperator(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', ':value', null);

        // Verify @> operator is used
        $this->assertStringContainsString('@>', $result);
    }

    public function testBuildJsonContainsWrapsValueInToJsonb(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', ':param1', null);

        // Value must be converted to JSONB for comparison
        $this->assertStringContainsString('to_jsonb(:param1::text)', $result);
    }

    public function testBuildJsonContainsUsesDoubleQuotedIdentifiers(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'tags', ':param1', null);

        // Column identifiers use double quotes
        $this->assertStringContainsString('"tags"', $result);
    }

    public function testBuildJsonContainsWithComplexParameterName(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', ':q123_p456', '$.nested.path');

        // Complex parameter names should be preserved
        $this->assertStringContainsString(':q123_p456', $result);
        $this->assertStringContainsString('to_jsonb(:q123_p456::text)', $result);
    }

    // ==================== buildJsonNotContains() Tests ====================
    // Tests for negated containment (NOT @>)

    public function testBuildJsonNotContainsWithoutPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonNotContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'tags', '?', null);

        // Wraps containment check in NOT()
        $this->assertEquals('NOT ("tags" @> to_jsonb(?::text))', $result);
    }

    public function testBuildJsonNotContainsWithPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonNotContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '?', '$.status');

        // Negates path-specific containment check
        $this->assertEquals('NOT ("data"->\'status\' @> to_jsonb(?::text))', $result);
    }

    public function testBuildJsonNotContainsWrapsInNotParentheses(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonNotContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '?', null);

        // Must wrap entire expression in NOT (...)
        $this->assertStringStartsWith('NOT (', $result);
        $this->assertStringEndsWith(')', $result);
    }

    public function testBuildJsonNotContainsWithNestedPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonNotContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'config', '?', '$.a.b.c');

        // Nested path handled correctly in negation
        $this->assertStringContainsString('NOT (', $result);
        $this->assertStringContainsString('"config"->\'a.b.c\'', $result);
        $this->assertStringContainsString('@> to_jsonb(?::text)', $result);
    }

    // ==================== buildJsonLength() Tests ====================
    // Tests for jsonb_array_length() function

    public function testBuildJsonLengthWithoutPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'tags', null);

        // Direct array length on column
        $this->assertEquals('jsonb_array_length("tags")', $result);
    }

    public function testBuildJsonLengthWithSimplePath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '$.items');

        // Extract path with -> (not ->>) because jsonb_array_length needs JSON input
        $this->assertEquals('jsonb_array_length("data"->\'items\')', $result);
    }

    public function testBuildJsonLengthWithNestedPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'profile', '$.friends.active');

        // Nested path treated as single key
        $this->assertStringContainsString('jsonb_array_length', $result);
        $this->assertStringContainsString('"profile"->\'friends.active\'', $result);
    }

    public function testBuildJsonLengthUsesJsonbArrayLengthFunction(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'items', null);

        // Function name is correct
        $this->assertStringStartsWith('jsonb_array_length(', $result);
    }

    public function testBuildJsonLengthWithPathUsesSingleArrowOperator(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '$.items');

        // Must use -> (not ->>) because function requires JSON/JSONB input
        $this->assertStringContainsString('->', $result);
        $this->assertStringNotContainsString('->>', $result);
    }

    public function testBuildJsonLengthUsesDoubleQuotedIdentifiers(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'my_array', '$.elements');

        // Column names are double-quoted
        $this->assertStringContainsString('"my_array"', $result);
    }

    // ==================== Integration Tests ====================
    // Tests that verify multiple components work together correctly

    public function testJsonExtractUsesDoubleQuotedIdentifiers(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $extractMethod = $reflection->getMethod('buildJsonExtract');
        $extractMethod->setAccessible(true);

        $result = $extractMethod->invoke($this->builder, 'data', '$.field');

        // Column identifiers use double quotes in all JSON functions
        $this->assertStringContainsString('"data"', $result);
    }

    public function testAllJsonFunctionsUseDoubleQuotedIdentifiers(): void
    {
        $reflection = new ReflectionClass($this->builder);

        // Test buildJsonExtract
        $extractMethod = $reflection->getMethod('buildJsonExtract');
        $extractMethod->setAccessible(true);
        $extractResult = $extractMethod->invoke($this->builder, 'col1', '$.field');
        $this->assertStringContainsString('"col1"', $extractResult);

        // Test buildJsonContains
        $containsMethod = $reflection->getMethod('buildJsonContains');
        $containsMethod->setAccessible(true);
        $containsResult = $containsMethod->invoke($this->builder, 'col2', '?', null);
        $this->assertStringContainsString('"col2"', $containsResult);

        // Test buildJsonLength
        $lengthMethod = $reflection->getMethod('buildJsonLength');
        $lengthMethod->setAccessible(true);
        $lengthResult = $lengthMethod->invoke($this->builder, 'col3', null);
        $this->assertStringContainsString('"col3"', $lengthResult);
    }

    public function testJsonExtractConsistencyBetweenCallsWithSameInput(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result1 = $method->invoke($this->builder, 'data', '$.field');
        $result2 = $method->invoke($this->builder, 'data', '$.field');

        // Same input produces same output
        $this->assertEquals($result1, $result2);
    }

    public function testJsonContainsConsistencyBetweenCalls(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result1 = $method->invoke($this->builder, 'tags', '?', '$.roles');
        $result2 = $method->invoke($this->builder, 'tags', '?', '$.roles');

        // Same input produces same output
        $this->assertEquals($result1, $result2);
    }

    // ==================== Edge Cases and Special Scenarios ====================

    public function testBuildJsonExtractWithSpecialCharactersInColumnName(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'user-data_v2', '$.name');

        // Special characters in column name are properly quoted
        $this->assertStringContainsString('"user-data_v2"', $result);
    }

    public function testBuildJsonContainsWithEmptyParameterName(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '?', null);

        // Placeholder should be preserved
        $this->assertStringContainsString('to_jsonb(?::text)', $result);
    }

    public function testBuildJsonLengthWithEmptyPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        // Empty string path should be treated as null
        $result = $method->invoke($this->builder, 'data', '');

        // Should act like no path provided
        $this->assertStringContainsString('jsonb_array_length("data"', $result);
    }
}
