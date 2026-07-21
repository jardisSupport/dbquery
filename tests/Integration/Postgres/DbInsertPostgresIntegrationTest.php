<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Integration\Postgres;

use JardisSupport\DbQuery\DbInsert;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Tests\Integration\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * PostgreSQL Integration Tests for DbInsert
 *
 * Tests actual INSERT query execution against PostgreSQL database
 */
class DbInsertPostgresIntegrationTest extends TestCase
{
    private DatabaseConnection $db;
    private PDO $connection;

    protected function setUp(): void
    {
        $this->db = new DatabaseConnection();
        $this->connection = $this->db->getPostgresConnection();
        $this->db->createTestTable($this->connection, 'postgres', 'users');
    }

    protected function tearDown(): void
    {
        $this->db->dropTestTable($this->connection, 'users');
    }

    public function testSimpleInsertWithSet(): void
    {
        $insert = new DbInsert();
        $prepared = $insert
            ->into('users')
            ->set([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'status' => 'active',
                'age' => 30
            ])
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $this->assertEquals(1, $this->db->countRows($this->connection, 'users'));

        $rows = $this->db->getAllRows($this->connection, 'users');
        $this->assertEquals('John Doe', $rows[0]['name']);
        $this->assertEquals('john@example.com', $rows[0]['email']);
        $this->assertEquals(30, $rows[0]['age']);
    }

    public function testInsertWithFieldsAndValues(): void
    {
        $insert = new DbInsert();
        $prepared = $insert
            ->into('users')
            ->fields('name', 'email', 'status', 'age')
            ->values('Jane Smith', 'jane@example.com', 'active', 25)
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $this->assertEquals(1, $this->db->countRows($this->connection, 'users'));

        $rows = $this->db->getAllRows($this->connection, 'users');
        $this->assertEquals('Jane Smith', $rows[0]['name']);
        $this->assertEquals(25, $rows[0]['age']);
    }

    public function testMultiRowInsert(): void
    {
        $insert = new DbInsert();
        $prepared = $insert
            ->into('users')
            ->fields('name', 'email', 'status', 'age')
            ->values('John Doe', 'john@example.com', 'active', 30)
            ->values('Jane Smith', 'jane@example.com', 'active', 25)
            ->values('Bob Johnson', 'bob@example.com', 'inactive', 35)
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $this->assertEquals(3, $this->db->countRows($this->connection, 'users'));
    }

    public function testOnConflictDoNothing(): void
    {
        // First insert
        $this->db->insertTestData($this->connection, 'users', [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'status' => 'active', 'age' => 30]
        ]);

        // Try to insert duplicate email with ON CONFLICT DO NOTHING
        $insert = new DbInsert();
        $prepared = $insert
            ->into('users')
            ->set([
                'name' => 'John Different',
                'email' => 'john@example.com',
                'status' => 'active',
                'age' => 40
            ])
            ->onConflict('email')->doNothing()
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        // Should still be only 1 row
        $this->assertEquals(1, $this->db->countRows($this->connection, 'users'));

        // Original data should remain unchanged
        $rows = $this->db->getAllRows($this->connection, 'users');
        $this->assertEquals('John Doe', $rows[0]['name']);
        $this->assertEquals(30, $rows[0]['age']);
    }

    public function testOnConflictDoUpdate(): void
    {
        // First insert
        $this->db->insertTestData($this->connection, 'users', [
            ['name' => 'John Doe', 'email' => 'john@example.com', 'status' => 'active', 'age' => 30]
        ]);

        // Insert with ON CONFLICT DO UPDATE
        $insert = new DbInsert();
        $prepared = $insert
            ->into('users')
            ->set([
                'name' => 'John Updated',
                'email' => 'john@example.com',
                'status' => 'inactive',
                'age' => 40
            ])
            ->onConflict('email')->doUpdate([
                'name' => 'John Updated',
                'age' => 40,
                'status' => 'inactive'
            ])
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        // Should still be only 1 row
        $this->assertEquals(1, $this->db->countRows($this->connection, 'users'));

        // Data should be updated
        $rows = $this->db->getAllRows($this->connection, 'users');
        $this->assertEquals('John Updated', $rows[0]['name']);
        $this->assertEquals(40, $rows[0]['age']);
        $this->assertEquals('inactive', $rows[0]['status']);
    }

    public function testInsertFromSelect(): void
    {
        // Create a temp table with source data
        $this->connection->exec("
            CREATE TEMPORARY TABLE temp_users (
                name VARCHAR(255),
                email VARCHAR(255),
                status VARCHAR(50),
                age INT
            )
        ");

        $this->connection->exec("
            INSERT INTO temp_users (name, email, status, age) VALUES
            ('Alice', 'alice@example.com', 'active', 28),
            ('Bob', 'bob@example.com', 'active', 32)
        ");

        // Insert from SELECT
        $selectQuery = new DbQuery();
        $selectQuery
            ->select('name, email, status, age')
            ->from('temp_users')
            ->where('status')->equals('active');

        $insert = new DbInsert();
        $prepared = $insert
            ->into('users')
            ->fields('name', 'email', 'status', 'age')
            ->fromSelect($selectQuery)
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $this->assertEquals(2, $this->db->countRows($this->connection, 'users'));

        $rows = $this->db->getAllRows($this->connection, 'users');
        $this->assertEquals('Alice', $rows[0]['name']);
        $this->assertEquals('Bob', $rows[1]['name']);
    }

    public function testInsertWithNullValue(): void
    {
        $insert = new DbInsert();
        $prepared = $insert
            ->into('users')
            ->set([
                'name' => 'No Age User',
                'email' => 'noage@example.com',
                'status' => 'active',
                'age' => null
            ])
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $rows = $this->db->getAllRows($this->connection, 'users');
        $this->assertEquals('No Age User', $rows[0]['name']);
        $this->assertNull($rows[0]['age']);
    }

    public function testInsertReturnsLastInsertId(): void
    {
        $insert = new DbInsert();
        $prepared = $insert
            ->into('users')
            ->set([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'status' => 'active',
                'age' => 25
            ])
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $lastId = $this->connection->lastInsertId('users_id_seq');
        $this->assertGreaterThan(0, (int)$lastId);
    }

    public function testOnConflictDoUpdateWithMultipleColumns(): void
    {
        // First insert
        $this->db->insertTestData($this->connection, 'users', [
            ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'status' => 'active', 'age' => 25]
        ]);

        // Insert with ON CONFLICT DO UPDATE on email
        $insert = new DbInsert();
        $prepared = $insert
            ->into('users')
            ->set([
                'name' => 'Jane Updated',
                'email' => 'jane@example.com',
                'status' => 'inactive',
                'age' => 26
            ])
            ->onConflict('email')->doUpdate([
                'name' => 'Jane Updated',
                'age' => 26,
                'status' => 'inactive'
            ])
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        // Should still be only 1 row
        $this->assertEquals(1, $this->db->countRows($this->connection, 'users'));

        // Data should be updated
        $rows = $this->db->getAllRows($this->connection, 'users');
        $this->assertEquals('Jane Updated', $rows[0]['name']);
        $this->assertEquals(26, $rows[0]['age']);
        $this->assertEquals('inactive', $rows[0]['status']);
    }
}
