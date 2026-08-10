<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Sqlite;

use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * SQLite Basic Tests
 *
 * Tests: Constructor, SELECT, FROM, DISTINCT, Alias
 */
/*
 * SQL-Pins am 2026-08-10 an das Auto-Quoting einfacher Identifier angepasst:
 * WHERE/AND/OR-, HAVING-, ORDER-BY-, GROUP-BY-Felder und die SELECT-Feldliste
 * quoten `ident` bzw. `alias.ident` jetzt dialektgerecht (MySQL/SQLite: Backtick,
 * PostgreSQL: Double-Quote). Ausdruecke, '*', bereits Gequotetes und
 * Expression::raw() bleiben byte-identisch roh. Alle Aenderungen in dieser
 * Datei sind reine Quote-Zeichen-Diffs in erwarteten SQL-Strings.
 */
class DbQuerySqliteBasicTest extends TestCase
{
    public function testConstructor(): void
    {
        $query = new DbQuery();
        $this->assertInstanceOf(DbQueryBuilderInterface::class, $query);
    }

    public function testSelectWithoutParameters(): void
    {
        $query = new DbQuery();
        $result = $query->select();
        $this->assertSame($query, $result);

        $sql = $query->from('users')->sql('sqlite', false);
        $this->assertEquals('SELECT * FROM `users`', $sql);
    }

    public function testSelectWithSingleField(): void
    {
        $query = new DbQuery();
        $sql = $query->select('id')->from('users')->sql('sqlite', false);
        $this->assertEquals('SELECT `id` FROM `users`', $sql);
    }

    public function testSelectWithMultipleFields(): void
    {
        $query = new DbQuery();
        $sql = $query->select('id, name, email')->from('users')->sql('sqlite', false);
        $this->assertEquals('SELECT `id`, `name`, `email` FROM `users`', $sql);
    }

    public function testDistinct(): void
    {
        $query = new DbQuery();
        $result = $query->distinct(true);
        $this->assertSame($query, $result);

        $sql = $query->select('name')->from('users')->sql('sqlite', false);
        $this->assertEquals('SELECT DISTINCT `name` FROM `users`', $sql);
    }

    public function testFromWithTableName(): void
    {
        $query = new DbQuery();
        $result = $query->from('users');
        $this->assertSame($query, $result);

        $sql = $query->select('*')->sql('sqlite', false);
        $this->assertEquals('SELECT * FROM `users`', $sql);
    }

    public function testFromWithAlias(): void
    {
        $query = new DbQuery();
        $sql = $query->select('*')->from('users', 'u')->sql('sqlite', false);
        $this->assertEquals('SELECT * FROM `users` `u`', $sql);
    }

    public function testAlias(): void
    {
        $query = new DbQuery();

        $sql = $query->select('*')
            ->from('users', 'u')
            ->sql('sqlite', false);

        $this->assertEquals('SELECT * FROM `users` `u`', $sql);
    }
}
