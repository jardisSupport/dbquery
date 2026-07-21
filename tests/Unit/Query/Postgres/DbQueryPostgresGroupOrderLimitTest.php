<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Postgres;

use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use PHPUnit\Framework\TestCase;

/**
 * PostgreSQL GROUP BY, ORDER BY, LIMIT Tests
 *
 * Tests: GROUP BY, HAVING, ORDER BY, LIMIT, OFFSET
 */
class DbQueryPostgresGroupOrderLimitTest extends TestCase
{
    public function testGroupBySingleColumn(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('status, COUNT(*) as count')
            ->from('users')
            ->groupBy('status')
            ->sql('postgres', false);

        $this->assertEquals('SELECT status, COUNT(*) as count FROM "users" GROUP BY status', $sql);
    }

    public function testGroupByMultipleColumns(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('status, role, COUNT(*)')
            ->from('users')
            ->groupBy('status', 'role')
            ->sql('postgres', false);

        $this->assertEquals('SELECT status, role, COUNT(*) FROM "users" GROUP BY status, role', $sql);
    }

    public function testHavingWithCount(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('status, COUNT(*) as count')
            ->from('users')
            ->groupBy('status')
            ->having('COUNT(*)')->greater(5)
            ->sql('postgres', false);

        $this->assertEquals('SELECT status, COUNT(*) as count FROM "users" GROUP BY status HAVING COUNT(*) > 5', $sql);

        $prepared = $query->sql('postgres', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT status, COUNT(*) as count FROM "users" GROUP BY status HAVING COUNT(*) > ?', $prepared->sql());
        $this->assertEquals([5], $prepared->bindings());
    }

    public function testHavingWithMultipleConditions(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('status, COUNT(*) as count')
            ->from('users')
            ->groupBy('status')
            ->having('COUNT(*)')->greater(5)
            ->and('COUNT(*)')->lower(100)
            ->sql('postgres', false);

        $this->assertEquals('SELECT status, COUNT(*) as count FROM "users" GROUP BY status HAVING COUNT(*) > 5 AND COUNT(*) < 100', $sql);

        $prepared = $query->sql('postgres', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT status, COUNT(*) as count FROM "users" GROUP BY status HAVING COUNT(*) > ? AND COUNT(*) < ?', $prepared->sql());
        $this->assertEquals([5, 100], $prepared->bindings());
    }

    public function testOrderBySingleColumn(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->orderBy('name')
            ->sql('postgres', false);

        $this->assertEquals('SELECT * FROM "users" ORDER BY name ASC', $sql);
    }

    public function testOrderByDescending(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->orderBy('created_at', 'DESC')
            ->sql('postgres', false);

        $this->assertEquals('SELECT * FROM "users" ORDER BY created_at DESC', $sql);
    }

    public function testOrderByMultipleColumns(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->orderBy('status', 'ASC')
            ->orderBy('name', 'DESC')
            ->sql('postgres', false);

        $this->assertEquals('SELECT * FROM "users" ORDER BY status ASC, name DESC', $sql);
    }

    public function testLimitOnly(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->limit(10)
            ->sql('postgres', false);

        $this->assertEquals('SELECT * FROM "users" LIMIT 10', $sql);
    }

    public function testLimitWithOffset(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->limit(10, 20)
            ->sql('postgres', false);

        $this->assertEquals('SELECT * FROM "users" LIMIT 10 OFFSET 20', $sql);
    }

    public function testCompleteQueryWithAllClauses(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('u.id, u.name, COUNT(p.id) as post_count')
            ->from('users', 'u')
            ->leftJoin('posts', 'u.id = p.user_id', 'p')
            ->where('u.status')->equals('active')
            ->groupBy('u.id', 'u.name')
            ->having('COUNT(p.id)')->greater(5)
            ->orderBy('post_count', 'DESC')
            ->limit(10)
            ->sql('postgres', false);

        $expected = "SELECT u.id, u.name, COUNT(p.id) as post_count FROM \"users\" \"u\" "
            . "LEFT JOIN \"posts\" \"p\" ON u.id = p.user_id "
            . "WHERE u.status = 'active' "
            . "GROUP BY u.id, u.name "
            . "HAVING COUNT(p.id) > 5 "
            . "ORDER BY post_count DESC "
            . "LIMIT 10";

        $this->assertEquals($expected, $sql);

        $prepared = $query->sql('postgres', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $expectedPrepared = "SELECT u.id, u.name, COUNT(p.id) as post_count FROM \"users\" \"u\" "
            . "LEFT JOIN \"posts\" \"p\" ON u.id = p.user_id "
            . "WHERE u.status = ? "
            . "GROUP BY u.id, u.name "
            . "HAVING COUNT(p.id) > ? "
            . "ORDER BY post_count DESC "
            . "LIMIT 10";
        $this->assertEquals($expectedPrepared, $prepared->sql());
        $this->assertEquals(['active', 5], $prepared->bindings());
    }
}
