<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Command\Delete;

use JardisSupport\DbQuery\command\Delete\DeleteSqliteSql;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for DeleteSqliteSql
 *
 * Tests SQLite-specific DELETE SQL generation.
 */
class DeleteSqliteSqlTest extends TestCase
{
    // ==================== Quote Identifier Tests ====================

    public function testQuoteIdentifierWithSimpleName(): void
    {
        $builder = new DeleteSqliteSql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('quoteIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'users');

        $this->assertEquals('`users`', $result);
    }

    public function testQuoteIdentifierEscapesBackticks(): void
    {
        $builder = new DeleteSqliteSql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('quoteIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'table`name');

        $this->assertEquals('`table``name`', $result);
    }

    public function testQuoteIdentifierWithMultipleBackticks(): void
    {
        $builder = new DeleteSqliteSql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('quoteIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'tab``le');

        $this->assertEquals('`tab````le`', $result);
    }

    // ==================== JSON Extract Tests ====================

    public function testBuildJsonExtractSimplePath(): void
    {
        $builder = new DeleteSqliteSql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '$.status');

        $this->assertEquals("json_extract(`data`, '$.status')", $result);
    }

    public function testBuildJsonExtractNestedPath(): void
    {
        $builder = new DeleteSqliteSql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'metadata', '$.user.id');

        $this->assertEquals("json_extract(`metadata`, '$.user.id')", $result);
    }

    public function testBuildJsonExtractArrayIndex(): void
    {
        $builder = new DeleteSqliteSql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'items', '$[0].name');

        $this->assertEquals("json_extract(`items`, '$[0].name')", $result);
    }

    // ==================== JSON Contains Tests ====================

    public function testBuildJsonContainsWithoutPath(): void
    {
        $builder = new DeleteSqliteSql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'tags', '?', null);

        $expected = "EXISTS (SELECT 1 FROM json_each(`tags`) WHERE value = ?)";
        $this->assertEquals($expected, $result);
    }

    public function testBuildJsonContainsWithPath(): void
    {
        $builder = new DeleteSqliteSql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '?', '$.items');

        $expected = "EXISTS (SELECT 1 FROM json_each(json_extract(`data`, '$.items')) WHERE value = ?)";
        $this->assertEquals($expected, $result);
    }

    public function testBuildJsonContainsWithNestedPath(): void
    {
        $builder = new DeleteSqliteSql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'metadata', '?', '$.user.roles');

        $expected = "EXISTS (SELECT 1 FROM json_each(json_extract(`metadata`, '$.user.roles')) WHERE value = ?)";
        $this->assertEquals($expected, $result);
    }

    // ==================== JSON Not Contains Tests ====================

    public function testBuildJsonNotContainsWithoutPath(): void
    {
        $builder = new DeleteSqliteSql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonNotContains');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'tags', '?', null);

        $expected = "NOT EXISTS (SELECT 1 FROM json_each(`tags`) WHERE value = ?)";
        $this->assertEquals($expected, $result);
    }

    public function testBuildJsonNotContainsWithPath(): void
    {
        $builder = new DeleteSqliteSql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonNotContains');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '?', '$.items');

        $expected = "NOT EXISTS (SELECT 1 FROM json_each(json_extract(`data`, '$.items')) WHERE value = ?)";
        $this->assertEquals($expected, $result);
    }

    // ==================== JSON Length Tests ====================

    public function testBuildJsonLengthWithoutPath(): void
    {
        $builder = new DeleteSqliteSql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'items', null);

        $this->assertEquals("json_array_length(`items`)", $result);
    }

    public function testBuildJsonLengthWithPath(): void
    {
        $builder = new DeleteSqliteSql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '$.tags');

        $this->assertEquals("json_array_length(`data`, '$.tags')", $result);
    }

    public function testBuildJsonLengthWithNestedPath(): void
    {
        $builder = new DeleteSqliteSql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'metadata', '$.user.permissions');

        $this->assertEquals("json_array_length(`metadata`, '$.user.permissions')", $result);
    }

    // ==================== Dialect Tests ====================

    public function testDialectIsSqlite(): void
    {
        $builder = new DeleteSqliteSql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('dialect');
        $property->setAccessible(true);

        $dialect = $property->getValue($builder);

        $this->assertEquals('sqlite', $dialect);
    }
}
