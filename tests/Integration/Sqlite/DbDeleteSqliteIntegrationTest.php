<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Integration\Sqlite;

use JardisSupport\DbQuery\DbDelete;
use JardisSupport\DbQuery\Tests\Integration\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * SQLite Integration Tests for DbDelete
 *
 * Tests actual DELETE query execution against SQLite database
 */
class DbDeleteSqliteIntegrationTest extends TestCase
{
    private DatabaseConnection $db;
    private PDO $connection;

    protected function setUp(): void
    {
        $this->db = new DatabaseConnection();
        $this->connection = $this->db->getSqliteConnection();
        $this->db->createTestTable($this->connection, 'sqlite', 'users');

        // Insert test data
        $this->db->insertTestData($this->connection, 'users', [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'status' => 'active', 'age' => 30],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'status' => 'active', 'age' => 25],
            ['name' => 'Bob Johnson', 'email' => 'bob@example.com', 'status' => 'inactive', 'age' => 35],
            ['name' => 'Alice Brown', 'email' => 'alice@example.com', 'status' => 'active', 'age' => 28],
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->dropTestTable($this->connection, 'users');
    }

    public function testSimpleDelete(): void
    {
        $delete = new DbDelete();
        $prepared = $delete
            ->from('users')
            ->where('name')->equals('John Doe')
            ->sql('sqlite');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(1, $affectedRows);

        // Verify the row was deleted
        $this->assertEquals(3, $this->db->countRows($this->connection, 'users'));

        $stmt = $this->connection->query("SELECT COUNT(*) as cnt FROM users WHERE name = 'John Doe'");
        $result = $stmt->fetch();
        $this->assertEquals(0, $result['cnt']);
    }

    public function testDeleteMultipleRows(): void
    {
        $delete = new DbDelete();
        $prepared = $delete
            ->from('users')
            ->where('status')->equals('active')
            ->sql('sqlite');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(3, $affectedRows);

        // Verify only inactive user remains
        $this->assertEquals(1, $this->db->countRows($this->connection, 'users'));

        $rows = $this->db->getAllRows($this->connection, 'users');
        $this->assertEquals('Bob Johnson', $rows[0]['name']);
    }

    public function testDeleteWithMultipleConditions(): void
    {
        $delete = new DbDelete();
        $prepared = $delete
            ->from('users')
            ->where('status')->equals('active')
            ->and('age')->lower(28)
            ->sql('sqlite');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(1, $affectedRows); // Only Jane (25)

        $this->assertEquals(3, $this->db->countRows($this->connection, 'users'));
    }

    // Note: LIMIT in DELETE is not supported in SQLite without special compilation flags
    /*
    public function testDeleteWithLimit(): void
    {
        $delete = new DbDelete();
        $prepared = $delete
            ->from('users')
            ->where('status')->equals('active')
            ->limit(2)
            ->sql('sqlite');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(2, $affectedRows);

        // 2 rows should remain (1 inactive + 1 active)
        $this->assertEquals(2, $this->db->countRows($this->connection, 'users'));
    }
    */

    // Note: ORDER BY and LIMIT in DELETE are not supported in SQLite without special compilation flags
    /*
    public function testDeleteWithOrderByAndLimit(): void
    {
        $delete = new DbDelete();
        $prepared = $delete
            ->from('users')
            ->where('status')->equals('active')
            ->orderBy('age', 'ASC')
            ->limit(1)
            ->sql('sqlite');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(1, $affectedRows);

        // Verify Jane (youngest active) was deleted
        $this->assertEquals(3, $this->db->countRows($this->connection, 'users'));

        $stmt = $this->connection->query("SELECT COUNT(*) as cnt FROM users WHERE name = 'Jane Smith'");
        $result = $stmt->fetch();
        $this->assertEquals(0, $result['cnt']);
    }
    */

    public function testDeleteWithInCondition(): void
    {
        $delete = new DbDelete();
        $prepared = $delete
            ->from('users')
            ->where('name')->in(['John Doe', 'Bob Johnson'])
            ->sql('sqlite');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(2, $affectedRows);

        $this->assertEquals(2, $this->db->countRows($this->connection, 'users'));
    }

    public function testDeleteWithBetweenCondition(): void
    {
        $delete = new DbDelete();
        $prepared = $delete
            ->from('users')
            ->where('age')->between(25, 30)
            ->sql('sqlite');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(3, $affectedRows); // Jane (25), Alice (28), John (30)

        // Only Bob (35) should remain
        $this->assertEquals(1, $this->db->countRows($this->connection, 'users'));
    }

    public function testDeleteNoRowsMatched(): void
    {
        $delete = new DbDelete();
        $prepared = $delete
            ->from('users')
            ->where('name')->equals('Non Existent User')
            ->sql('sqlite');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(0, $affectedRows);

        // All rows should still exist
        $this->assertEquals(4, $this->db->countRows($this->connection, 'users'));
    }

    public function testDeleteWithLikeCondition(): void
    {
        $delete = new DbDelete();
        $prepared = $delete
            ->from('users')
            ->where('name')->like('%John%')
            ->sql('sqlite');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(2, $affectedRows); // John Doe and Bob Johnson

        $this->assertEquals(2, $this->db->countRows($this->connection, 'users'));
    }

    public function testDeleteWithOrCondition(): void
    {
        $delete = new DbDelete();
        $prepared = $delete
            ->from('users')
            ->where('status')->equals('inactive')
            ->or('age')->lower(26)
            ->sql('sqlite');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(2, $affectedRows); // Bob (inactive) and Jane (age 25)

        $this->assertEquals(2, $this->db->countRows($this->connection, 'users'));
    }

    public function testDeleteReturnsAffectedRows(): void
    {
        $delete = new DbDelete();
        $prepared = $delete
            ->from('users')
            ->where('age')->greaterEquals(30)
            ->sql('sqlite');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(2, $affectedRows); // John (30) and Bob (35)

        $this->assertEquals(2, $this->db->countRows($this->connection, 'users'));
    }

    public function testDeleteAll(): void
    {
        // Delete without WHERE clause - should delete all rows
        $delete = new DbDelete();
        $prepared = $delete
            ->from('users')
            ->sql('sqlite');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(4, $affectedRows);

        $this->assertEquals(0, $this->db->countRows($this->connection, 'users'));
    }

    public function testDeleteWithEmptyInConditionDeletesNothing(): void
    {
        $delete = new DbDelete();
        $prepared = $delete
            ->from('users')
            ->where('id')->in([])
            ->sql('sqlite');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $this->assertEquals(0, $stmt->rowCount());
        $this->assertEquals(4, $this->db->countRows($this->connection, 'users'));
    }
}
