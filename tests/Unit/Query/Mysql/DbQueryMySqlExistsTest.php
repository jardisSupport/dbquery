<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Mysql;

use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * MySQL EXISTS Tests
 *
 * Tests: EXISTS, NOT EXISTS, complex subqueries with EXISTS
 */
class DbQueryMySqlExistsTest extends TestCase
{
    public function testExistsSimple(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('posts', 'p')
            ->where('p.user_id')->equals(Expression::raw('users.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->exists($subquery)
            ->sql('mysql', false);

        $expected = "SELECT * FROM `users` WHERE EXISTS (SELECT 1 FROM `posts` `p` WHERE p.user_id = users.id)";

        $this->assertEquals($expected, $sql);
    }

    public function testNotExistsSimple(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('posts', 'p')
            ->where('p.user_id')->equals(Expression::raw('users.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->notExists($subquery)
            ->sql('mysql', false);

        $expected = "SELECT * FROM `users` WHERE NOT EXISTS (SELECT 1 FROM `posts` `p` WHERE p.user_id = users.id)";

        $this->assertEquals($expected, $sql);
    }

    public function testPreparedExists(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('orders')
            ->where('orders.user_id')->equals(Expression::raw('users.id'))
            ->and('orders.status')->equals('completed');

        $update = new DbQuery();
        $result = $update->from('users')
            ->where('active')->equals(1)
            ->exists($subquery)
            ->sql('mysql', true);

        $expected = "SELECT * FROM `users` " .
            "WHERE active = ? AND EXISTS (SELECT 1 FROM `orders` WHERE orders.user_id = users.id AND orders.status = ?)";

        $this->assertEquals($expected, $result->sql());
        $this->assertEquals([1, 'completed'], $result->bindings());
    }

    public function testExistsWithComplexSubquery(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('orders', 'o')
            ->where('o.user_id')->equals(Expression::raw('u.id'))
            ->where('o.status')->equals('completed')
            ->where('o.total')->greater(1000);

        $query = new DbQuery();
        $sql = $query
            ->select('u.id, u.name')
            ->from('users', 'u')
            ->exists($subquery)
            ->sql('mysql', false);

        $expected = "SELECT u.id, u.name FROM `users` `u` "
            . "WHERE EXISTS (SELECT 1 FROM `orders` `o` "
            . "WHERE o.user_id = u.id AND o.status = 'completed' AND o.total > 1000)";

        $this->assertEquals($expected, $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $expectedPrepared = "SELECT u.id, u.name FROM `users` `u` "
            . "WHERE EXISTS (SELECT 1 FROM `orders` `o` "
            . "WHERE o.user_id = u.id AND o.status = ? AND o.total > ?)";
        $this->assertEquals($expectedPrepared, $prepared->sql());
        $this->assertEquals(['completed', 1000], $prepared->bindings());
    }

    public function testNotExistsWithComplexSubquery(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('violations', 'v')
            ->where('v.user_id')->equals(Expression::raw('users.id'))
            ->where('v.severity')->equals('critical');

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->notExists($subquery)
            ->sql('mysql', false);

        $expected = "SELECT * FROM `users` "
            . "WHERE NOT EXISTS (SELECT 1 FROM `violations` `v` "
            . "WHERE v.user_id = users.id AND v.severity = 'critical')";

        $this->assertEquals($expected, $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $expectedPrepared = "SELECT * FROM `users` "
            . "WHERE NOT EXISTS (SELECT 1 FROM `violations` `v` "
            . "WHERE v.user_id = users.id AND v.severity = ?)";
        $this->assertEquals($expectedPrepared, $prepared->sql());
        $this->assertEquals(['critical'], $prepared->bindings());
    }

    public function testExistsWithJoinInSubquery(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('posts', 'p')
            ->innerJoin('comments', 'p.id = c.post_id', 'c')
            ->where('p.user_id')->equals(Expression::raw('users.id'))
            ->where('c.status')->equals('approved');

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->exists($subquery)
            ->sql('mysql', false);

        $this->assertStringContainsString('WHERE EXISTS (SELECT 1 FROM `posts` `p`', $sql);
        $this->assertStringContainsString('INNER JOIN `comments` `c`', $sql);
        $this->assertStringContainsString("c.status = 'approved'", $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertStringContainsString('WHERE EXISTS (SELECT 1 FROM `posts` `p`', $prepared->sql());
        $this->assertStringContainsString('c.status = ?', $prepared->sql());
        $this->assertEquals(['approved'], $prepared->bindings());
    }

    public function testExistsCombinedWithWhereConditions(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('orders', 'o')
            ->where('o.user_id')->equals(Expression::raw('u.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users', 'u')
            ->where('u.active')->equals(1)
            ->exists($subquery)
            ->sql('mysql', false);

        $expected = "SELECT * FROM `users` `u` "
            . "WHERE u.active = 1 AND EXISTS (SELECT 1 FROM `orders` `o` WHERE o.user_id = u.id)";

        $this->assertEquals($expected, $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $expectedPrepared = "SELECT * FROM `users` `u` "
            . "WHERE u.active = ? AND EXISTS (SELECT 1 FROM `orders` `o` WHERE o.user_id = u.id)";
        $this->assertEquals($expectedPrepared, $prepared->sql());
        $this->assertEquals([1], $prepared->bindings());
    }

    public function testNotExistsCombinedWithWhereConditions(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('bans', 'b')
            ->where('b.user_id')->equals(Expression::raw('u.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users', 'u')
            ->where('u.status')->equals('active')
            ->notExists($subquery)
            ->sql('mysql', false);

        $expected = "SELECT * FROM `users` `u` "
            . "WHERE u.status = 'active' AND NOT EXISTS (SELECT 1 FROM `bans` `b` WHERE b.user_id = u.id)";

        $this->assertEquals($expected, $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $expectedPrepared = "SELECT * FROM `users` `u` "
            . "WHERE u.status = ? AND NOT EXISTS (SELECT 1 FROM `bans` `b` WHERE b.user_id = u.id)";
        $this->assertEquals($expectedPrepared, $prepared->sql());
        $this->assertEquals(['active'], $prepared->bindings());
    }

    public function testExistsWithBrackets(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('premium_features', 'pf')
            ->where('pf.user_id')->equals(Expression::raw('u.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users', 'u')
            ->where('u.age', '(')->greater(18)
            ->exists($subquery, ')')
            ->sql('mysql', false);

        $this->assertStringContainsString('WHERE (u.age > 18 AND EXISTS', $sql);
        $this->assertStringContainsString('))', $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertStringContainsString('WHERE (u.age > ?', $prepared->sql());
        $this->assertEquals([18], $prepared->bindings());
    }

    public function testNotExistsWithBrackets(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('warnings', 'w')
            ->where('w.user_id')->equals(Expression::raw('u.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users', 'u')
            ->where('u.verified', '(')->equals(1)
            ->notExists($subquery, ')')
            ->sql('mysql', false);

        $this->assertStringContainsString('WHERE (u.verified = 1 AND NOT EXISTS', $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertStringContainsString('WHERE (u.verified = ?', $prepared->sql());
        $this->assertEquals([1], $prepared->bindings());
    }

    public function testMultipleExistsConditions(): void
    {
        $hasOrdersSubquery = (new DbQuery())
            ->select('1')
            ->from('orders', 'o')
            ->where('o.user_id')->equals(Expression::raw('u.id'));

        $hasReviewsSubquery = (new DbQuery())
            ->select('1')
            ->from('reviews', 'r')
            ->where('r.user_id')->equals(Expression::raw('u.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users', 'u')
            ->exists($hasOrdersSubquery)
            ->exists($hasReviewsSubquery)
            ->sql('mysql', false);

        $this->assertStringContainsString('WHERE EXISTS (SELECT 1 FROM `orders`', $sql);
        $this->assertStringContainsString('AND EXISTS (SELECT 1 FROM `reviews`', $sql);
    }

    public function testExistsAndNotExistsCombined(): void
    {
        $hasPostsSubquery = (new DbQuery())
            ->select('1')
            ->from('posts', 'p')
            ->where('p.user_id')->equals(Expression::raw('u.id'));

        $hasBansSubquery = (new DbQuery())
            ->select('1')
            ->from('bans', 'b')
            ->where('b.user_id')->equals(Expression::raw('u.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users', 'u')
            ->exists($hasPostsSubquery)
            ->notExists($hasBansSubquery)
            ->sql('mysql', false);

        $this->assertStringContainsString('WHERE EXISTS (SELECT 1 FROM `posts`', $sql);
        $this->assertStringContainsString('AND NOT EXISTS (SELECT 1 FROM `bans`', $sql);
    }

    public function testExistsReturnsQueryBuilder(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('posts');

        $query = new DbQuery();
        $result = $query
            ->select('*')
            ->from('users')
            ->exists($subquery);

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertSame($query, $result);
    }

    public function testNotExistsReturnsQueryBuilder(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('posts');

        $query = new DbQuery();
        $result = $query
            ->select('*')
            ->from('users')
            ->notExists($subquery);

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertSame($query, $result);
    }

    public function testExistsWithAggregateInSubquery(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('orders', 'o')
            ->where('o.user_id')->equals(Expression::raw('u.id'))
            ->groupBy('o.user_id')
            ->having('COUNT(*)')->greater(5);

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users', 'u')
            ->exists($subquery)
            ->sql('mysql', false);

        $this->assertStringContainsString('WHERE EXISTS (SELECT 1 FROM `orders`', $sql);
        $this->assertStringContainsString('GROUP BY o.user_id', $sql);
        $this->assertStringContainsString('HAVING COUNT(*) > 5', $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertStringContainsString('HAVING COUNT(*) > ?', $prepared->sql());
        $this->assertEquals([5], $prepared->bindings());
    }

    public function testExistsWithOptionalTrue(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('posts', 'p')
            ->where('p.user_id')->equals(Expression::raw('users.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->exists($subquery, null, true)
            ->sql('mysql', false);

        // With optional=true, EXISTS should still be included if subquery is present
        $this->assertStringContainsString('WHERE EXISTS', $sql);
    }

    public function testExistsWithUnionInSubquery(): void
    {
        $subquery1 = (new DbQuery())
            ->select('user_id')
            ->from('orders')
            ->where('status')->equals('completed');

        $subquery = (new DbQuery())
            ->select('user_id')
            ->from('subscriptions')
            ->where('active')->equals(1)
            ->union($subquery1);

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users', 'u')
            ->exists($subquery)
            ->sql('mysql', false);

        $this->assertStringContainsString('WHERE EXISTS', $sql);
        $this->assertStringContainsString('UNION', $sql);
    }

    public function testNotExistsWithNestedSubquery(): void
    {
        $nestedSubquery = (new DbQuery())
            ->select('order_id')
            ->from('refunds')
            ->where('refund_status')->equals('approved');

        $mainSubquery = (new DbQuery())
            ->select('1')
            ->from('orders', 'o')
            ->where('o.user_id')->equals(Expression::raw('u.id'))
            ->where('o.id')->in($nestedSubquery);

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users', 'u')
            ->notExists($mainSubquery)
            ->sql('mysql', false);

        $this->assertStringContainsString('WHERE NOT EXISTS', $sql);
        $this->assertStringContainsString('IN (SELECT order_id FROM', $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertStringContainsString('WHERE NOT EXISTS', $prepared->sql());
        $this->assertStringContainsString('IN (SELECT order_id FROM', $prepared->sql());
        $this->assertEquals(['approved'], $prepared->bindings());
    }

    public function testExistsWithMultipleCorrelations(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('user_permissions', 'up')
            ->where('up.user_id')->equals(Expression::raw('u.id'))
            ->where('up.resource_type')->equals(Expression::raw('r.type'))
            ->where('up.permission')->equals('write');

        $query = new DbQuery();
        $sql = $query
            ->select('u.name, r.name')
            ->from('users', 'u')
            ->crossJoin('resources', 'r')
            ->exists($subquery)
            ->sql('mysql', false);

        $this->assertStringContainsString('WHERE EXISTS', $sql);
        $this->assertStringContainsString('up.user_id = u.id', $sql);
        $this->assertStringContainsString('up.resource_type = r.type', $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertStringContainsString('WHERE EXISTS', $prepared->sql());
        $this->assertStringContainsString('up.permission = ?', $prepared->sql());
        $this->assertEquals(['write'], $prepared->bindings());
    }
}
