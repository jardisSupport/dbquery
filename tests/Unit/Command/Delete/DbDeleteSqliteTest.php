<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Command\Delete;

use InvalidArgumentException;
use JardisSupport\DbQuery\DbDelete;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\Contract\DbQuery\DbDeleteBuilderInterface;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * SQLite DELETE Tests
 *
 * Tests: DELETE FROM, WHERE, ORDER BY, LIMIT (SQLite 3.24.0+)
 * Note: SQLite has limited JOIN support and does not support RIGHT/FULL OUTER JOIN
 */
/*
 * SQL-Pins am 2026-08-10 an das Auto-Quoting einfacher Identifier angepasst:
 * WHERE/AND/OR-, HAVING-, ORDER-BY-, GROUP-BY-Felder und die SELECT-Feldliste
 * quoten `ident` bzw. `alias.ident` jetzt dialektgerecht (MySQL/SQLite: Backtick,
 * PostgreSQL: Double-Quote). Ausdruecke, '*', bereits Gequotetes und
 * Expression::raw() bleiben byte-identisch roh. Alle Aenderungen in dieser
 * Datei sind reine Quote-Zeichen-Diffs in erwarteten SQL-Strings.
 */
class DbDeleteSqliteTest extends TestCase
{
    public function testConstructor(): void
    {
        $delete = new DbDelete();
        $this->assertInstanceOf(DbDeleteBuilderInterface::class, $delete);
    }

    public function testSimpleDelete(): void
    {
        $delete = new DbDelete();
        $sql = $delete
            ->from('users')
            ->where('id')->equals(1)
            ->sql('sqlite', false);

        $expected = "DELETE FROM `users` WHERE `id` = 1";
        $this->assertEquals($expected, $sql);
    }

    public function testSimpleDeletePrepared(): void
    {
        $delete = new DbDelete();
        $result = $delete
            ->from('users')
            ->where('id')->equals(1)
            ->sql('sqlite', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals("DELETE FROM `users` WHERE `id` = ?", $result->sql());
        $this->assertEquals([1], $result->bindings());
    }

    public function testDeleteWithAlias(): void
    {
        $delete = new DbDelete();
        $sql = $delete
            ->from('users', 'u')
            ->where('u.id')->equals(1)
            ->sql('sqlite', false);

        $expected = "DELETE FROM `users` `u` WHERE `u`.`id` = 1";
        $this->assertEquals($expected, $sql);
    }

    public function testDeleteWithMultipleConditions(): void
    {
        $delete = new DbDelete();
        $sql = $delete
            ->from('users')
            ->where('status')->equals('inactive')
            ->and('created_at')->lower('2020-01-01')
            ->sql('sqlite', false);

        $expected = "DELETE FROM `users` WHERE `status` = 'inactive' AND `created_at` < '2020-01-01'";
        $this->assertEquals($expected, $sql);
    }

    public function testDeleteWithOrCondition(): void
    {
        $delete = new DbDelete();
        $sql = $delete
            ->from('users')
            ->where('status')->equals('deleted')
            ->or('status')->equals('banned')
            ->sql('sqlite', false);

        $expected = "DELETE FROM `users` WHERE `status` = 'deleted' OR `status` = 'banned'";
        $this->assertEquals($expected, $sql);
    }

    public function testDeleteWithInCondition(): void
    {
        $delete = new DbDelete();
        $result = $delete
            ->from('users')
            ->where('status')->in(['deleted', 'banned', 'suspended'])
            ->sql('sqlite', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals("DELETE FROM `users` WHERE `status` IN (?, ?, ?)", $result->sql());
        $this->assertEquals(['deleted', 'banned', 'suspended'], $result->bindings());
    }

    public function testDeleteWithEmptyInCondition(): void
    {
        $delete = new DbDelete();
        $result = $delete
            ->from('users')
            ->where('id')->in([])
            ->sql('sqlite', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals('DELETE FROM `users` WHERE 1=0', $result->sql());
        $this->assertEmpty($result->bindings());
    }

    public function testDeleteWithEmptyNotInCondition(): void
    {
        $delete = new DbDelete();
        $result = $delete
            ->from('users')
            ->where('id')->notIn([])
            ->sql('sqlite', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals('DELETE FROM `users` WHERE 1=1', $result->sql());
        $this->assertEmpty($result->bindings());
    }

    public function testDeleteWithEmptyInAndOtherCondition(): void
    {
        $delete = new DbDelete();
        $result = $delete
            ->from('users')
            ->where('status')->equals('banned')
            ->and('id')->in([])
            ->sql('sqlite', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals('DELETE FROM `users` WHERE `status` = ? AND 1=0', $result->sql());
        $this->assertEquals(['banned'], $result->bindings());
    }

    public function testDeleteWithBetweenCondition(): void
    {
        $delete = new DbDelete();
        $sql = $delete
            ->from('logs')
            ->where('created_at')->between('2020-01-01', '2020-12-31')
            ->sql('sqlite', false);

        $expected = "DELETE FROM `logs` WHERE `created_at` BETWEEN '2020-01-01' AND '2020-12-31'";
        $this->assertEquals($expected, $sql);
    }

    public function testDeleteWithLikeCondition(): void
    {
        $delete = new DbDelete();
        $sql = $delete
            ->from('users')
            ->where('email')->like('%@spam.com')
            ->sql('sqlite', false);

        $expected = "DELETE FROM `users` WHERE `email` LIKE '%@spam.com'";
        $this->assertEquals($expected, $sql);
    }

    public function testDeleteWithIsNull(): void
    {
        $delete = new DbDelete();
        $sql = $delete
            ->from('users')
            ->where('deleted_at')->isNull()
            ->sql('sqlite', false);

        $expected = "DELETE FROM `users` WHERE `deleted_at` IS NULL";
        $this->assertEquals($expected, $sql);
    }

    public function testDeleteWithIsNotNull(): void
    {
        $delete = new DbDelete();
        $sql = $delete
            ->from('users')
            ->where('email_verified_at')->isNotNull()
            ->sql('sqlite', false);

        $expected = "DELETE FROM `users` WHERE `email_verified_at` IS NOT NULL";
        $this->assertEquals($expected, $sql);
    }

    public function testDeleteWithBrackets(): void
    {
        $delete = new DbDelete();
        $sql = $delete
            ->from('users')
            ->where('status', '(')->equals('active')
            ->and('age')->greater(18, ')')
            ->or('is_admin', '(')->equals(true, ')')
            ->sql('sqlite', false);

        $expected = "DELETE FROM `users` WHERE (`status` = 'active' AND `age` > 18) OR (`is_admin` = 1)";
        $this->assertEquals($expected, $sql);
    }

    public function testDeleteWithOrderByThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ORDER BY is not supported in SQLite DELETE statements');

        $delete = new DbDelete();
        $delete
            ->from('logs')
            ->where('level')->equals('debug')
            ->orderBy('created_at', 'ASC')
            ->sql('sqlite', false);
    }

    public function testDeleteWithLimitThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('LIMIT is not supported in SQLite DELETE statements');

        $delete = new DbDelete();
        $delete
            ->from('logs')
            ->where('level')->equals('info')
            ->limit(1000)
            ->sql('sqlite', false);
    }

    public function testDeleteWithOrderByAndLimitThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ORDER BY is not supported in SQLite DELETE statements');

        $delete = new DbDelete();
        $delete
            ->from('logs')
            ->where('created_at')->lower('2020-01-01')
            ->orderBy('created_at', 'ASC')
            ->limit(500)
            ->sql('sqlite', false);
    }

    public function testDeleteWithExists(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('orders')
            ->where('orders.user_id')->equals(Expression::raw('users.id'));

        $delete = new DbDelete();
        $sql = $delete
            ->from('users')
            ->where()->exists($subquery)
            ->sql('sqlite', false);

        $expected = "DELETE FROM `users` WHERE EXISTS (SELECT 1 FROM `orders` WHERE `orders`.`user_id` = users.id)";
        $this->assertEquals($expected, $sql);
    }

    public function testDeleteWithNotExists(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('orders')
            ->where('orders.user_id')->equals(Expression::raw('users.id'));

        $delete = new DbDelete();
        $sql = $delete
            ->from('users')
            ->where()->notExists($subquery)
            ->sql('sqlite', false);

        $expected = "DELETE FROM `users` WHERE NOT EXISTS (SELECT 1 FROM `orders` WHERE `orders`.`user_id` = users.id)";
        $this->assertEquals($expected, $sql);
    }

    public function testDeleteWithJsonCondition(): void
    {
        $delete = new DbDelete();
        $sql = $delete
            ->from('users')
            ->whereJson('metadata')->extract('$.status')->equals('inactive')
            ->sql('sqlite', false);

        $expected = "DELETE FROM `users` WHERE json_extract(`metadata`, '$.status') = 'inactive'";
        $this->assertEquals($expected, $sql);
    }

    public function testDeleteWithJsonContains(): void
    {
        $delete = new DbDelete();
        $result = $delete
            ->from('users')
            ->whereJson('preferences')->contains('dark_mode')
            ->sql('sqlite', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals(
            "DELETE FROM `users` WHERE EXISTS (SELECT 1 FROM json_each(`preferences`) WHERE value = ?)",
            $result->sql()
        );
        $this->assertEquals(['dark_mode'], $result->bindings());
    }

    public function testDeleteWithJsonLength(): void
    {
        $delete = new DbDelete();
        $sql = $delete
            ->from('users')
            ->whereJson('tags')->length()->greater(10)
            ->sql('sqlite', false);

        $expected = "DELETE FROM `users` WHERE json_array_length(`tags`) > 10";
        $this->assertEquals($expected, $sql);
    }

    public function testDeleteWithoutFromThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table name must be specified with from()');

        $delete = new DbDelete();
        $delete->sql('sqlite', false);
    }

    public function testDeleteWithInvalidBracketsThrowsException(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid brackets in query');

        $delete = new DbDelete();
        $delete
            ->from('users')
            ->where('status', '(')->equals('active')
            ->sql('sqlite', false);
    }

    public function testDeleteWithComplexConditions(): void
    {
        $delete = new DbDelete();
        $result = $delete
            ->from('users')
            ->where('status', '(')->equals('inactive')
            ->and('last_login')->lower('2020-01-01', ')')
            ->or('email_verified_at', '(')->isNull()
            ->and('created_at')->lower('2019-01-01', ')')
            ->sql('sqlite', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $expected = "DELETE FROM `users` WHERE (`status` = ? AND `last_login` < ?) OR (`email_verified_at` IS NULL AND `created_at` < ?)";
        $this->assertEquals($expected, $result->sql());
        $this->assertEquals(['inactive', '2020-01-01', '2019-01-01'], $result->bindings());
    }

    public function testDeleteWithSubquery(): void
    {
        $subquery = (new DbQuery())
            ->select('user_id')
            ->from('banned_users');

        $delete = new DbDelete();
        $result = $delete
            ->from('users')
            ->where('id')->in($subquery)
            ->sql('sqlite', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $expected = "DELETE FROM `users` WHERE `id` IN ?";
        $this->assertEquals($expected, $result->sql());
    }

    public function testDeleteWithInnerJoinThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JOIN is not supported in SQLite DELETE statements');

        $delete = new DbDelete();
        $delete
            ->from('user_sessions')
            ->innerJoin('users', 'user_sessions.user_id = users.id')
            ->where('users.status')->equals('deleted')
            ->sql('sqlite', false);
    }

    public function testDeleteWithLeftJoinThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JOIN is not supported in SQLite DELETE statements');

        $delete = new DbDelete();
        $delete
            ->from('orphaned_records', 'o')
            ->leftJoin('parent_table', 'o.parent_id = parent_table.id', 'p')
            ->where('p.id')->isNull()
            ->sql('sqlite', false);
    }
}
