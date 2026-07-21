<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Sqlite;

use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * SQLite UNION Tests
 *
 * Tests: UNION, UNION ALL
 */
class DbQuerySqliteUnionTest extends TestCase
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
            ->sql('sqlite', false);

        $expected = "SELECT id, name FROM `customers` WHERE country = 'Germany' "
            . "UNION SELECT id, name FROM `suppliers` WHERE country = 'Germany'";

        $this->assertEquals($expected, $sql);
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
            ->sql('sqlite', false);

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
            ->sql('sqlite', false);

        $expected = "SELECT email FROM `customers` "
            . "UNION SELECT email FROM `suppliers` "
            . "UNION SELECT email FROM `partners`";

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
            ->sql('sqlite', false);

        $this->assertStringContainsString('SELECT name, created_at FROM `active_posts`', $sql);
        $this->assertStringContainsString('UNION', $sql);
        $this->assertStringContainsString('SELECT name, created_at FROM `archived_posts`', $sql);
        $this->assertStringContainsString('ORDER BY created_at DESC', $sql);

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
            ->sql('sqlite', false);

        $expected = "SELECT title FROM `news_posts` "
            . "UNION SELECT title FROM `blog_posts` "
            . "LIMIT 20";

        $this->assertEquals($expected, $sql);
    }

    public function testUnionWithOrderByAndLimit(): void
    {
        $secondQuery = (new DbQuery())
            ->select('id, name, created_at')
            ->from('archived_items')
            ->where('status')->equals('closed');

        $query = new DbQuery();
        $sql = $query
            ->select('id, name, created_at')
            ->from('active_items')
            ->where('status')->equals('open')
            ->union($secondQuery)
            ->orderBy('created_at', 'DESC')
            ->limit(50)
            ->sql('sqlite', false);

        $this->assertStringContainsString('SELECT id, name, created_at FROM `active_items`', $sql);
        $this->assertStringContainsString("WHERE status = 'open'", $sql);
        $this->assertStringContainsString('UNION', $sql);
        $this->assertStringContainsString('SELECT id, name, created_at FROM `archived_items`', $sql);
        $this->assertStringContainsString("WHERE status = 'closed'", $sql);
        $this->assertStringContainsString('ORDER BY created_at DESC', $sql);
        $this->assertStringContainsString('LIMIT 50', $sql);
    }

    public function testUnionWithComplexQueries(): void
    {
        $secondQuery = (new DbQuery())
            ->select('u.id, u.email')
            ->from('inactive_users', 'u')
            ->leftJoin('profiles', 'u.id = p.user_id', 'p')
            ->where('u.last_login')->lower('2023-01-01')
            ->orderBy('u.email');

        $query = new DbQuery();
        $sql = $query
            ->select('u.id, u.email')
            ->from('active_users', 'u')
            ->innerJoin('profiles', 'u.id = p.user_id', 'p')
            ->where('u.status')->equals('verified')
            ->union($secondQuery)
            ->limit(100)
            ->sql('sqlite', false);

        $this->assertStringContainsString('SELECT u.id, u.email', $sql);
        $this->assertStringContainsString('FROM `active_users` `u`', $sql);
        $this->assertStringContainsString('INNER JOIN `profiles`', $sql);
        $this->assertStringContainsString("WHERE u.status = 'verified'", $sql);
        $this->assertStringContainsString('UNION', $sql);
        $this->assertStringContainsString('FROM `inactive_users` `u`', $sql);
        $this->assertStringContainsString('LEFT JOIN `profiles`', $sql);
        $this->assertStringContainsString("WHERE u.last_login < '2023-01-01'", $sql);
        $this->assertStringContainsString('LIMIT 100', $sql);
    }

    public function testUnionAllWithMultipleQueries(): void
    {
        $secondQuery = (new DbQuery())
            ->select('email, created_at')
            ->from('pending_subscribers');

        $thirdQuery = (new DbQuery())
            ->select('email, created_at')
            ->from('archived_subscribers');

        $query = new DbQuery();
        $sql = $query
            ->select('email, created_at')
            ->from('active_subscribers')
            ->unionAll($secondQuery)
            ->unionAll($thirdQuery)
            ->sql('sqlite', false);

        $expected = "SELECT email, created_at FROM `active_subscribers` "
            . "UNION ALL SELECT email, created_at FROM `pending_subscribers` "
            . "UNION ALL SELECT email, created_at FROM `archived_subscribers`";

        $this->assertEquals($expected, $sql);
    }

    public function testUnionMixedWithUnionAll(): void
    {
        $secondQuery = (new DbQuery())
            ->select('name')
            ->from('suppliers');

        $thirdQuery = (new DbQuery())
            ->select('name')
            ->from('partners');

        $query = new DbQuery();
        $sql = $query
            ->select('name')
            ->from('customers')
            ->union($secondQuery)
            ->unionAll($thirdQuery)
            ->sql('sqlite', false);

        $expected = "SELECT name FROM `customers` "
            . "UNION SELECT name FROM `suppliers` "
            . "UNION ALL SELECT name FROM `partners`";

        $this->assertEquals($expected, $sql);
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

        $result = $query->sql('sqlite', true);

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
