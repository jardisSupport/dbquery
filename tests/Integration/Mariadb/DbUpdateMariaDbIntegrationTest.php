<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Integration\Mariadb;

use JardisSupport\DbQuery\DbUpdate;
use JardisSupport\DbQuery\Tests\Integration\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * MariaDB Integration Tests for DbUpdate
 *
 * Tests actual UPDATE query execution against MariaDB database
 */
class DbUpdateMariaDbIntegrationTest extends TestCase
{
    private DatabaseConnection $db;
    private PDO $connection;

    protected function setUp(): void
    {
        $this->db = new DatabaseConnection();
        $this->connection = $this->db->getMariaDbConnection();
        $this->db->createTestTable($this->connection, 'mariadb', 'users');

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

    public function testSimpleUpdate(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->setMultiple(['status' => 'updated'])
            ->where('name')->equals('John Doe')
            ->sql('mariadb');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(1, $affectedRows);

        // Verify the update
        $stmt = $this->connection->query("SELECT status FROM users WHERE name = 'John Doe'");
        $result = $stmt->fetch();
        $this->assertEquals('updated', $result['status']);
    }

    public function testUpdateMultipleFields(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->setMultiple([
                'status' => 'updated',
                'age' => 31
            ])
            ->where('name')->equals('John Doe')
            ->sql('mariadb');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        // Verify the updates
        $stmt = $this->connection->query("SELECT status, age FROM users WHERE name = 'John Doe'");
        $result = $stmt->fetch();
        $this->assertEquals('updated', $result['status']);
        $this->assertEquals(31, $result['age']);
    }

    public function testUpdateMultipleRows(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->setMultiple(['status' => 'suspended'])
            ->where('status')->equals('active')
            ->sql('mariadb');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(3, $affectedRows);

        // Verify all active users are now suspended
        $count = $this->connection->query("SELECT COUNT(*) as cnt FROM users WHERE status = 'suspended'")->fetch();
        $this->assertEquals(3, $count['cnt']);
    }

    public function testUpdateWithMultipleConditions(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->setMultiple(['status' => 'senior'])
            ->where('status')->equals('active')
            ->and('age')->greaterEquals(30)
            ->sql('mariadb');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(1, $affectedRows);

        // Verify only John Doe (age 30) was updated
        $stmt = $this->connection->query("SELECT name FROM users WHERE status = 'senior'");
        $result = $stmt->fetch();
        $this->assertEquals('John Doe', $result['name']);
    }

    public function testUpdateWithLimit(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->setMultiple(['status' => 'limited'])
            ->where('status')->equals('active')
            ->limit(2)
            ->sql('mariadb');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(2, $affectedRows);

        // Verify only 2 rows were updated
        $count = $this->connection->query("SELECT COUNT(*) as cnt FROM users WHERE status = 'limited'")->fetch();
        $this->assertEquals(2, $count['cnt']);
    }

    public function testUpdateWithOrderByAndLimit(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->setMultiple(['status' => 'youngest'])
            ->where('status')->equals('active')
            ->orderBy('age', 'ASC')
            ->limit(1)
            ->sql('mariadb');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(1, $affectedRows);

        // Verify only Jane Smith (youngest active, age 25) was updated
        $stmt = $this->connection->query("SELECT name, age FROM users WHERE status = 'youngest'");
        $result = $stmt->fetch();
        $this->assertEquals('Jane Smith', $result['name']);
        $this->assertEquals(25, $result['age']);
    }

    public function testUpdateWithJoin(): void
    {
        // Create orders table
        $this->db->createOrdersTable($this->connection, 'mariadb', 'orders');
        $this->db->insertTestData($this->connection, 'orders', [
            ['user_id' => 1, 'product' => 'Laptop', 'amount' => 1200.00],
            ['user_id' => 1, 'product' => 'Mouse', 'amount' => 25.00],
            ['user_id' => 2, 'product' => 'Keyboard', 'amount' => 75.00],
        ]);

        // Update users who have orders > 1000
        $update = new DbUpdate();
        $prepared = $update
            ->table('users', 'u')
            ->innerJoin('orders', 'u.id = o.user_id', 'o')
            ->setMultiple(['status' => 'premium'])
            ->where('o.amount')->greater(1000)
            ->sql('mariadb');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        // Verify user with expensive order was updated
        $stmt = $this->connection->query("SELECT COUNT(*) as cnt FROM users WHERE status = 'premium'");
        $result = $stmt->fetch();
        $this->assertGreaterThan(0, $result['cnt']);

        $this->db->dropTestTable($this->connection, 'orders');
    }

    public function testUpdateWithInCondition(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->setMultiple(['status' => 'selected'])
            ->where('name')->in(['John Doe', 'Jane Smith'])
            ->sql('mariadb');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(2, $affectedRows);

        $count = $this->connection->query("SELECT COUNT(*) as cnt FROM users WHERE status = 'selected'")->fetch();
        $this->assertEquals(2, $count['cnt']);
    }

    public function testUpdateWithNullValue(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->setMultiple(['age' => null])
            ->where('name')->equals('John Doe')
            ->sql('mariadb');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        // Verify age is now NULL
        $stmt = $this->connection->query("SELECT age FROM users WHERE name = 'John Doe'");
        $result = $stmt->fetch();
        $this->assertNull($result['age']);
    }

    public function testUpdateIgnore(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->ignore()
            ->setMultiple(['email' => 'jane@example.com']) // Try to set to existing email
            ->where('name')->equals('John Doe')
            ->sql('mariadb');

        $stmt = $this->connection->prepare($prepared->sql());

        // Should not throw an exception due to IGNORE
        $stmt->execute($prepared->bindings());

        // Verify John's email was NOT changed (due to unique constraint)
        $stmt = $this->connection->query("SELECT email FROM users WHERE name = 'John Doe'");
        $result = $stmt->fetch();
        $this->assertEquals('john@example.com', $result['email']);
    }

    public function testUpdateNoRowsMatched(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->setMultiple(['status' => 'updated'])
            ->where('name')->equals('Non Existent User')
            ->sql('mariadb');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(0, $affectedRows);
    }

    public function testUpdateReturnsAffectedRows(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->setMultiple(['status' => 'batch_updated'])
            ->where('age')->greater(25)
            ->sql('mariadb');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(3, $affectedRows); // John (30), Bob (35), Alice (28)
    }

    public function testUpdateWithEmptyInConditionUpdatesNothing(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->set('status', 'deleted')
            ->where('id')->in([])
            ->sql('mariadb');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $this->assertEquals(0, $stmt->rowCount());

        // Verify nothing was changed
        $stmt = $this->connection->query("SELECT COUNT(*) as cnt FROM users WHERE status = 'deleted'");
        $result = $stmt->fetch();
        $this->assertEquals(0, (int)$result['cnt']);
    }
}
