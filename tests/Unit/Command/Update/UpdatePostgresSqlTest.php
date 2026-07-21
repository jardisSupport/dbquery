<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Command\Update;

use JardisSupport\DbQuery\command\update\UpdatePostgresSql;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for UpdatePostgresSql
 *
 * Tests PostgreSQL-specific UPDATE SQL generation.
 */
class UpdatePostgresSqlTest extends TestCase
{
    // ==================== Quote Identifier Tests ====================

    public function testQuoteIdentifierWithSimpleName(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('quoteIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'column_name');

        $this->assertEquals('"column_name"', $result);
    }

    public function testQuoteIdentifierEscapesDoubleQuotes(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('quoteIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'column"name');

        $this->assertEquals('"column""name"', $result);
    }

    public function testQuoteIdentifierWithMultipleDoubleQuotes(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('quoteIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'col""umn');

        $this->assertEquals('"col""""umn"', $result);
    }

    public function testQuoteIdentifierWithTableAndColumn(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('quoteIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'users.id');

        $this->assertEquals('"users.id"', $result);
    }

    // ==================== Format Boolean Tests ====================

    public function testFormatBooleanTrue(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('formatBoolean');
        $method->setAccessible(true);

        $result = $method->invoke($builder, true);

        $this->assertEquals('TRUE', $result);
    }

    public function testFormatBooleanFalse(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('formatBoolean');
        $method->setAccessible(true);

        $result = $method->invoke($builder, false);

        $this->assertEquals('FALSE', $result);
    }

    // ==================== JSON Extract Tests ====================

    public function testBuildJsonExtractSimplePath(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '$.age');

        $this->assertEquals('"data"->>\'age\'', $result);
    }

    public function testBuildJsonExtractNestedPath(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'metadata', '$.user.name');

        $this->assertEquals('"metadata"->\'user\'->>\'name\'', $result);
    }

    public function testBuildJsonExtractPathWithoutDollar(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', 'age');

        $this->assertEquals('"data"->>\'age\'', $result);
    }

    public function testBuildJsonExtractDeepNestedPath(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '$.user.profile.bio');

        $this->assertEquals('"data"->\'user\'->\'profile\'->>\'bio\'', $result);
    }

    // ==================== JSON Contains Tests ====================

    public function testBuildJsonContainsWithoutPath(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'tags', '?', null);

        $this->assertEquals('"tags" @> to_jsonb(?::text)', $result);
    }

    public function testBuildJsonContainsWithPath(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '?', '$.items');

        $expected = '"data"->\'items\' @> to_jsonb(?::text)';
        $this->assertEquals($expected, $result);
    }

    public function testBuildJsonContainsWithPathNoDollar(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '?', 'items');

        $expected = '"data"->\'items\' @> to_jsonb(?::text)';
        $this->assertEquals($expected, $result);
    }

    // ==================== JSON Not Contains Tests ====================

    public function testBuildJsonNotContainsWithoutPath(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonNotContains');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'tags', '?', null);

        $this->assertEquals('NOT ("tags" @> to_jsonb(?::text))', $result);
    }

    public function testBuildJsonNotContainsWithPath(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonNotContains');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '?', '$.items');

        $expected = 'NOT ("data"->\'items\' @> to_jsonb(?::text))';
        $this->assertEquals($expected, $result);
    }

    public function testBuildJsonNotContainsWithPathNoDollar(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonNotContains');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '?', 'items');

        $expected = 'NOT ("data"->\'items\' @> to_jsonb(?::text))';
        $this->assertEquals($expected, $result);
    }

    // ==================== JSON Length Tests ====================

    public function testBuildJsonLengthWithoutPath(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'items', null);

        $this->assertEquals('jsonb_array_length("items")', $result);
    }

    public function testBuildJsonLengthWithPath(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '$.tags');

        $this->assertEquals('jsonb_array_length("data"->\'tags\')', $result);
    }

    public function testBuildJsonLengthWithPathNoDollar(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', 'tags');

        $this->assertEquals('jsonb_array_length("data"->\'tags\')', $result);
    }

    public function testBuildJsonLengthWithNestedPath(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'metadata', '$.user.roles');

        $this->assertEquals('jsonb_array_length("metadata"->\'user.roles\')', $result);
    }

    // ==================== Dialect Tests ====================

    public function testDialectIsPostgres(): void
    {
        $builder = new UpdatePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('dialect');
        $property->setAccessible(true);

        $dialect = $property->getValue($builder);

        $this->assertEquals('postgres', $dialect);
    }
}
