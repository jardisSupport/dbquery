<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Sqlite;

use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * SQLite Subquery Tests
 *
 * Tests: SELECT subqueries, correlated subqueries, nested subqueries
 */
/*
 * SQL-Pins am 2026-08-10 an das Auto-Quoting einfacher Identifier angepasst:
 * WHERE/AND/OR-, HAVING-, ORDER-BY-, GROUP-BY-Felder und die SELECT-Feldliste
 * quoten `ident` bzw. `alias.ident` jetzt dialektgerecht (MySQL/SQLite: Backtick,
 * PostgreSQL: Double-Quote). Ausdruecke, '*', bereits Gequotetes und
 * Expression::raw() bleiben byte-identisch roh. Alle Aenderungen in dieser
 * Datei sind reine Quote-Zeichen-Diffs in erwarteten SQL-Strings.
 */
class DbQuerySqliteSubqueryTest extends TestCase
{
    public function testSelectSubquerySingleSimple(): void
    {
        $subquery = (new DbQuery())
            ->select('COUNT(*)')
            ->from('posts')
            ->where('user_id')->equals(1);

        $query = new DbQuery();
        $sql = $query
            ->select('id, name')
            ->selectSubquery($subquery, 'post_count')
            ->from('users')
            ->sql('sqlite', false);

        $expected = "SELECT `id`, `name`, (SELECT COUNT(*) FROM `posts` WHERE `user_id` = 1) AS `post_count` FROM `users`";
        $this->assertEquals($expected, $sql);
    }

    public function testSelectSubqueryWithCorrelatedCondition(): void
    {
        $subquery = (new DbQuery())
            ->select('COUNT(*)')
            ->from('posts', 'p')
            ->where('p.user_id')->equals(Expression::raw('users.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('id, name')
            ->selectSubquery($subquery, 'post_count')
            ->from('users')
            ->sql('sqlite', false);

        $expected = "SELECT `id`, `name`, (SELECT COUNT(*) FROM `posts` `p` WHERE `p`.`user_id` = users.id) AS `post_count` FROM `users`";
        $this->assertEquals($expected, $sql);
    }

    public function testSelectSubqueryMultiple(): void
    {
        $postCountSubquery = (new DbQuery())
            ->select('COUNT(*)')
            ->from('posts', 'p')
            ->where('p.user_id')->equals(Expression::raw('users.id'));

        $commentCountSubquery = (new DbQuery())
            ->select('COUNT(*)')
            ->from('comments', 'c')
            ->where('c.user_id')->equals(Expression::raw('users.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('id, name')
            ->selectSubquery($postCountSubquery, 'post_count')
            ->selectSubquery($commentCountSubquery, 'comment_count')
            ->from('users')
            ->sql('sqlite', false);

        $expected = "SELECT `id`, `name`, "
            . "(SELECT COUNT(*) FROM `posts` `p` WHERE `p`.`user_id` = users.id) AS `post_count`, "
            . "(SELECT COUNT(*) FROM `comments` `c` WHERE `c`.`user_id` = users.id) AS `comment_count` "
            . "FROM `users`";

        $this->assertEquals($expected, $sql);
    }

    public function testSelectSubqueryWithOnlySubqueries(): void
    {
        $subquery = (new DbQuery())
            ->select('COUNT(*)')
            ->from('posts');

        $query = new DbQuery();
        $sql = $query
            ->select('') // Empty select
            ->selectSubquery($subquery, 'total_posts')
            ->from('users')
            ->sql('sqlite', false);

        $expected = "SELECT (SELECT COUNT(*) FROM `posts`) AS `total_posts` FROM `users`";
        $this->assertEquals($expected, $sql);
    }

    public function testSelectSubqueryWithComplexSubquery(): void
    {
        $subquery = (new DbQuery())
            ->select('MAX(p.created_at)')
            ->from('posts', 'p')
            ->where('p.user_id')->equals(Expression::raw('users.id'))
            ->where('p.status')->equals('published');

        $query = new DbQuery();
        $sql = $query
            ->select('id, name, email')
            ->selectSubquery($subquery, 'last_post_date')
            ->from('users')
            ->where('active')->equals(1)
            ->sql('sqlite', false);

        $expected = "SELECT `id`, `name`, `email`, "
            . "(SELECT MAX(p.created_at) FROM `posts` `p` WHERE `p`.`user_id` = users.id AND `p`.`status` = 'published') AS `last_post_date` "
            . "FROM `users` WHERE `active` = 1";

        $this->assertEquals($expected, $sql);
    }

    public function testSelectSubqueryWithJoinInSubquery(): void
    {
        $subquery = (new DbQuery())
            ->select('COUNT(DISTINCT c.id)')
            ->from('posts', 'p')
            ->innerJoin('comments', 'p.id = c.post_id', 'c')
            ->where('p.user_id')->equals(Expression::raw('users.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('id, name')
            ->selectSubquery($subquery, 'comment_count')
            ->from('users')
            ->sql('sqlite', false);

        $expected = "SELECT `id`, `name`, "
            . "(SELECT COUNT(DISTINCT c.id) FROM `posts` `p` INNER JOIN `comments` `c` ON p.id = c.post_id WHERE `p`.`user_id` = users.id) AS `comment_count` "
            . "FROM `users`";

        $this->assertEquals($expected, $sql);
    }

    public function testSelectSubqueryWithMainQueryJoin(): void
    {
        $subquery = (new DbQuery())
            ->select('COUNT(*)')
            ->from('posts', 'p')
            ->where('p.user_id')->equals(Expression::raw('u.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('u.id, u.name')
            ->selectSubquery($subquery, 'post_count')
            ->from('users', 'u')
            ->innerJoin('profiles', 'u.id = prof.user_id', 'prof')
            ->sql('sqlite', false);

        $expected = "SELECT `u`.`id`, `u`.`name`, "
            . "(SELECT COUNT(*) FROM `posts` `p` WHERE `p`.`user_id` = u.id) AS `post_count` "
            . "FROM `users` `u` INNER JOIN `profiles` `prof` ON u.id = prof.user_id";

        $this->assertEquals($expected, $sql);
    }

    public function testSelectSubqueryWithOrderByAndLimit(): void
    {
        $subquery = (new DbQuery())
            ->select('COUNT(*)')
            ->from('posts', 'p')
            ->where('p.user_id')->equals(Expression::raw('users.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('id, name')
            ->selectSubquery($subquery, 'post_count')
            ->from('users')
            ->orderBy('name')
            ->limit(10)
            ->sql('sqlite', false);

        $expected = "SELECT `id`, `name`, "
            . "(SELECT COUNT(*) FROM `posts` `p` WHERE `p`.`user_id` = users.id) AS `post_count` "
            . "FROM `users` ORDER BY `name` ASC LIMIT 10";

        $this->assertEquals($expected, $sql);
    }

    public function testSelectSubqueryWithGroupByAndHaving(): void
    {
        $subquery = (new DbQuery())
            ->select('AVG(p.rating)')
            ->from('posts', 'p')
            ->where('p.category_id')->equals(Expression::raw('c.id'));

        $query = new DbQuery();
        $sql = $query
            ->select('c.id, c.name, COUNT(*) as total')
            ->selectSubquery($subquery, 'avg_rating')
            ->from('categories', 'c')
            ->groupBy('c.id', 'c.name')
            ->having('COUNT(*)')->greater(5)
            ->sql('sqlite', false);

        $expected = "SELECT `c`.`id`, `c`.`name`, COUNT(*) as `total`, "
            . "(SELECT AVG(p.rating) FROM `posts` `p` WHERE `p`.`category_id` = c.id) AS `avg_rating` "
            . "FROM `categories` `c` GROUP BY `c`.`id`, `c`.`name` HAVING COUNT(*) > 5";

        $this->assertEquals($expected, $sql);
    }

    public function testSelectSubqueryReturnsQueryBuilder(): void
    {
        $subquery = (new DbQuery())
            ->select('COUNT(*)')
            ->from('posts');

        $query = new DbQuery();
        $result = $query->selectSubquery($subquery, 'count');

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertSame($query, $result);
    }

    public function testSelectSubqueryWithNestedSubquery(): void
    {
        // Nested: subquery contains another subquery
        $nestedSubquery = (new DbQuery())
            ->select('MAX(created_at)')
            ->from('comments')
            ->where('post_id')->equals(Expression::raw('p.id'));

        $subquery = (new DbQuery())
            ->select('COUNT(*)')
            ->from('posts', 'p')
            ->where('p.user_id')->equals(Expression::raw('users.id'))
            ->selectSubquery($nestedSubquery, 'last_comment');

        $query = new DbQuery();
        $sql = $query
            ->select('id, name')
            ->selectSubquery($subquery, 'post_info')
            ->from('users')
            ->sql('sqlite', false);

        // Note: This creates a very complex nested structure
        $this->assertStringContainsString('SELECT `id`, `name`,', $sql);
        $this->assertStringContainsString('SELECT COUNT(*)', $sql);
        $this->assertStringContainsString('SELECT MAX(created_at)', $sql);
        $this->assertStringContainsString('AS `post_info`', $sql);
    }

    public function testFromSubqueryWithBindingsInPreparedMode(): void
    {
        // Create subquery for FROM clause with bindings
        $subQuery = (new DbQuery())
            ->select('id, name, email')
            ->from('users')
            ->where('status')->equals('active');

        // Main query with FROM subquery and additional WHERE condition
        $query = (new DbQuery())
            ->select('*')
            ->from($subQuery, 'sub')
            ->where('sub.id')->greater(10);

        $result = $query->sql('sqlite', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertStringContainsString('FROM (SELECT', $result->sql());
        $this->assertStringContainsString('FROM `users`', $result->sql());
        $this->assertStringContainsString('WHERE `status` = ?', $result->sql());
        $this->assertStringContainsString(') `sub`', $result->sql());
        $this->assertStringContainsString('WHERE `sub`.`id` > ?', $result->sql());

        // Verify binding order: FROM subquery bindings first, then main query bindings
        $bindings = $result->bindings();
        $this->assertCount(2, $bindings);
        $this->assertSame('active', $bindings[0], 'First binding should be from FROM subquery (status = active)');
        $this->assertSame(10, $bindings[1], 'Second binding should be from main query WHERE (id > 10)');
    }

    public function testFromSubqueryInNonPreparedMode(): void
    {
        // Create subquery for FROM clause
        $subQuery = (new DbQuery())
            ->select('id, name')
            ->from('users')
            ->where('status')->equals('active');

        // Main query with FROM subquery in non-prepared mode
        $query = (new DbQuery())
            ->select('*')
            ->from($subQuery, 'sub')
            ->where('sub.id')->greater(5);

        $sql = $query->sql('sqlite', false);

        $this->assertIsString($sql);
        $this->assertStringContainsString('FROM (SELECT `id`, `name` FROM `users` WHERE `status` = \'active\')', $sql);
        $this->assertStringContainsString('`sub`', $sql);
        $this->assertStringContainsString('WHERE `sub`.`id` > 5', $sql);
    }
}
