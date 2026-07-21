<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query;

use JardisSupport\DbQuery\Data\Dialect;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use JardisSupport\DbQuery\Query\SqlBuilder;
use JardisSupport\DbQuery\Query\SqliteSql;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit Tests for SqliteSql
 *
 * Tests SQLite-specific SQL generation.
 */
class SqliteSqlTest extends TestCase
{
    private SqliteSql $builder;

    protected function setUp(): void
    {
        $this->builder = new SqliteSql(new BuilderRegistry());
    }

    // ==================== Basic Properties Tests ====================

    public function testSqliteSqlExtendsSqlBuilder(): void
    {
        $this->assertInstanceOf(SqlBuilder::class, $this->builder);
    }

    public function testDialectIsSetToSqlite(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $property = $reflection->getProperty('dialect');
        $property->setAccessible(true);

        $this->assertEquals(Dialect::SQLite->value, $property->getValue($this->builder));
    }

    // ==================== quoteIdentifier() Tests ====================

    public function testQuoteIdentifierUsesBackticks(): void
    {
        $quoted = $this->builder->quoteIdentifier('users');

        $this->assertEquals('`users`', $quoted);
    }

    public function testQuoteIdentifierWithTableAndColumn(): void
    {
        $quoted = $this->builder->quoteIdentifier('users.id');

        $this->assertEquals('`users.id`', $quoted);
    }

    public function testQuoteIdentifierEscapesBackticks(): void
    {
        $quoted = $this->builder->quoteIdentifier('my`table');

        $this->assertEquals('`my``table`', $quoted);
    }

    // ==================== shouldSkipJoinType() Tests ====================

    public function testShouldSkipFullJoin(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('shouldSkipJoinType');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'FULL JOIN');

        $this->assertTrue($result);
    }

    public function testShouldSkipFullOuterJoin(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('shouldSkipJoinType');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'FULL OUTER JOIN');

        $this->assertTrue($result);
    }

    public function testShouldNotSkipInnerJoin(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('shouldSkipJoinType');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'INNER JOIN');

        $this->assertFalse($result);
    }

    public function testShouldNotSkipLeftJoin(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('shouldSkipJoinType');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'LEFT JOIN');

        $this->assertFalse($result);
    }

    public function testShouldNotSkipRightJoin(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('shouldSkipJoinType');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'RIGHT JOIN');

        $this->assertFalse($result);
    }

    // ==================== buildJsonExtract() Tests ====================

    public function testBuildJsonExtractWithSimplePath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '$.age');

        $this->assertEquals("json_extract(`data`, '$.age')", $result);
    }

    public function testBuildJsonExtractWithNestedPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'profile', '$.address.city');

        $this->assertEquals("json_extract(`profile`, '$.address.city')", $result);
    }

    public function testBuildJsonExtractWithArrayIndex(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'tags', '$[0]');

        $this->assertEquals("json_extract(`tags`, '\$[0]')", $result);
    }

    public function testBuildJsonExtractUsesLowercaseFunction(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '$.field');

        $this->assertStringStartsWith('json_extract(', $result);
    }

    public function testBuildJsonExtractEscapesSingleQuotes(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', "$.field'with'quotes");

        $this->assertStringContainsString("''", $result);
    }

    // ==================== buildJsonContains() Tests ====================

    public function testBuildJsonContainsWithoutPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'tags', '?', null);

        $this->assertEquals('EXISTS (SELECT 1 FROM json_each(`tags`) WHERE value = ?)', $result);
    }

    public function testBuildJsonContainsWithPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '?', '$.roles');

        $expected = "EXISTS (SELECT 1 FROM json_each(json_extract(`data`, '$.roles')) WHERE value = ?)";
        $this->assertEquals($expected, $result);
    }

    public function testBuildJsonContainsUsesExistsClause(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'tags', '?', null);

        $this->assertStringStartsWith('EXISTS (', $result);
        $this->assertStringEndsWith(')', $result);
    }

    public function testBuildJsonContainsUsesJsonEach(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'tags', '?', null);

        $this->assertStringContainsString('json_each(', $result);
        $this->assertStringContainsString('WHERE value = ', $result);
    }

    public function testBuildJsonContainsWithNestedPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'config', '?', '$.settings.features');

        $this->assertStringContainsString('json_extract', $result);
        $this->assertStringContainsString('$.settings.features', $result);
        $this->assertStringContainsString('json_each', $result);
    }

    // ==================== buildJsonNotContains() Tests ====================

    public function testBuildJsonNotContainsWithoutPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonNotContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'tags', '?', null);

        $this->assertEquals('NOT EXISTS (SELECT 1 FROM json_each(`tags`) WHERE value = ?)', $result);
    }

    public function testBuildJsonNotContainsWithPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonNotContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '?', '$.status');

        $expected = "NOT EXISTS (SELECT 1 FROM json_each(json_extract(`data`, '$.status')) WHERE value = ?)";
        $this->assertEquals($expected, $result);
    }

    public function testBuildJsonNotContainsUsesNotExists(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonNotContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'tags', '?', null);

        $this->assertStringStartsWith('NOT EXISTS (', $result);
    }

    public function testBuildJsonNotContainsIsNegationOfContains(): void
    {
        $reflection = new ReflectionClass($this->builder);

        $containsMethod = $reflection->getMethod('buildJsonContains');
        $containsMethod->setAccessible(true);
        $containsResult = $containsMethod->invoke($this->builder, 'tags', '?', null);

        $notContainsMethod = $reflection->getMethod('buildJsonNotContains');
        $notContainsMethod->setAccessible(true);
        $notContainsResult = $notContainsMethod->invoke($this->builder, 'tags', '?', null);

        $this->assertEquals('NOT ' . $containsResult, $notContainsResult);
    }

    // ==================== buildJsonLength() Tests ====================

    public function testBuildJsonLengthWithoutPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'tags', null);

        $this->assertEquals('json_array_length(`tags`)', $result);
    }

    public function testBuildJsonLengthWithPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '$.items');

        $this->assertEquals("json_array_length(json_extract(`data`, '$.items'))", $result);
    }

    public function testBuildJsonLengthWithNestedPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'profile', '$.friends.active');

        $expected = "json_array_length(json_extract(`profile`, '$.friends.active'))";
        $this->assertEquals($expected, $result);
    }

    public function testBuildJsonLengthUsesJsonArrayLength(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'items', null);

        $this->assertStringStartsWith('json_array_length(', $result);
    }

    public function testBuildJsonLengthWithPathExtractsFirst(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '$.products');

        $this->assertStringContainsString('json_extract', $result);
        $this->assertStringContainsString('json_array_length', $result);
    }

    // ==================== Integration Tests ====================

    public function testJsonFunctionsUseQuotedIdentifiers(): void
    {
        $reflection = new ReflectionClass($this->builder);

        $extractMethod = $reflection->getMethod('buildJsonExtract');
        $extractMethod->setAccessible(true);
        $extractResult = $extractMethod->invoke($this->builder, 'data', '$.field');

        $this->assertStringContainsString('`data`', $extractResult);
    }

    public function testJsonContainsUsesQuotedIdentifiers(): void
    {
        $reflection = new ReflectionClass($this->builder);

        $containsMethod = $reflection->getMethod('buildJsonContains');
        $containsMethod->setAccessible(true);
        $containsResult = $containsMethod->invoke($this->builder, 'tags', '?', null);

        $this->assertStringContainsString('`tags`', $containsResult);
    }

    public function testJsonLengthUsesQuotedIdentifiers(): void
    {
        $reflection = new ReflectionClass($this->builder);

        $lengthMethod = $reflection->getMethod('buildJsonLength');
        $lengthMethod->setAccessible(true);
        $lengthResult = $lengthMethod->invoke($this->builder, 'items', null);

        $this->assertStringContainsString('`items`', $lengthResult);
    }

    public function testQuoteIdentifierIsConsistentAcrossMultipleCalls(): void
    {
        $quoted1 = $this->builder->quoteIdentifier('users');
        $quoted2 = $this->builder->quoteIdentifier('users');

        $this->assertEquals($quoted1, $quoted2);
    }

    // ==================== Edge Cases ====================

    public function testBuildJsonExtractWithRootPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '$');

        $this->assertEquals("json_extract(`data`, '\$')", $result);
    }

    public function testBuildJsonContainsWithComplexParameter(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '?', '$.nested.path');

        $this->assertStringContainsString('?', $result);
        $this->assertStringContainsString('json_each', $result);
    }

    public function testQuoteIdentifierWithEmptyString(): void
    {
        $quoted = $this->builder->quoteIdentifier('');

        $this->assertEquals('``', $quoted);
    }

    public function testQuoteIdentifierWithSpecialCharacters(): void
    {
        $quoted = $this->builder->quoteIdentifier('user-table');

        $this->assertEquals('`user-table`', $quoted);
    }

    public function testQuoteIdentifierWithUnicodeCharacters(): void
    {
        $quoted = $this->builder->quoteIdentifier('benutzér');

        $this->assertEquals('`benutzér`', $quoted);
    }

    public function testMultipleBackticksAreEscapedCorrectly(): void
    {
        $quoted = $this->builder->quoteIdentifier('my``table');

        $this->assertEquals('`my````table`', $quoted);
    }

    public function testBuildJsonExtractWithArrayPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '$[0].items');

        $this->assertStringContainsString('$[0].items', $result);
    }

    public function testBuildJsonContainsSimulatesArrayContainment(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'array_col', '?', null);

        $this->assertStringContainsString('json_each', $result);
        $this->assertStringContainsString('SELECT 1', $result);
    }
}
