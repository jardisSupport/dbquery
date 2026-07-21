<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Postgres;

use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * PostgreSQL EXISTS Tests
 *
 * Tests: EXISTS, NOT EXISTS, complex subqueries with EXISTS
 */
class DbQueryPostgresExistsTest extends TestCase
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
            ->sql('postgres', false);

        $expected = "SELECT * FROM \"users\" WHERE EXISTS (SELECT 1 FROM \"posts\" \"p\" WHERE p.user_id = users.id)";

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
            ->sql('postgres', false);

        $expected = "SELECT * FROM \"users\" WHERE NOT EXISTS (SELECT 1 FROM \"posts\" \"p\" WHERE p.user_id = users.id)";

        $this->assertEquals($expected, $sql);
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
            ->sql('postgres', false);

        $expected = "SELECT u.id, u.name FROM \"users\" \"u\" "
            . "WHERE EXISTS (SELECT 1 FROM \"orders\" \"o\" "
            . "WHERE o.user_id = u.id AND o.status = 'completed' AND o.total > 1000)";

        $this->assertEquals($expected, $sql);

        $prepared = $query->sql('postgres', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $expectedPrepared = "SELECT u.id, u.name FROM \"users\" \"u\" "
            . "WHERE EXISTS (SELECT 1 FROM \"orders\" \"o\" "
            . "WHERE o.user_id = u.id AND o.status = ? AND o.total > ?)";
        $this->assertEquals($expectedPrepared, $prepared->sql());
        $this->assertEquals(['completed', 1000], $prepared->bindings());

        $prepared = $query->sql('postgres', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $expectedPrepared = "SELECT u.id, u.name FROM \"users\" \"u\" "
            . "WHERE EXISTS (SELECT 1 FROM \"orders\" \"o\" "
            . "WHERE o.user_id = u.id AND o.status = ? AND o.total > ?)";
        $this->assertEquals($expectedPrepared, $prepared->sql());
        $this->assertEquals(['completed', 1000], $prepared->bindings());
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
            ->sql('postgres', false);

        $expected = "SELECT * FROM \"users\" \"u\" "
            . "WHERE u.active = 1 AND EXISTS (SELECT 1 FROM \"orders\" \"o\" WHERE o.user_id = u.id)";

        $this->assertEquals($expected, $sql);

        $prepared = $query->sql('postgres', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $expectedPrepared = "SELECT * FROM \"users\" \"u\" "
            . "WHERE u.active = ? AND EXISTS (SELECT 1 FROM \"orders\" \"o\" WHERE o.user_id = u.id)";
        $this->assertEquals($expectedPrepared, $prepared->sql());
        $this->assertEquals([1], $prepared->bindings());

        $prepared = $query->sql('postgres', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $expectedPrepared = "SELECT * FROM \"users\" \"u\" "
            . "WHERE u.active = ? AND EXISTS (SELECT 1 FROM \"orders\" \"o\" WHERE o.user_id = u.id)";
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
            ->sql('postgres', false);

        $expected = "SELECT * FROM \"users\" \"u\" "
            . "WHERE u.status = 'active' AND NOT EXISTS (SELECT 1 FROM \"bans\" \"b\" WHERE b.user_id = u.id)";

        $this->assertEquals($expected, $sql);

        $prepared = $query->sql('postgres', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $expectedPrepared = "SELECT * FROM \"users\" \"u\" "
            . "WHERE u.status = ? AND NOT EXISTS (SELECT 1 FROM \"bans\" \"b\" WHERE b.user_id = u.id)";
        $this->assertEquals($expectedPrepared, $prepared->sql());
        $this->assertEquals(['active'], $prepared->bindings());

        $prepared = $query->sql('postgres', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $expectedPrepared = "SELECT * FROM \"users\" \"u\" "
            . "WHERE u.status = ? AND NOT EXISTS (SELECT 1 FROM \"bans\" \"b\" WHERE b.user_id = u.id)";
        $this->assertEquals($expectedPrepared, $prepared->sql());
        $this->assertEquals(['active'], $prepared->bindings());
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
            ->sql('postgres', false);

        $this->assertStringContainsString('WHERE EXISTS (SELECT 1 FROM "orders"', $sql);
        $this->assertStringContainsString('AND EXISTS (SELECT 1 FROM "reviews"', $sql);
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
            ->sql('postgres', false);

        $this->assertStringContainsString('WHERE EXISTS (SELECT 1 FROM "posts"', $sql);
        $this->assertStringContainsString('AND NOT EXISTS (SELECT 1 FROM "bans"', $sql);
    }
}
