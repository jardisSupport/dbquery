<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Mysql;

use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use PHPUnit\Framework\TestCase;

/**
 * MySQL JOIN Tests
 *
 * Tests: INNER JOIN, LEFT JOIN, RIGHT JOIN, FULL JOIN, CROSS JOIN
 */
class DbQueryMySqlJoinTest extends TestCase
{
    public function testInnerJoinWithConstraint(): void
    {
        $query = new DbQuery();
        $result = $query->innerJoin('posts', 'users.id = p.user_id', 'p');
        $this->assertSame($query, $result);

        $sql = $query->select('*')
            ->from('users')
            ->sql('mysql', false);

        $this->assertEquals('SELECT * FROM `users` INNER JOIN `posts` `p` ON users.id = p.user_id', $sql);
    }

    public function testLeftJoin(): void
    {
        $query = new DbQuery();
        $sql = $query->select('*')
            ->from('users')
            ->leftJoin('posts', 'users.id = p.user_id', 'p')
            ->sql('mysql', false);

        $this->assertEquals('SELECT * FROM `users` LEFT JOIN `posts` `p` ON users.id = p.user_id', $sql);
    }

    public function testRightJoin(): void
    {
        $query = new DbQuery();
        $result = $query->rightJoin('posts', 'users.id = p.user_id', 'p');
        $this->assertSame($query, $result);

        $sql = $query->select('*')->from('users')->sql('mysql', false);
        $expected = 'SELECT * FROM `users` RIGHT JOIN `posts` `p` ON users.id = p.user_id';
        $this->assertEquals($expected, $sql);
    }

    public function testFullJoinThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('FULL OUTER JOIN is not supported by this database dialect');

        $query = new DbQuery();
        $query->select('*')
            ->from('users')
            ->fullJoin('posts', 'users.id = p.user_id', 'p')
            ->sql('mysql', false);
    }

    public function testCrossJoin(): void
    {
        $query = new DbQuery();
        $result = $query->crossJoin('posts', 'p');
        $this->assertSame($query, $result);

        $sql = $query->select('*')->from('users')->sql('mysql', false);
        $expected = 'SELECT * FROM `users` CROSS JOIN `posts` `p`';
        $this->assertEquals($expected, $sql);
    }

    public function testCrossJoinWithoutAlias(): void
    {
        $query = new DbQuery();
        $sql = $query->select('*')->from('users')->crossJoin('posts')->sql('mysql', false);
        $expected = 'SELECT * FROM `users` CROSS JOIN `posts`';
        $this->assertEquals($expected, $sql);
    }

    public function testMultipleJoins(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->innerJoin('posts', 'users.id = p.user_id', 'p')
            ->leftJoin('comments', 'p.id = c.post_id', 'c')
            ->sql('mysql', false);

        $expected = 'SELECT * FROM `users` INNER JOIN `posts` `p` ON users.id = p.user_id LEFT JOIN `comments` `c` ON p.id = c.post_id';
        $this->assertEquals($expected, $sql);
    }

    public function testAllJoinTypesCombined(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->innerJoin('profiles', 'users.id = prof.user_id', 'prof')
            ->leftJoin('posts', 'users.id = p.user_id', 'p')
            ->rightJoin('settings', 'users.id = s.user_id', 's')
            ->crossJoin('permissions', 'perm')
            ->sql('mysql', false);

        $expected = 'SELECT * FROM `users` '
            . 'INNER JOIN `profiles` `prof` ON users.id = prof.user_id '
            . 'LEFT JOIN `posts` `p` ON users.id = p.user_id '
            . 'RIGHT JOIN `settings` `s` ON users.id = s.user_id '
            . 'CROSS JOIN `permissions` `perm`';

        $this->assertEquals($expected, $sql);
    }

    public function testJoinSubqueryWithBindingsInPreparedMode(): void
    {
        // Create subquery for JOIN clause with bindings
        $subQuery = (new DbQuery())
            ->select('user_id, COUNT(*) as order_count')
            ->from('orders')
            ->where('status')->equals('completed')
            ->groupBy('user_id');

        // Main query with JOIN subquery and additional WHERE condition
        $query = (new DbQuery())
            ->select('u.id, u.name, o.order_count')
            ->from('users', 'u')
            ->leftJoin($subQuery, 'u.id = o.user_id', 'o')
            ->where('u.active')->equals(1);

        $result = $query->sql('mysql', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertStringContainsString('FROM `users` `u`', $result->sql());
        $this->assertStringContainsString('LEFT JOIN (SELECT', $result->sql());
        $this->assertStringContainsString('FROM `orders`', $result->sql());
        $this->assertStringContainsString('WHERE status = ?', $result->sql());
        $this->assertStringContainsString(') `o` ON u.id = o.user_id', $result->sql());
        $this->assertStringContainsString('WHERE u.active = ?', $result->sql());

        // Verify binding order: JOIN subquery bindings first, then main query bindings
        $bindings = $result->bindings();
        $this->assertCount(2, $bindings);
        $this->assertSame('completed', $bindings[0], 'First binding should be from JOIN subquery (status = completed)');
        $this->assertSame(1, $bindings[1], 'Second binding should be from main query WHERE (active = 1)');
    }

    public function testJoinSubqueryInNonPreparedMode(): void
    {
        // Create subquery for JOIN clause
        $subQuery = (new DbQuery())
            ->select('user_id, COUNT(*) as cnt')
            ->from('orders')
            ->where('status')->equals('paid')
            ->groupBy('user_id');

        // Main query with JOIN subquery in non-prepared mode
        $query = (new DbQuery())
            ->select('u.name, o.cnt')
            ->from('users', 'u')
            ->leftJoin($subQuery, 'u.id = o.user_id', 'o')
            ->where('u.active')->equals(1);

        $sql = $query->sql('mysql', false);

        $this->assertIsString($sql);
        $this->assertStringContainsString('FROM `users` `u`', $sql);
        $this->assertStringContainsString('LEFT JOIN (SELECT user_id, COUNT(*) as cnt FROM `orders` WHERE status = \'paid\'', $sql);
        $this->assertStringContainsString('`o` ON u.id = o.user_id', $sql);
        $this->assertStringContainsString('WHERE u.active = 1', $sql);
    }
}
