<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Postgres;

use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisSupport\DbQuery\DbQuery
 * @covers \JardisSupport\DbQuery\Query\PostgresBuilder
 */

/**
 * PostgreSQL CTE (Common Table Expressions) Tests
 *
 * Tests: WITH, WITH RECURSIVE
 * PostgreSQL uses VARCHAR instead of CHAR for CAST
 */
class DbQueryPostgresCteTest extends TestCase
{
    public function testWithSingleCte(): void
    {
        $cte = (new DbQuery())
            ->select('id, name')
            ->from('users')
            ->where('status')->equals('active');

        $query = new DbQuery();
        $sql = $query
            ->with('active_users', $cte)
            ->select('*')
            ->from('active_users')
            ->sql('postgres', false);

        $expected = "WITH \"active_users\" AS (SELECT id, name FROM \"users\" WHERE status = 'active') "
            . "SELECT * FROM \"active_users\"";

        $this->assertEquals($expected, $sql);
    }

    public function testWithMultipleCtes(): void
    {
        $activeCte = (new DbQuery())
            ->select('id, name')
            ->from('users')
            ->where('status')->equals('active');

        $postsCte = (new DbQuery())
            ->select('user_id, COUNT(*) as post_count')
            ->from('posts')
            ->groupBy('user_id');

        $query = new DbQuery();
        $sql = $query
            ->with('active_users', $activeCte)
            ->with('user_posts', $postsCte)
            ->select('*')
            ->from('active_users')
            ->sql('postgres', false);

        $expected = "WITH \"active_users\" AS (SELECT id, name FROM \"users\" WHERE status = 'active'), "
            . "\"user_posts\" AS (SELECT user_id, COUNT(*) as post_count FROM \"posts\" GROUP BY user_id) "
            . "SELECT * FROM \"active_users\"";

        $this->assertEquals($expected, $sql);
    }

    public function testWithCteUsedInJoin(): void
    {
        $cte = (new DbQuery())
            ->select('user_id, COUNT(*) as post_count')
            ->from('posts')
            ->groupBy('user_id');

        $query = new DbQuery();
        $sql = $query
            ->with('user_stats', $cte)
            ->select('u.name, s.post_count')
            ->from('users', 'u')
            ->innerJoin('user_stats', 'u.id = s.user_id', 's')
            ->sql('postgres', false);

        $expected = "WITH \"user_stats\" AS (SELECT user_id, COUNT(*) as post_count FROM \"posts\" GROUP BY user_id) "
            . "SELECT u.name, s.post_count FROM \"users\" \"u\" INNER JOIN \"user_stats\" \"s\" ON u.id = s.user_id";

        $this->assertEquals($expected, $sql);
    }

    public function testWithCteContainingJoin(): void
    {
        $cte = (new DbQuery())
            ->select('u.id, u.name, COUNT(p.id) as post_count')
            ->from('users', 'u')
            ->leftJoin('posts', 'u.id = p.user_id', 'p')
            ->groupBy('u.id', 'u.name');

        $query = new DbQuery();
        $sql = $query
            ->with('user_summary', $cte)
            ->select('*')
            ->from('user_summary')
            ->where('post_count')->greater(5)
            ->sql('postgres', false);

        $expected = "WITH \"user_summary\" AS "
            . "(SELECT u.id, u.name, COUNT(p.id) as post_count FROM \"users\" \"u\" "
            . "LEFT JOIN \"posts\" \"p\" ON u.id = p.user_id GROUP BY u.id, u.name) "
            . "SELECT * FROM \"user_summary\" WHERE post_count > 5";

        $this->assertEquals($expected, $sql);
    }

    public function testWithReturnsQueryBuilder(): void
    {
        $cte = (new DbQuery())
            ->select('*')
            ->from('users');

        $query = new DbQuery();
        $result = $query->with('cte_name', $cte);

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertSame($query, $result);
    }

    public function testWithRecursiveSingleCte(): void
    {
        $recursiveCte = (new DbQuery())
            ->select('id, name, parent_id, 1 as level')
            ->from('categories')
            ->where('parent_id')->isNull()
            ->union(
                (new DbQuery())
                    ->select('c.id, c.name, c.parent_id, r.level + 1')
                    ->from('categories', 'c')
                    ->innerJoin('category_tree', 'c.parent_id = r.id', 'r')
            );

        $query = new DbQuery();
        $sql = $query
            ->withRecursive('category_tree', $recursiveCte)
            ->select('*')
            ->from('category_tree')
            ->sql('postgres', false);

        $this->assertStringStartsWith('WITH RECURSIVE "category_tree" AS', $sql);
        $this->assertStringContainsString('SELECT id, name, parent_id, 1 as level', $sql);
        $this->assertStringContainsString('WHERE parent_id IS NULL', $sql);
        $this->assertStringContainsString('UNION', $sql);
        $this->assertStringContainsString('SELECT * FROM "category_tree"', $sql);
    }

    public function testWithRecursivePathTracking(): void
    {
        $recursiveCte = (new DbQuery())
            ->select("id, name, parent_id, CAST(name AS VARCHAR(1000)) as path")
            ->from('folders')
            ->where('parent_id')->isNull()
            ->union(
                (new DbQuery())
                    ->select("f.id, f.name, f.parent_id, CONCAT(p.path, '/', f.name)")
                    ->from('folders', 'f')
                    ->innerJoin('folder_paths', 'f.parent_id = p.id', 'p')
            );

        $query = new DbQuery();
        $sql = $query
            ->withRecursive('folder_paths', $recursiveCte)
            ->select('id, name, path')
            ->from('folder_paths')
            ->sql('postgres', false);

        $this->assertStringContainsString('WITH RECURSIVE "folder_paths" AS', $sql);
        $this->assertStringContainsString('CAST(name AS VARCHAR(1000)) as path', $sql);
        $this->assertStringContainsString("CONCAT(p.path, '/', f.name)", $sql);
    }

    public function testWithRecursiveReturnsQueryBuilder(): void
    {
        $recursiveCte = (new DbQuery())
            ->select('id')
            ->from('table');

        $query = new DbQuery();
        $result = $query->withRecursive('rec_cte', $recursiveCte);

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertSame($query, $result);
    }

    public function testRecursiveCteWithBindingsInPreparedMode(): void
    {
        // Create subquery for recursive CTE with bindings
        $subQuery = (new DbQuery())
            ->select('id, name, manager_id')
            ->from('employees')
            ->where('manager_id')->equals(1);

        // Main query with recursive CTE and additional WHERE condition
        $query = (new DbQuery())
            ->select('*')
            ->from('departments')
            ->withRecursive('emp_hierarchy', $subQuery)
            ->where('id')->equals(100);

        $result = $query->sql('postgres', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertStringContainsString('WITH RECURSIVE', $result->sql());
        $this->assertStringContainsString('"emp_hierarchy" AS', $result->sql());
        $this->assertStringContainsString('SELECT * FROM "departments"', $result->sql());
        $this->assertStringContainsString('WHERE id = ?', $result->sql());

        // Verify binding order: CTE bindings first, then main query bindings
        $bindings = $result->bindings();
        $this->assertCount(2, $bindings);
        $this->assertSame(1, $bindings[0], 'First binding should be from CTE (manager_id = 1)');
        $this->assertSame(100, $bindings[1], 'Second binding should be from main query (id = 100)');
    }
}
