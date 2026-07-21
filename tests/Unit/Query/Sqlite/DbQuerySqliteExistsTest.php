<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Sqlite;

use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * SQLite EXISTS Tests
 *
 * Tests: EXISTS, NOT EXISTS, andExists, orExists, andNotExists, orNotExists
 */
class DbQuerySqliteExistsTest extends TestCase
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
            ->sql('sqlite', false);

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
            ->sql('sqlite', false);

        $expected = "SELECT * FROM `users` WHERE NOT EXISTS (SELECT 1 FROM `posts` `p` WHERE p.user_id = users.id)";

        $this->assertEquals($expected, $sql);
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
            ->sql('sqlite', false);

        $expected = "SELECT * FROM `users` `u` "
            . "WHERE u.active = 1 AND EXISTS (SELECT 1 FROM `orders` `o` WHERE o.user_id = u.id)";

        $this->assertEquals($expected, $sql);
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
            ->sql('sqlite', false);

        $expected = "SELECT * FROM `users` `u` "
            . "WHERE u.status = 'active' AND NOT EXISTS (SELECT 1 FROM `bans` `b` WHERE b.user_id = u.id)";

        $this->assertEquals($expected, $sql);
    }

    public function testAndExists(): void
    {
        $firstSubquery = (new DbQuery())
            ->select('1')
            ->from('posts', 'p')
            ->where('p.user_id')->equals(Expression::raw('u.id'));

        $secondSubquery = (new DbQuery())
            ->select('1')
            ->from('comments', 'c')
            ->where('c.user_id')->equals(Expression::raw('u.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users', 'u')
            ->where('u.active')->equals(1)
            ->exists($firstSubquery)
            ->and()->exists($secondSubquery)
            ->sql('sqlite', false);

        $expected = "SELECT * FROM `users` `u` "
            . "WHERE u.active = 1 "
            . "AND EXISTS (SELECT 1 FROM `posts` `p` WHERE p.user_id = u.id) "
            . "AND EXISTS (SELECT 1 FROM `comments` `c` WHERE c.user_id = u.id)";

        $this->assertEquals($expected, $sql);
    }

    public function testOrExists(): void
    {
        $firstSubquery = (new DbQuery())
            ->select('1')
            ->from('posts', 'p')
            ->where('p.user_id')->equals(Expression::raw('u.id'));

        $secondSubquery = (new DbQuery())
            ->select('1')
            ->from('drafts', 'd')
            ->where('d.user_id')->equals(Expression::raw('u.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users', 'u')
            ->exists($firstSubquery)
            ->or()->exists($secondSubquery)
            ->sql('sqlite', false);

        $expected = "SELECT * FROM `users` `u` "
            . "WHERE EXISTS (SELECT 1 FROM `posts` `p` WHERE p.user_id = u.id) "
            . "OR EXISTS (SELECT 1 FROM `drafts` `d` WHERE d.user_id = u.id)";

        $this->assertEquals($expected, $sql);
    }

    public function testAndNotExists(): void
    {
        $firstSubquery = (new DbQuery())
            ->select('1')
            ->from('bans', 'b')
            ->where('b.user_id')->equals(Expression::raw('u.id'));

        $secondSubquery = (new DbQuery())
            ->select('1')
            ->from('suspensions', 's')
            ->where('s.user_id')->equals(Expression::raw('u.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users', 'u')
            ->where('u.active')->equals(1)
            ->notExists($firstSubquery)
            ->and()->notExists($secondSubquery)
            ->sql('sqlite', false);

        $expected = "SELECT * FROM `users` `u` "
            . "WHERE u.active = 1 "
            . "AND NOT EXISTS (SELECT 1 FROM `bans` `b` WHERE b.user_id = u.id) "
            . "AND NOT EXISTS (SELECT 1 FROM `suspensions` `s` WHERE s.user_id = u.id)";

        $this->assertEquals($expected, $sql);
    }

    public function testOrNotExists(): void
    {
        $firstSubquery = (new DbQuery())
            ->select('1')
            ->from('posts', 'p')
            ->where('p.user_id')->equals(Expression::raw('u.id'));

        $secondSubquery = (new DbQuery())
            ->select('1')
            ->from('comments', 'c')
            ->where('c.user_id')->equals(Expression::raw('u.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users', 'u')
            ->notExists($firstSubquery)
            ->or()->notExists($secondSubquery)
            ->sql('sqlite', false);

        $expected = "SELECT * FROM `users` `u` "
            . "WHERE NOT EXISTS (SELECT 1 FROM `posts` `p` WHERE p.user_id = u.id) "
            . "OR NOT EXISTS (SELECT 1 FROM `comments` `c` WHERE c.user_id = u.id)";

        $this->assertEquals($expected, $sql);
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





    public function testExistsWithMultipleConditions(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('orders', 'o')
            ->where('o.user_id')->equals(Expression::raw('u.id'))
            ->where('o.status')->equals('completed')
            ->where('o.total')->greater(100);

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users', 'u')
            ->where('u.active')->equals(1)
            ->exists($subquery)
            ->sql('sqlite', false);

        $expected = "SELECT * FROM `users` `u` "
            . "WHERE u.active = 1 "
            . "AND EXISTS (SELECT 1 FROM `orders` `o` WHERE o.user_id = u.id AND o.status = 'completed' AND o.total > 100)";

        $this->assertEquals($expected, $sql);
    }

    public function testNotExistsWithJoinInSubquery(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('posts', 'p')
            ->innerJoin('spam_reports', 'p.id = sr.post_id', 'sr')
            ->where('p.user_id')->equals(Expression::raw('u.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users', 'u')
            ->notExists($subquery)
            ->sql('sqlite', false);

        $expected = "SELECT * FROM `users` `u` "
            . "WHERE NOT EXISTS (SELECT 1 FROM `posts` `p` INNER JOIN `spam_reports` `sr` ON p.id = sr.post_id WHERE p.user_id = u.id)";

        $this->assertEquals($expected, $sql);
    }
}
