<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Postgres;

use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * PostgreSQL Basic Tests
 *
 * Tests: Constructor, SELECT, FROM, DISTINCT, Alias
 *
 * PostgreSQL uses " (double quotes) for identifiers
 */
class DbQueryPostgresBasicTest extends TestCase
{
    public function testConstructor(): void
    {
        $query = new DbQuery();
        $this->assertInstanceOf(DbQueryBuilderInterface::class, $query);
    }

    public function testSelectWithoutParameters(): void
    {
        $query = new DbQuery();
        $result = $query->select();
        $this->assertSame($query, $result);

        $sql = $query->from('users')->sql('postgres', false);
        $this->assertEquals('SELECT * FROM "users"', $sql);
    }

    public function testSelectWithSingleField(): void
    {
        $query = new DbQuery();
        $sql = $query->select('id')->from('users')->sql('postgres', false);
        $this->assertEquals('SELECT id FROM "users"', $sql);
    }

    public function testSelectWithMultipleFields(): void
    {
        $query = new DbQuery();
        $sql = $query->select('id, name, email')->from('users')->sql('postgres', false);
        $this->assertEquals('SELECT id, name, email FROM "users"', $sql);
    }

    public function testDistinct(): void
    {
        $query = new DbQuery();
        $result = $query->distinct(true);
        $this->assertSame($query, $result);

        $sql = $query->select('name')->from('users')->sql('postgres', false);
        $this->assertEquals('SELECT DISTINCT name FROM "users"', $sql);
    }

    public function testFromWithTableName(): void
    {
        $query = new DbQuery();
        $result = $query->from('users');
        $this->assertSame($query, $result);

        $sql = $query->select('*')->sql('postgres', false);
        $this->assertEquals('SELECT * FROM "users"', $sql);
    }

    public function testFromWithAlias(): void
    {
        $query = new DbQuery();
        $sql = $query->select('*')->from('users', 'u')->sql('postgres', false);
        $this->assertEquals('SELECT * FROM "users" "u"', $sql);
    }
}
