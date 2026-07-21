<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Command\Update;

use JardisSupport\DbQuery\command\update\UpdateMySql;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for UpdateMySql
 *
 * Tests MySQL-specific UPDATE SQL generation.
 */
class UpdateMySqlTest extends TestCase
{
    // ==================== Quote Identifier Tests ====================

    public function testQuoteIdentifierWithSimpleName(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('quoteIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'column_name');

        $this->assertEquals('`column_name`', $result);
    }

    public function testQuoteIdentifierEscapesBackticks(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('quoteIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'column`name');

        $this->assertEquals('`column``name`', $result);
    }

    public function testQuoteIdentifierWithMultipleBackticks(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('quoteIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'col``umn');

        $this->assertEquals('`col````umn`', $result);
    }

    // ==================== Should Skip Join Type Tests ====================

    public function testShouldSkipFullJoin(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('shouldSkipJoinType');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'FULL JOIN');

        $this->assertTrue($result);
    }

    public function testShouldSkipFullOuterJoin(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('shouldSkipJoinType');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'FULL OUTER JOIN');

        $this->assertTrue($result);
    }

    public function testShouldNotSkipInnerJoin(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('shouldSkipJoinType');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'INNER JOIN');

        $this->assertFalse($result);
    }

    public function testShouldNotSkipLeftJoin(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('shouldSkipJoinType');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'LEFT JOIN');

        $this->assertFalse($result);
    }

    public function testShouldNotSkipRightJoin(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('shouldSkipJoinType');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'RIGHT JOIN');

        $this->assertFalse($result);
    }

    // ==================== JSON Extract Tests ====================

    public function testBuildJsonExtractSimplePath(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '$.age');

        $this->assertEquals("JSON_EXTRACT(`data`, '$.age')", $result);
    }

    public function testBuildJsonExtractNestedPath(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'metadata', '$.user.name');

        $this->assertEquals("JSON_EXTRACT(`metadata`, '$.user.name')", $result);
    }

    public function testBuildJsonExtractArrayIndex(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'items', '$[0]');

        $this->assertEquals("JSON_EXTRACT(`items`, '$[0]')", $result);
    }

    // ==================== JSON Contains Tests ====================

    public function testBuildJsonContainsWithoutPath(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'tags', '?', null);

        $this->assertEquals("JSON_CONTAINS(`tags`, JSON_QUOTE(?))", $result);
    }

    public function testBuildJsonContainsWithPath(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '?', '$.items');

        $expected = "JSON_CONTAINS(`data`, JSON_QUOTE(?), '$.items')";
        $this->assertEquals($expected, $result);
    }

    public function testBuildJsonContainsWithNestedPath(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'metadata', '?', '$.user.roles');

        $expected = "JSON_CONTAINS(`metadata`, JSON_QUOTE(?), '$.user.roles')";
        $this->assertEquals($expected, $result);
    }

    // ==================== JSON Length Tests ====================

    public function testBuildJsonLengthWithoutPath(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'items', null);

        $this->assertEquals("JSON_LENGTH(`items`)", $result);
    }

    public function testBuildJsonLengthWithPath(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '$.tags');

        $this->assertEquals("JSON_LENGTH(`data`, '$.tags')", $result);
    }

    public function testBuildJsonLengthWithNestedPath(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'metadata', '$.user.roles');

        $this->assertEquals("JSON_LENGTH(`metadata`, '$.user.roles')", $result);
    }

    // ==================== Build Ignore Update Tests ====================

    public function testBuildIgnoreUpdate(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildIgnoreUpdate');
        $method->setAccessible(true);

        $result = $method->invoke($builder);

        $this->assertEquals('UPDATE IGNORE', $result);
    }

    // ==================== Dialect Tests ====================

    public function testDialectIsMySQL(): void
    {
        $builder = new UpdateMySql(new BuilderRegistry());
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('dialect');
        $property->setAccessible(true);

        $dialect = $property->getValue($builder);

        $this->assertEquals('mysql', $dialect);
    }
}
