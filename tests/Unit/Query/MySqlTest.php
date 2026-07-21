<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query;

use JardisSupport\DbQuery\Data\Dialect;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use JardisSupport\DbQuery\Query\MySql;
use JardisSupport\DbQuery\Query\SqlBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Unit Tests for MySql
 *
 * Tests MySQL/MariaDB-specific SQL generation.
 */
class MySqlTest extends TestCase
{
    private MySql $builder;

    protected function setUp(): void
    {
        $this->builder = new MySql(new BuilderRegistry());
    }

    // ==================== Basic Properties Tests ====================

    public function testMySqlExtendsSqlBuilder(): void
    {
        $this->assertInstanceOf(SqlBuilder::class, $this->builder);
    }

    public function testDialectIsSetToMysql(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $property = $reflection->getProperty('dialect');
        $property->setAccessible(true);

        $this->assertEquals(Dialect::MySQL->value, $property->getValue($this->builder));
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

    public function testShouldNotSkipCrossJoin(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('shouldSkipJoinType');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'CROSS JOIN');

        $this->assertFalse($result);
    }

    // ==================== buildJsonExtract() Tests ====================

    public function testBuildJsonExtractWithSimplePath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '$.age');

        $this->assertEquals("JSON_EXTRACT(`data`, '$.age')", $result);
    }

    public function testBuildJsonExtractWithNestedPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'profile', '$.address.city');

        $this->assertEquals("JSON_EXTRACT(`profile`, '$.address.city')", $result);
    }

    public function testBuildJsonExtractWithArrayIndex(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'tags', '$[0]');

        $this->assertEquals("JSON_EXTRACT(`tags`, '\$[0]')", $result);
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

        $this->assertEquals("JSON_CONTAINS(`tags`, JSON_QUOTE(?))", $result);
    }

    public function testBuildJsonContainsWithPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '?', '$.roles');

        $this->assertEquals("JSON_CONTAINS(`data`, JSON_QUOTE(?), '$.roles')", $result);
    }

    public function testBuildJsonContainsWithNestedPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'config', '?', '$.settings.features');

        $this->assertStringContainsString("'$.settings.features'", $result);
        $this->assertStringContainsString('JSON_QUOTE(?)', $result);
    }

    // ==================== buildJsonLength() Tests ====================

    public function testBuildJsonLengthWithoutPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'tags', null);

        $this->assertEquals('JSON_LENGTH(`tags`)', $result);
    }

    public function testBuildJsonLengthWithPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '$.items');

        $this->assertEquals("JSON_LENGTH(`data`, '$.items')", $result);
    }

    public function testBuildJsonLengthWithNestedPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'profile', '$.friends.active');

        $this->assertEquals("JSON_LENGTH(`profile`, '$.friends.active')", $result);
    }

    public function testBuildJsonLengthWithArrayPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '$[0].items');

        $this->assertStringContainsString('$[0].items', $result);
    }

    // ==================== Integration Tests ====================

    public function testQuoteIdentifierIsConsistentAcrossMultipleCalls(): void
    {
        $quoted1 = $this->builder->quoteIdentifier('users');
        $quoted2 = $this->builder->quoteIdentifier('users');

        $this->assertEquals($quoted1, $quoted2);
    }

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

    // ==================== Edge Cases ====================

    public function testQuoteIdentifierWithUnicodeCharacters(): void
    {
        $quoted = $this->builder->quoteIdentifier('benutzér');

        $this->assertEquals('`benutzér`', $quoted);
    }

    public function testBuildJsonExtractWithRootPath(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '$');

        $this->assertEquals("JSON_EXTRACT(`data`, '\$')", $result);
    }

    public function testBuildJsonContainsWithComplexParameter(): void
    {
        $reflection = new ReflectionClass($this->builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($this->builder, 'data', '?', '$.nested.deep.path');

        $this->assertStringContainsString('?', $result);
        $this->assertStringContainsString('JSON_QUOTE', $result);
    }

    public function testMultipleBackticksAreEscapedCorrectly(): void
    {
        $quoted = $this->builder->quoteIdentifier('my``table');

        $this->assertEquals('`my````table`', $quoted);
    }
}
