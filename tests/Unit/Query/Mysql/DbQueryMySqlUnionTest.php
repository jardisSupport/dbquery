<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Mysql;

use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * MySQL UNION Tests
 *
 * Tests: UNION, UNION ALL
 */
class DbQueryMySqlUnionTest extends TestCase
{
    public function testUnionSimple(): void
    {
        $secondQuery = (new DbQuery())
            ->select('id, name')
            ->from('suppliers')
            ->where('country')->equals('Germany');

        $query = new DbQuery();
        $sql = $query
            ->select('id, name')
            ->from('customers')
            ->where('country')->equals('Germany')
            ->union($secondQuery)
            ->sql('mysql', false);

        $expected = "SELECT id, name FROM `customers` WHERE country = 'Germany' "
            . "UNION SELECT id, name FROM `suppliers` WHERE country = 'Germany'";

        $this->assertEquals($expected, $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $expectedPrepared = "SELECT id, name FROM `customers` WHERE country = ? "
            . "UNION SELECT id, name FROM `suppliers` WHERE country = ?";
        $this->assertEquals($expectedPrepared, $prepared->sql());
        $this->assertEquals(['Germany', 'Germany'], $prepared->bindings());

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $expectedPrepared = "SELECT id, name FROM `customers` WHERE country = ? "
            . "UNION SELECT id, name FROM `suppliers` WHERE country = ?";
        $this->assertEquals($expectedPrepared, $prepared->sql());
        $this->assertEquals(['Germany', 'Germany'], $prepared->bindings());
    }

    public function testUnionAllSimple(): void
    {
        $secondQuery = (new DbQuery())
            ->select('name')
            ->from('archived_users');

        $query = new DbQuery();
        $sql = $query
            ->select('name')
            ->from('active_users')
            ->unionAll($secondQuery)
            ->sql('mysql', false);

        $expected = "SELECT name FROM `active_users` UNION ALL SELECT name FROM `archived_users`";

        $this->assertEquals($expected, $sql);
    }

    public function testUnionMultipleQueries(): void
    {
        $secondQuery = (new DbQuery())
            ->select('email')
            ->from('suppliers');

        $thirdQuery = (new DbQuery())
            ->select('email')
            ->from('partners');

        $query = new DbQuery();
        $sql = $query
            ->select('email')
            ->from('customers')
            ->union($secondQuery)
            ->union($thirdQuery)
            ->sql('mysql', false);

        $expected = "SELECT email FROM `customers` "
            . "UNION SELECT email FROM `suppliers` "
            . "UNION SELECT email FROM `partners`";

        $this->assertEquals($expected, $sql);
    }

    public function testUnionAllMultipleQueries(): void
    {
        $q2 = (new DbQuery())
            ->select('id, type')
            ->from('orders')
            ->where('status')->equals('completed');

        $q3 = (new DbQuery())
            ->select('id, type')
            ->from('invoices');

        $query = new DbQuery();
        $sql = $query
            ->select('id, type')
            ->from('quotes')
            ->unionAll($q2)
            ->unionAll($q3)
            ->sql('mysql', false);

        $expected = "SELECT id, type FROM `quotes` "
            . "UNION ALL SELECT id, type FROM `orders` WHERE status = 'completed' "
            . "UNION ALL SELECT id, type FROM `invoices`";

        $this->assertEquals($expected, $sql);
    }

    public function testUnionMixedWithUnionAll(): void
    {
        $q2 = (new DbQuery())
            ->select('name')
            ->from('table2');

        $q3 = (new DbQuery())
            ->select('name')
            ->from('table3');

        $query = new DbQuery();
        $sql = $query
            ->select('name')
            ->from('table1')
            ->union($q2)
            ->unionAll($q3)
            ->sql('mysql', false);

        $expected = "SELECT name FROM `table1` "
            . "UNION SELECT name FROM `table2` "
            . "UNION ALL SELECT name FROM `table3`";

        $this->assertEquals($expected, $sql);
    }

    public function testUnionWithOrderBy(): void
    {
        $secondQuery = (new DbQuery())
            ->select('name, created_at')
            ->from('archived_posts');

        $query = new DbQuery();
        $sql = $query
            ->select('name, created_at')
            ->from('active_posts')
            ->union($secondQuery)
            ->orderBy('created_at', 'DESC')
            ->sql('mysql', false);

        // Verify components are present in correct order
        $this->assertStringContainsString('SELECT name, created_at FROM `active_posts`', $sql);
        $this->assertStringContainsString('UNION', $sql);
        $this->assertStringContainsString('SELECT name, created_at FROM `archived_posts`', $sql);
        $this->assertStringContainsString('ORDER BY created_at DESC', $sql);

        // Verify ORDER BY comes after UNION
        $unionPos = strpos($sql, 'UNION');
        $orderByPos = strpos($sql, 'ORDER BY');
        $this->assertLessThan($orderByPos, $unionPos, 'ORDER BY should come after UNION');
    }

    public function testUnionWithLimit(): void
    {
        $secondQuery = (new DbQuery())
            ->select('title')
            ->from('blog_posts');

        $query = new DbQuery();
        $sql = $query
            ->select('title')
            ->from('news_posts')
            ->union($secondQuery)
            ->limit(20)
            ->sql('mysql', false);

        $expected = "SELECT title FROM `news_posts` "
            . "UNION SELECT title FROM `blog_posts` "
            . "LIMIT 20";

        $this->assertEquals($expected, $sql);
    }

    public function testUnionWithOrderByAndLimit(): void
    {
        $secondQuery = (new DbQuery())
            ->select('id, priority, title')
            ->from('pending_tasks');

        $query = new DbQuery();
        $sql = $query
            ->select('id, priority, title')
            ->from('active_tasks')
            ->union($secondQuery)
            ->orderBy('priority', 'DESC')
            ->orderBy('title')
            ->limit(50)
            ->sql('mysql', false);

        $expected = "SELECT id, priority, title FROM `active_tasks` "
            . "UNION SELECT id, priority, title FROM `pending_tasks` "
            . "ORDER BY priority DESC, title ASC "
            . "LIMIT 50";

        $this->assertEquals($expected, $sql);
    }

    public function testUnionWithComplexQueries(): void
    {
        $firstInnerQuery = (new DbQuery())
            ->select('u.id, u.name, COUNT(o.id) as order_count')
            ->from('users', 'u')
            ->leftJoin('orders', 'u.id = o.user_id', 'o')
            ->where('u.type')->equals('premium')
            ->groupBy('u.id', 'u.name');

        $secondInnerQuery = (new DbQuery())
            ->select('u.id, u.name, COUNT(o.id) as order_count')
            ->from('users', 'u')
            ->leftJoin('orders', 'u.id = o.user_id', 'o')
            ->where('u.type')->equals('standard')
            ->groupBy('u.id', 'u.name');

        $query = new DbQuery();
        $sql = $query
            ->select('u.id, u.name, COUNT(o.id) as order_count')
            ->from('users', 'u')
            ->leftJoin('orders', 'u.id = o.user_id', 'o')
            ->where('u.type')->equals('premium')
            ->groupBy('u.id', 'u.name')
            ->union($secondInnerQuery)
            ->orderBy('order_count', 'DESC')
            ->sql('mysql', false);

        $this->assertStringContainsString('UNION', $sql);
        $this->assertStringContainsString("u.type = 'premium'", $sql);
        $this->assertStringContainsString("u.type = 'standard'", $sql);
        $this->assertStringContainsString('GROUP BY', $sql);
        $this->assertStringContainsString('ORDER BY order_count DESC', $sql);
    }

    public function testUnionReturnsQueryBuilder(): void
    {
        $secondQuery = (new DbQuery())
            ->select('*')
            ->from('table2');

        $query = new DbQuery();
        $result = $query
            ->select('*')
            ->from('table1')
            ->union($secondQuery);

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertSame($query, $result);
    }

    public function testUnionAllReturnsQueryBuilder(): void
    {
        $secondQuery = (new DbQuery())
            ->select('*')
            ->from('table2');

        $query = new DbQuery();
        $result = $query
            ->select('*')
            ->from('table1')
            ->unionAll($secondQuery);

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertSame($query, $result);
    }

    public function testUnionWithDifferentColumns(): void
    {
        // UNION requires same number of columns, but names can differ
        $secondQuery = (new DbQuery())
            ->select('supplier_id as id, company_name as name')
            ->from('suppliers');

        $query = new DbQuery();
        $sql = $query
            ->select('customer_id as id, customer_name as name')
            ->from('customers')
            ->union($secondQuery)
            ->sql('mysql', false);

        $expected = "SELECT customer_id as id, customer_name as name FROM `customers` "
            . "UNION SELECT supplier_id as id, company_name as name FROM `suppliers`";

        $this->assertEquals($expected, $sql);
    }

    public function testUnionWithWhereInBothQueries(): void
    {
        $secondQuery = (new DbQuery())
            ->select('name')
            ->from('users')
            ->where('status')->equals('inactive')
            ->where('created_at')->greater('2023-01-01');

        $query = new DbQuery();
        $sql = $query
            ->select('name')
            ->from('users')
            ->where('status')->equals('active')
            ->where('created_at')->greater('2024-01-01')
            ->union($secondQuery)
            ->sql('mysql', false);

        $this->assertStringContainsString("WHERE status = 'active' AND created_at > '2024-01-01'", $sql);
        $this->assertStringContainsString("WHERE status = 'inactive' AND created_at > '2023-01-01'", $sql);
        $this->assertStringContainsString('UNION', $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertStringContainsString('WHERE status = ? AND created_at > ?', $prepared->sql());
        $this->assertEquals(['active', '2024-01-01', 'inactive', '2023-01-01'], $prepared->bindings());

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertStringContainsString('WHERE status = ? AND created_at > ?', $prepared->sql());
        $this->assertStringContainsString('UNION', $prepared->sql());
        $this->assertEquals(['active', '2024-01-01', 'inactive', '2023-01-01'], $prepared->bindings());
    }

    public function testUnionFiveQueries(): void
    {
        $q2 = (new DbQuery())->select('name')->from('t2');
        $q3 = (new DbQuery())->select('name')->from('t3');
        $q4 = (new DbQuery())->select('name')->from('t4');
        $q5 = (new DbQuery())->select('name')->from('t5');

        $query = new DbQuery();
        $sql = $query
            ->select('name')
            ->from('t1')
            ->union($q2)
            ->union($q3)
            ->unionAll($q4)
            ->unionAll($q5)
            ->sql('mysql', false);

        $expected = "SELECT name FROM `t1` "
            . "UNION SELECT name FROM `t2` "
            . "UNION SELECT name FROM `t3` "
            . "UNION ALL SELECT name FROM `t4` "
            . "UNION ALL SELECT name FROM `t5`";

        $this->assertEquals($expected, $sql);
    }

    public function testUnionAllWithBindingsInPreparedMode(): void
    {
        // Create subquery for UNION ALL with bindings
        $unionQuery = (new DbQuery())
            ->select('id, name')
            ->from('suppliers')
            ->where('country')->equals('AT');

        // Main query with WHERE condition and UNION ALL
        $query = (new DbQuery())
            ->select('id, name')
            ->from('employees')
            ->where('country')->equals('CH')
            ->unionAll($unionQuery);

        $result = $query->sql('mysql', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertStringContainsString('SELECT id, name FROM `employees`', $result->sql());
        $this->assertStringContainsString('WHERE country = ?', $result->sql());
        $this->assertStringContainsString('UNION ALL', $result->sql());
        $this->assertStringContainsString('SELECT id, name FROM `suppliers`', $result->sql());

        // Verify binding order: main query bindings first, then UNION ALL bindings
        $bindings = $result->bindings();
        $this->assertCount(2, $bindings);
        $this->assertSame('CH', $bindings[0], 'First binding should be from main query (country = CH)');
        $this->assertSame('AT', $bindings[1], 'Second binding should be from UNION ALL subquery (country = AT)');
    }
}
