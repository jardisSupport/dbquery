<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Command\Insert;

use InvalidArgumentException;
use JardisSupport\DbQuery\DbInsert;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Contract\DbQuery\DbInsertBuilderInterface;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use PHPUnit\Framework\TestCase;

/**
 * SQLite INSERT Tests
 *
 * Tests: INSERT INTO, columns, values, set, fromSelect (SQLite dialect)
 */
class DbInsertSqliteTest extends TestCase
{
    public function testConstructor(): void
    {
        $insert = new DbInsert();
        $this->assertInstanceOf(DbInsertBuilderInterface::class, $insert);
    }

    public function testSimpleInsertWithSet(): void
    {
        $insert = new DbInsert();
        $sql = $insert
            ->into('users')
            ->set([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'status' => 'active'
            ])
            ->sql('sqlite', false);

        $expected = "INSERT INTO `users` (`name`, `email`, `status`) VALUES ('John Doe', 'john@example.com', 'active')";
        $this->assertEquals($expected, $sql);
    }

    public function testSimpleInsertWithSetPrepared(): void
    {
        $insert = new DbInsert();
        $result = $insert
            ->into('users')
            ->set([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'status' => 'active'
            ])
            ->sql('sqlite', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals("INSERT INTO `users` (`name`, `email`, `status`) VALUES (?, ?, ?)", $result->sql());
        $this->assertEquals(['John Doe', 'john@example.com', 'active'], $result->bindings());
    }

    public function testInsertWithBooleanValues(): void
    {
        $insert = new DbInsert();
        $sql = $insert
            ->into('settings')
            ->set([
                'is_active' => true,
                'is_deleted' => false
            ])
            ->sql('sqlite', false);

        // SQLite uses 1/0 for booleans
        $expected = "INSERT INTO `settings` (`is_active`, `is_deleted`) VALUES (1, 0)";
        $this->assertEquals($expected, $sql);
    }

    public function testMultiRowInsert(): void
    {
        $insert = new DbInsert();
        $sql = $insert
            ->into('users')
            ->fields('name', 'email')
            ->values('John Doe', 'john@example.com')
            ->values('Jane Smith', 'jane@example.com')
            ->sql('sqlite', false);

        $expected = "INSERT INTO `users` (`name`, `email`) VALUES ('John Doe', 'john@example.com'), ('Jane Smith', 'jane@example.com')";
        $this->assertEquals($expected, $sql);
    }

    public function testMultiRowInsertPrepared(): void
    {
        $insert = new DbInsert();
        $result = $insert
            ->into('users')
            ->fields('name', 'email')
            ->values('John Doe', 'john@example.com')
            ->values('Jane Smith', 'jane@example.com')
            ->sql('sqlite', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals("INSERT INTO `users` (`name`, `email`) VALUES (?, ?), (?, ?)", $result->sql());
        $this->assertEquals(['John Doe', 'john@example.com', 'Jane Smith', 'jane@example.com'], $result->bindings());
    }

    public function testInsertWithNullValue(): void
    {
        $insert = new DbInsert();
        $sql = $insert
            ->into('users')
            ->set([
                'name' => 'John Doe',
                'email' => null,
                'age' => 30
            ])
            ->sql('sqlite', false);

        $expected = "INSERT INTO `users` (`name`, `email`, `age`) VALUES ('John Doe', NULL, 30)";
        $this->assertEquals($expected, $sql);
    }

    public function testInsertFromSelect(): void
    {
        $selectQuery = (new DbQuery())
            ->select('name, email')
            ->from('temp_users')
            ->where('status')->equals('pending');

        $insert = new DbInsert();
        $sql = $insert
            ->into('users')
            ->fields('name', 'email')
            ->fromSelect($selectQuery)
            ->sql('sqlite', false);

        $expected = "INSERT INTO `users` (`name`, `email`) SELECT name, email FROM `temp_users` WHERE status = 'pending'";
        $this->assertEquals($expected, $sql);
    }

    public function testInsertFromSelectPrepared(): void
    {
        $selectQuery = (new DbQuery())
            ->select('name, email')
            ->from('temp_users')
            ->where('status')->equals('pending');

        $insert = new DbInsert();
        $result = $insert
            ->into('users')
            ->fields('name', 'email')
            ->fromSelect($selectQuery)
            ->sql('sqlite', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals("INSERT INTO `users` (`name`, `email`) SELECT name, email FROM `temp_users` WHERE status = ?", $result->sql());
        $this->assertEquals(['pending'], $result->bindings());
    }

    public function testThrowsExceptionWhenUsingWildcardInColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Wildcard \'*\' is not allowed. Specify explicit column names');

        $selectQuery = (new DbQuery())->select('name, email')->from('temp_users');

        $insert = new DbInsert();
        $insert
            ->into('users')
            ->fields('*')
            ->fromSelect($selectQuery)
            ->sql('sqlite');
    }

    public function testThrowsExceptionWhenUsingWildcardInSelectQuery(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Wildcard \'*\' in SELECT subquery is not allowed');

        $selectQuery = (new DbQuery())->select('*')->from('temp_users');

        $insert = new DbInsert();
        $insert
            ->into('users')
            ->fields('name', 'email')
            ->fromSelect($selectQuery)
            ->sql('sqlite');
    }

    public function testThrowsExceptionWhenFromSelectWithoutColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Column names must be specified');

        $selectQuery = (new DbQuery())->select('name, email')->from('temp_users');

        $insert = new DbInsert();
        $insert
            ->into('users')
            ->fromSelect($selectQuery)
            ->sql('sqlite');
    }

    public function testThrowsExceptionWhenColumnsNotSpecified(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Column names must be specified with columns() before adding values');

        $insert = new DbInsert();
        $insert
            ->into('users')
            ->values('John Doe', 'john@example.com', 'active');
    }

    public function testThrowsExceptionWhenValueCountMismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Number of values (2) does not match number of columns (3)');

        $insert = new DbInsert();
        $insert
            ->into('users')
            ->fields('name', 'email', 'status')
            ->values('John Doe', 'john@example.com'); // Missing status
    }

    public function testFluentInterface(): void
    {
        $insert = new DbInsert();
        $result = $insert->into('users');
        $this->assertSame($insert, $result);

        $result = $insert->fields('name', 'email');
        $this->assertSame($insert, $result);

        $result = $insert->values('John', 'john@example.com');
        $this->assertSame($insert, $result);
    }

    public function testOnConflictDoUpdate(): void
    {
        $sql = (new DbInsert())
            ->into('users')
            ->set(['email' => 'john@example.com', 'name' => 'John', 'status' => 'active'])
            ->onConflict('email')
            ->doUpdate(['name' => 'John Updated', 'status' => 'active'])
            ->sql('sqlite', false);

        $expected = "INSERT INTO `users` (`email`, `name`, `status`) VALUES ('john@example.com', 'John', 'active') ON CONFLICT (`email`) DO UPDATE SET `name` = 'John Updated', `status` = 'active'";
        $this->assertEquals($expected, $sql);
    }

    public function testOnConflictDoUpdatePrepared(): void
    {
        $result = (new DbInsert())
            ->into('users')
            ->set(['email' => 'john@example.com', 'name' => 'John'])
            ->onConflict('email')
            ->doUpdate(['name' => 'John Updated'])
            ->sql('sqlite', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals("INSERT INTO `users` (`email`, `name`) VALUES (?, ?) ON CONFLICT (`email`) DO UPDATE SET `name` = ?", $result->sql());
        $this->assertEquals(['john@example.com', 'John', 'John Updated'], $result->bindings());
    }

    public function testOnConflictDoNothing(): void
    {
        $sql = (new DbInsert())
            ->into('users')
            ->set(['email' => 'john@example.com', 'name' => 'John'])
            ->onConflict('email')
            ->doNothing()
            ->sql('sqlite', false);

        $expected = "INSERT INTO `users` (`email`, `name`) VALUES ('john@example.com', 'John') ON CONFLICT (`email`) DO NOTHING";
        $this->assertEquals($expected, $sql);
    }

    public function testOnConflictDoNothingPrepared(): void
    {
        $result = (new DbInsert())
            ->into('users')
            ->set(['email' => 'john@example.com', 'name' => 'John'])
            ->onConflict('email')
            ->doNothing()
            ->sql('sqlite', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals("INSERT INTO `users` (`email`, `name`) VALUES (?, ?) ON CONFLICT (`email`) DO NOTHING", $result->sql());
        $this->assertEquals(['john@example.com', 'John'], $result->bindings());
    }

    public function testOnConflictMultipleColumns(): void
    {
        $sql = (new DbInsert())
            ->into('user_settings')
            ->set(['user_id' => 1, 'key' => 'theme', 'value' => 'dark'])
            ->onConflict('user_id', 'key')
            ->doUpdate(['value' => 'dark'])
            ->sql('sqlite', false);

        $expected = "INSERT INTO `user_settings` (`user_id`, `key`, `value`) VALUES (1, 'theme', 'dark') ON CONFLICT (`user_id`, `key`) DO UPDATE SET `value` = 'dark'";
        $this->assertEquals($expected, $sql);
    }

    public function testOnConflictWithExpression(): void
    {
        $sql = (new DbInsert())
            ->into('products')
            ->set(['sku' => 'ABC-123', 'stock' => 10])
            ->onConflict('sku')
            ->doUpdate([
                'stock' => new \JardisSupport\DbQuery\Data\Expression('stock + 10'),
                'updated_at' => new \JardisSupport\DbQuery\Data\Expression('CURRENT_TIMESTAMP')
            ])
            ->sql('sqlite', false);

        $expected = "INSERT INTO `products` (`sku`, `stock`) VALUES ('ABC-123', 10) ON CONFLICT (`sku`) DO UPDATE SET `stock` = stock + 10, `updated_at` = CURRENT_TIMESTAMP";
        $this->assertEquals($expected, $sql);
    }

    public function testOrIgnoreUsesInsertOrIgnore(): void
    {
        $sql = (new DbInsert())
            ->into('users')
            ->set(['email' => 'john@example.com', 'name' => 'John'])
            ->orIgnore()
            ->sql('sqlite', false);

        $expected = "INSERT OR IGNORE INTO `users` (`email`, `name`) VALUES ('john@example.com', 'John')";
        $this->assertEquals($expected, $sql);
    }

    public function testReplaceUsesInsertOrReplace(): void
    {
        $sql = (new DbInsert())
            ->into('users')
            ->set(['email' => 'john@example.com', 'name' => 'John'])
            ->replace()
            ->sql('sqlite', false);

        $expected = "INSERT OR REPLACE INTO `users` (`email`, `name`) VALUES ('john@example.com', 'John')";
        $this->assertEquals($expected, $sql);
    }
}
