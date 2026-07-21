<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Command\Delete;

use JardisSupport\DbQuery\command\Delete\DeletePostgresSql;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for DeletePostgresSql
 *
 * Tests PostgreSQL-specific DELETE SQL generation.
 */
class DeletePostgresSqlTest extends TestCase
{
    // ==================== Quote Identifier Tests ====================

    public function testQuoteIdentifierWithSimpleName(): void
    {
        $builder = new DeletePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('quoteIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'users');

        $this->assertEquals('"users"', $result);
    }

    public function testQuoteIdentifierEscapesDoubleQuotes(): void
    {
        $builder = new DeletePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('quoteIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'table"name');

        $this->assertEquals('"table""name"', $result);
    }

    public function testQuoteIdentifierWithMultipleDoubleQuotes(): void
    {
        $builder = new DeletePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('quoteIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'tab""le');

        $this->assertEquals('"tab""""le"', $result);
    }

    // ==================== JSON Extract Tests ====================

    public function testBuildJsonExtractSimplePath(): void
    {
        $builder = new DeletePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '$.status');

        $this->assertEquals('"data"->>\'status\'', $result);
    }

    public function testBuildJsonExtractNestedPath(): void
    {
        $builder = new DeletePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'metadata', '$.user.id');

        $this->assertEquals('"metadata"->\'user\'->>\'id\'', $result);
    }

    public function testBuildJsonExtractPathWithoutDollar(): void
    {
        $builder = new DeletePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', 'status');

        $this->assertEquals('"data"->>\'status\'', $result);
    }

    public function testBuildJsonExtractDeepNestedPath(): void
    {
        $builder = new DeletePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonExtract');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '$.user.profile.bio');

        $this->assertEquals('"data"->\'user\'->\'profile\'->>\'bio\'', $result);
    }

    // ==================== JSON Contains Tests ====================

    public function testBuildJsonContainsWithoutPath(): void
    {
        $builder = new DeletePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'tags', '?', null);

        $this->assertEquals('"tags" @> to_jsonb(?::text)', $result);
    }

    public function testBuildJsonContainsWithPath(): void
    {
        $builder = new DeletePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '?', '$.items');

        $expected = '"data"->\'items\' @> to_jsonb(?::text)';
        $this->assertEquals($expected, $result);
    }

    public function testBuildJsonContainsWithPathNoDollar(): void
    {
        $builder = new DeletePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonContains');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '?', 'items');

        $expected = '"data"->\'items\' @> to_jsonb(?::text)';
        $this->assertEquals($expected, $result);
    }

    // ==================== JSON Length Tests ====================

    public function testBuildJsonLengthWithoutPath(): void
    {
        $builder = new DeletePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'items', null);

        $this->assertEquals('jsonb_array_length("items")', $result);
    }

    public function testBuildJsonLengthWithPath(): void
    {
        $builder = new DeletePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', '$.tags');

        $this->assertEquals('jsonb_array_length("data"->\'tags\')', $result);
    }

    public function testBuildJsonLengthWithPathNoDollar(): void
    {
        $builder = new DeletePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'data', 'tags');

        $this->assertEquals('jsonb_array_length("data"->\'tags\')', $result);
    }

    public function testBuildJsonLengthWithNestedPath(): void
    {
        $builder = new DeletePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('buildJsonLength');
        $method->setAccessible(true);

        $result = $method->invoke($builder, 'metadata', '$.user.roles');

        $this->assertEquals('jsonb_array_length("metadata"->\'user.roles\')', $result);
    }

    // ==================== Dialect Tests ====================

    public function testDialectIsPostgres(): void
    {
        $builder = new DeletePostgresSql(new BuilderRegistry('postgres', '14'));
        $reflection = new \ReflectionClass($builder);
        $property = $reflection->getProperty('dialect');
        $property->setAccessible(true);

        $dialect = $property->getValue($builder);

        $this->assertEquals('postgres', $dialect);
    }
}
