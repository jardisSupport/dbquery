<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Mysql;

use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JardisSupport\DbQuery\DbQuery
 * @covers \JardisSupport\DbQuery\Query\MySqlBuilder
 */

/**
 * MySQL CTE (Common Table Expressions) Tests
 *
 * Tests: WITH, WITH RECURSIVE
 */
/*
 * SQL-Pins am 2026-08-10 an das Auto-Quoting einfacher Identifier angepasst:
 * WHERE/AND/OR-, HAVING-, ORDER-BY-, GROUP-BY-Felder und die SELECT-Feldliste
 * quoten `ident` bzw. `alias.ident` jetzt dialektgerecht (MySQL/SQLite: Backtick,
 * PostgreSQL: Double-Quote). Ausdruecke, '*', bereits Gequotetes und
 * Expression::raw() bleiben byte-identisch roh. Alle Aenderungen in dieser
 * Datei sind reine Quote-Zeichen-Diffs in erwarteten SQL-Strings.
 */
class DbQueryMySqlCteTest extends TestCase
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
            ->sql('mysql', false);

        $expected = "WITH `active_users` AS (SELECT `id`, `name` FROM `users` WHERE `status` = 'active') "
            . "SELECT * FROM `active_users`";

        $this->assertEquals($expected, $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $expectedPrepared = "WITH `active_users` AS (SELECT `id`, `name` FROM `users` WHERE `status` = ?) "
            . "SELECT * FROM `active_users`";
        $this->assertEquals($expectedPrepared, $prepared->sql());
        $this->assertEquals(['active'], $prepared->bindings());
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
            ->sql('mysql', false);

        $expected = "WITH `active_users` AS (SELECT `id`, `name` FROM `users` WHERE `status` = 'active'), "
            . "`user_posts` AS (SELECT `user_id`, COUNT(*) as `post_count` FROM `posts` GROUP BY `user_id`) "
            . "SELECT * FROM `active_users`";

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
            ->sql('mysql', false);

        $expected = "WITH `user_stats` AS (SELECT `user_id`, COUNT(*) as `post_count` FROM `posts` GROUP BY `user_id`) "
            . "SELECT `u`.`name`, `s`.`post_count` FROM `users` `u` INNER JOIN `user_stats` `s` ON u.id = s.user_id";

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
            ->sql('mysql', false);

        $expected = "WITH `user_summary` AS "
            . "(SELECT `u`.`id`, `u`.`name`, COUNT(p.id) as `post_count` FROM `users` `u` "
            . "LEFT JOIN `posts` `p` ON u.id = p.user_id GROUP BY `u`.`id`, `u`.`name`) "
            . "SELECT * FROM `user_summary` WHERE `post_count` > 5";

        $this->assertEquals($expected, $sql);
    }

    public function testWithCteAndMainQueryWhere(): void
    {
        $cte = (new DbQuery())
            ->select('*')
            ->from('users')
            ->where('created_at')->greater('2024-01-01');

        $query = new DbQuery();
        $sql = $query
            ->with('recent_users', $cte)
            ->select('id, name')
            ->from('recent_users')
            ->where('status')->equals('verified')
            ->sql('mysql', false);

        $expected = "WITH `recent_users` AS "
            . "(SELECT * FROM `users` WHERE `created_at` > '2024-01-01') "
            . "SELECT `id`, `name` FROM `recent_users` WHERE `status` = 'verified'";

        $this->assertEquals($expected, $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $expectedPrepared = "WITH `recent_users` AS "
            . "(SELECT * FROM `users` WHERE `created_at` > ?) "
            . "SELECT `id`, `name` FROM `recent_users` WHERE `status` = ?";
        $this->assertEquals($expectedPrepared, $prepared->sql());
        $this->assertEquals(['2024-01-01', 'verified'], $prepared->bindings());
    }

    public function testWithCteAndOrderByLimit(): void
    {
        $cte = (new DbQuery())
            ->select('user_id, SUM(amount) as total')
            ->from('orders')
            ->groupBy('user_id');

        $query = new DbQuery();
        $sql = $query
            ->with('user_totals', $cte)
            ->select('*')
            ->from('user_totals')
            ->orderBy('total', 'DESC')
            ->limit(10)
            ->sql('mysql', false);

        $expected = "WITH `user_totals` AS "
            . "(SELECT `user_id`, SUM(amount) as `total` FROM `orders` GROUP BY `user_id`) "
            . "SELECT * FROM `user_totals` ORDER BY `total` DESC LIMIT 10";

        $this->assertEquals($expected, $sql);
    }

    public function testWithMultipleCtesCascading(): void
    {
        // First CTE
        $firstCte = (new DbQuery())
            ->select('id, name, status')
            ->from('users')
            ->where('active')->equals(1);

        // Second CTE references first CTE
        $secondCte = (new DbQuery())
            ->select('id, name')
            ->from('active_users')
            ->where('status')->equals('verified');

        $query = new DbQuery();
        $sql = $query
            ->with('active_users', $firstCte)
            ->with('verified_users', $secondCte)
            ->select('*')
            ->from('verified_users')
            ->sql('mysql', false);

        $expected = "WITH `active_users` AS (SELECT `id`, `name`, `status` FROM `users` WHERE `active` = 1), "
            . "`verified_users` AS (SELECT `id`, `name` FROM `active_users` WHERE `status` = 'verified') "
            . "SELECT * FROM `verified_users`";

        $this->assertEquals($expected, $sql);
    }

    public function testWithCteAndMultipleJoins(): void
    {
        $usersCte = (new DbQuery())
            ->select('id, name')
            ->from('users')
            ->where('active')->equals(1);

        $postsCte = (new DbQuery())
            ->select('user_id, COUNT(*) as count')
            ->from('posts')
            ->groupBy('user_id');

        $query = new DbQuery();
        $sql = $query
            ->with('active_users', $usersCte)
            ->with('post_counts', $postsCte)
            ->select('u.name, p.count')
            ->from('active_users', 'u')
            ->innerJoin('post_counts', 'u.id = p.user_id', 'p')
            ->sql('mysql', false);

        $expected = "WITH `active_users` AS (SELECT `id`, `name` FROM `users` WHERE `active` = 1), "
            . "`post_counts` AS (SELECT `user_id`, COUNT(*) as `count` FROM `posts` GROUP BY `user_id`) "
            . "SELECT `u`.`name`, `p`.`count` FROM `active_users` `u` INNER JOIN `post_counts` `p` ON u.id = p.user_id";

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

    public function testWithCteContainingSubquery(): void
    {
        $subquery = (new DbQuery())
            ->select('COUNT(*)')
            ->from('posts', 'p')
            ->where('p.user_id')->equals(Expression::raw('u.id'));

        $cte = (new DbQuery())
            ->select('id, name')
            ->selectSubquery($subquery, 'post_count')
            ->from('users', 'u');

        $query = new DbQuery();
        $sql = $query
            ->with('user_stats', $cte)
            ->select('*')
            ->from('user_stats')
            ->where('post_count')->greater(10)
            ->sql('mysql', false);

        $this->assertStringContainsString('WITH `user_stats` AS', $sql);
        $this->assertStringContainsString('SELECT `id`, `name`,', $sql);
        $this->assertStringContainsString('SELECT COUNT(*)', $sql);
        $this->assertStringContainsString('AS `post_count`', $sql);
        $this->assertStringContainsString('FROM `user_stats` WHERE `post_count` > 10', $sql);
    }

    // ==================== WITH RECURSIVE Tests ====================

    public function testWithRecursiveSingleCte(): void
    {
        // Simple recursive CTE for hierarchical data
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
            ->sql('mysql', false);

        $this->assertStringStartsWith('WITH RECURSIVE `category_tree` AS', $sql);
        $this->assertStringContainsString('SELECT `id`, `name`, `parent_id`, 1 as `level`', $sql);
        $this->assertStringContainsString('WHERE `parent_id` IS NULL', $sql);
        $this->assertStringContainsString('UNION', $sql);
        $this->assertStringContainsString('SELECT * FROM `category_tree`', $sql);
    }

    public function testWithRecursiveEmployeeHierarchy(): void
    {
        // Classic recursive query: employee hierarchy
        $recursiveCte = (new DbQuery())
            ->select('id, name, manager_id, 0 as depth')
            ->from('employees')
            ->where('manager_id')->isNull()
            ->union(
                (new DbQuery())
                    ->select('e.id, e.name, e.manager_id, h.depth + 1')
                    ->from('employees', 'e')
                    ->innerJoin('hierarchy', 'e.manager_id = h.id', 'h')
            );

        $query = new DbQuery();
        $sql = $query
            ->withRecursive('hierarchy', $recursiveCte)
            ->select('*')
            ->from('hierarchy')
            ->orderBy('depth')
            ->sql('mysql', false);

        $this->assertStringContainsString('WITH RECURSIVE `hierarchy` AS', $sql);
        $this->assertStringContainsString('0 as `depth`', $sql);
        $this->assertStringContainsString('h.depth + 1', $sql);
        $this->assertStringContainsString('ORDER BY `depth` ASC', $sql);
    }

    public function testWithRecursiveMixedWithNormalCte(): void
    {
        // Normal CTE
        $normalCte = (new DbQuery())
            ->select('id, name')
            ->from('users')
            ->where('active')->equals(1);

        // Recursive CTE
        $recursiveCte = (new DbQuery())
            ->select('id, parent_id, 1 as level')
            ->from('categories')
            ->where('parent_id')->isNull()
            ->union(
                (new DbQuery())
                    ->select('c.id, c.parent_id, t.level + 1')
                    ->from('categories', 'c')
                    ->innerJoin('cat_tree', 't.id = c.parent_id', 't')
            );

        $query = new DbQuery();
        $sql = $query
            ->with('active_users', $normalCte)
            ->withRecursive('cat_tree', $recursiveCte)
            ->select('*')
            ->from('cat_tree')
            ->sql('mysql', false);

        $this->assertStringContainsString('WITH RECURSIVE `active_users` AS', $sql);
        $this->assertStringContainsString('`cat_tree` AS', $sql);
        $this->assertStringContainsString('FROM `cat_tree`', $sql);
    }

    public function testWithRecursiveAndWhere(): void
    {
        $recursiveCte = (new DbQuery())
            ->select('id, name, parent_id, 1 as depth')
            ->from('nodes')
            ->where('parent_id')->isNull()
            ->union(
                (new DbQuery())
                    ->select('n.id, n.name, n.parent_id, t.depth + 1')
                    ->from('nodes', 'n')
                    ->innerJoin('node_tree', 'n.parent_id = t.id', 't')
            );

        $query = new DbQuery();
        $sql = $query
            ->withRecursive('node_tree', $recursiveCte)
            ->select('*')
            ->from('node_tree')
            ->where('depth')->lowerEquals(3)
            ->sql('mysql', false);

        $this->assertStringContainsString('WITH RECURSIVE `node_tree` AS', $sql);
        $this->assertStringContainsString('FROM `node_tree` WHERE `depth` <= 3', $sql);
    }

    public function testWithRecursiveAndLimit(): void
    {
        $recursiveCte = (new DbQuery())
            ->select('id, parent_id, name')
            ->from('items')
            ->where('parent_id')->equals(0)
            ->union(
                (new DbQuery())
                    ->select('i.id, i.parent_id, i.name')
                    ->from('items', 'i')
                    ->innerJoin('item_tree', 'i.parent_id = t.id', 't')
            );

        $query = new DbQuery();
        $sql = $query
            ->withRecursive('item_tree', $recursiveCte)
            ->select('*')
            ->from('item_tree')
            ->limit(100)
            ->sql('mysql', false);

        $this->assertStringContainsString('WITH RECURSIVE `item_tree` AS', $sql);
        $this->assertStringContainsString('LIMIT 100', $sql);
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

    public function testWithRecursivePathTracking(): void
    {
        // Track full path in recursive query
        $recursiveCte = (new DbQuery())
            ->select("id, name, parent_id, CAST(name AS CHAR(1000)) as path")
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
            ->sql('mysql', false);

        $this->assertStringContainsString('WITH RECURSIVE `folder_paths` AS', $sql);
        $this->assertStringContainsString('CAST(name AS CHAR(1000)) as `path`', $sql);
        $this->assertStringContainsString("CONCAT(p.path, '/', f.name)", $sql);
    }

    public function testWithRecursiveMultipleRecursiveCtes(): void
    {
        // First recursive CTE
        $firstRecursive = (new DbQuery())
            ->select('id, parent_id')
            ->from('tree_a')
            ->where('parent_id')->isNull()
            ->union(
                (new DbQuery())
                    ->select('t.id, t.parent_id')
                    ->from('tree_a', 't')
                    ->innerJoin('tree_a_rec', 't.parent_id = r.id', 'r')
            );

        // Second recursive CTE
        $secondRecursive = (new DbQuery())
            ->select('id, parent_id')
            ->from('tree_b')
            ->where('parent_id')->isNull()
            ->union(
                (new DbQuery())
                    ->select('t.id, t.parent_id')
                    ->from('tree_b', 't')
                    ->innerJoin('tree_b_rec', 't.parent_id = r.id', 'r')
            );

        $query = new DbQuery();
        $sql = $query
            ->withRecursive('tree_a_rec', $firstRecursive)
            ->withRecursive('tree_b_rec', $secondRecursive)
            ->select('*')
            ->from('tree_a_rec')
            ->sql('mysql', false);

        $this->assertStringContainsString('WITH RECURSIVE `tree_a_rec` AS', $sql);
        $this->assertStringContainsString('`tree_b_rec` AS', $sql);
    }

    public function testWithRecursiveWithJoinInMainQuery(): void
    {
        $recursiveCte = (new DbQuery())
            ->select('id, parent_id, name')
            ->from('categories')
            ->where('parent_id')->isNull()
            ->union(
                (new DbQuery())
                    ->select('c.id, c.parent_id, c.name')
                    ->from('categories', 'c')
                    ->innerJoin('cat_hierarchy', 'c.parent_id = h.id', 'h')
            );

        $query = new DbQuery();
        $sql = $query
            ->withRecursive('cat_hierarchy', $recursiveCte)
            ->select('h.*, p.title')
            ->from('cat_hierarchy', 'h')
            ->innerJoin('products', 'p.category_id = h.id', 'p')
            ->sql('mysql', false);

        $this->assertStringContainsString('WITH RECURSIVE `cat_hierarchy` AS', $sql);
        $this->assertStringContainsString('FROM `cat_hierarchy` `h`', $sql);
        $this->assertStringContainsString('INNER JOIN `products` `p`', $sql);
    }

    public function testWithRecursiveComplexHierarchyWithDepthLimit(): void
    {
        // More realistic example with depth limiting in WHERE
        $recursiveCte = (new DbQuery())
            ->select('id, parent_id, name, 1 as level, CAST(id AS CHAR(200)) as path')
            ->from('org_units')
            ->where('parent_id')->isNull()
            ->union(
                (new DbQuery())
                    ->select("o.id, o.parent_id, o.name, h.level + 1, CONCAT(h.path, ',', o.id)")
                    ->from('org_units', 'o')
                    ->innerJoin('org_hierarchy', 'o.parent_id = h.id', 'h')
                    ->where('h.level')->lower(5) // Limit recursion depth
            );

        $query = new DbQuery();
        $sql = $query
            ->withRecursive('org_hierarchy', $recursiveCte)
            ->select('*')
            ->from('org_hierarchy')
            ->orderBy('level')
            ->orderBy('name')
            ->sql('mysql', false);

        $this->assertStringContainsString('WITH RECURSIVE `org_hierarchy` AS', $sql);
        $this->assertStringContainsString('1 as `level`', $sql);
        $this->assertStringContainsString('h.level + 1', $sql);
        $this->assertStringContainsString('WHERE `h`.`level` < 5', $sql);
        $this->assertStringContainsString('ORDER BY `level` ASC, `name` ASC', $sql);
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

        $result = $query->sql('mysql', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertStringContainsString('WITH RECURSIVE', $result->sql());
        $this->assertStringContainsString('`emp_hierarchy` AS', $result->sql());
        $this->assertStringContainsString('SELECT * FROM `departments`', $result->sql());
        $this->assertStringContainsString('WHERE `id` = ?', $result->sql());

        // Verify binding order: CTE bindings first, then main query bindings
        $bindings = $result->bindings();
        $this->assertCount(2, $bindings);
        $this->assertSame(1, $bindings[0], 'First binding should be from CTE (manager_id = 1)');
        $this->assertSame(100, $bindings[1], 'Second binding should be from main query (id = 100)');
    }
}
