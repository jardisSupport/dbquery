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
 * MySQL INSERT Tests
 *
 * Tests: INSERT INTO, columns, values, set, fromSelect
 */
class DbInsertMySqlTest extends TestCase
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
            ->sql('mysql', false);

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
            ->sql('mysql', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals("INSERT INTO `users` (`name`, `email`, `status`) VALUES (?, ?, ?)", $result->sql());
        $this->assertEquals(['John Doe', 'john@example.com', 'active'], $result->bindings());
    }

    public function testInsertWithColumnsAndValues(): void
    {
        $insert = new DbInsert();
        $sql = $insert
            ->into('users')
            ->fields('name', 'email', 'status')
            ->values('John Doe', 'john@example.com', 'active')
            ->sql('mysql', false);

        $expected = "INSERT INTO `users` (`name`, `email`, `status`) VALUES ('John Doe', 'john@example.com', 'active')";
        $this->assertEquals($expected, $sql);
    }

    public function testInsertWithColumnsAndValuesPrepared(): void
    {
        $insert = new DbInsert();
        $result = $insert
            ->into('users')
            ->fields('name', 'email', 'status')
            ->values('John Doe', 'john@example.com', 'active')
            ->sql('mysql', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals("INSERT INTO `users` (`name`, `email`, `status`) VALUES (?, ?, ?)", $result->sql());
        $this->assertEquals(['John Doe', 'john@example.com', 'active'], $result->bindings());
    }

    public function testMultiRowInsert(): void
    {
        $insert = new DbInsert();
        $sql = $insert
            ->into('users')
            ->fields('name', 'email')
            ->values('John Doe', 'john@example.com')
            ->values('Jane Smith', 'jane@example.com')
            ->values('Bob Wilson', 'bob@example.com')
            ->sql('mysql', false);

        $expected = "INSERT INTO `users` (`name`, `email`) VALUES ('John Doe', 'john@example.com'), ('Jane Smith', 'jane@example.com'), ('Bob Wilson', 'bob@example.com')";
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
            ->sql('mysql', true);

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
            ->sql('mysql', false);

        $expected = "INSERT INTO `users` (`name`, `email`, `age`) VALUES ('John Doe', NULL, 30)";
        $this->assertEquals($expected, $sql);
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
            ->sql('mysql', false);

        $expected = "INSERT INTO `settings` (`is_active`, `is_deleted`) VALUES (1, 0)";
        $this->assertEquals($expected, $sql);
    }

    public function testInsertWithNumericValues(): void
    {
        $insert = new DbInsert();
        $sql = $insert
            ->into('products')
            ->set([
                'name' => 'Widget',
                'price' => 19.99,
                'quantity' => 100
            ])
            ->sql('mysql', false);

        $expected = "INSERT INTO `products` (`name`, `price`, `quantity`) VALUES ('Widget', 19.99, 100)";
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
            ->sql('mysql', false);

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
            ->sql('mysql', true);

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
            ->sql('mysql');
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
            ->values('test', 'email@test.de')
            ->fromSelect($selectQuery)
            ->sql('mysql');
    }

    public function testThrowsExceptionWhenColumnsNotSpecified(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Column names must be specified with columns() before adding values');

        $insert = new DbInsert();
        $insert
            ->into('users')
            ->values('John Doe', 'john@example.com', 'active')
            ->sql('mysql', false);
    }

    public function testThrowsExceptionWhenTableNotSpecified(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table name must be specified');

        $insert = new DbInsert();
        $insert->set(['name' => 'John'])->sql('mysql');
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

    public function testSetOverwritesPreviousData(): void
    {
        $insert = new DbInsert();
        $sql = $insert
            ->into('users')
            ->set(['name' => 'John', 'email' => 'john@example.com'])
            ->set(['name' => 'Jane', 'email' => 'jane@example.com'])
            ->sql('mysql', false);

        // Second set() should overwrite the first
        $expected = "INSERT INTO `users` (`name`, `email`) VALUES ('Jane', 'jane@example.com')";
        $this->assertEquals($expected, $sql);
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

    public function testOrIgnore(): void
    {
        $sql = (new DbInsert())
            ->into('users')
            ->set(['name' => 'John', 'email' => 'john@example.com'])
            ->orIgnore()
            ->sql('mysql', false);

        $expected = "INSERT IGNORE INTO `users` (`name`, `email`) VALUES ('John', 'john@example.com')";
        $this->assertEquals($expected, $sql);
    }

    public function testReplace(): void
    {
        $sql = (new DbInsert())
            ->into('users')
            ->set(['name' => 'John', 'email' => 'john@example.com'])
            ->replace()
            ->sql('mysql', false);

        $expected = "REPLACE INTO `users` (`name`, `email`) VALUES ('John', 'john@example.com')";
        $this->assertEquals($expected, $sql);
    }

    public function testOnDuplicateKeyUpdate(): void
    {
        $sql = (new DbInsert())
            ->into('users')
            ->set(['name' => 'John', 'email' => 'john@example.com'])
            ->onDuplicateKeyUpdate('name', 'Updated Name')
            ->sql('mysql', false);

        $expected = "INSERT INTO `users` (`name`, `email`) VALUES ('John', 'john@example.com') ON DUPLICATE KEY UPDATE `name` = 'Updated Name'";
        $this->assertEquals($expected, $sql);
    }

    public function testOnConflictDoesNothingForMySQL(): void
    {
        // MySQL doesn't support ON CONFLICT syntax, so it should be ignored
        $sql = (new DbInsert())
            ->into('users')
            ->set(['name' => 'John', 'email' => 'john@example.com'])
            ->onConflict('email')
            ->doUpdate(['name' => 'Updated Name'])
            ->sql('mysql', false);

        // Should produce normal INSERT without ON CONFLICT clause
        $expected = "INSERT INTO `users` (`name`, `email`) VALUES ('John', 'john@example.com')";
        $this->assertEquals($expected, $sql);
    }

    public function testOnConflictDoNothingForMySQL(): void
    {
        // MySQL doesn't support ON CONFLICT syntax
        $sql = (new DbInsert())
            ->into('users')
            ->set(['name' => 'John', 'email' => 'john@example.com'])
            ->onConflict('email')
            ->doNothing()
            ->sql('mysql', false);

        // Should produce normal INSERT without ON CONFLICT clause
        $expected = "INSERT INTO `users` (`name`, `email`) VALUES ('John', 'john@example.com')";
        $this->assertEquals($expected, $sql);
    }
}
