<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Integration\Postgres;

use JardisSupport\DbQuery\DbUpdate;
use JardisSupport\DbQuery\Tests\Integration\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * PostgreSQL Integration Tests for DbUpdate
 *
 * Tests actual UPDATE query execution against PostgreSQL database
 */
class DbUpdatePostgresIntegrationTest extends TestCase
{
    private DatabaseConnection $db;
    private PDO $connection;

    protected function setUp(): void
    {
        $this->db = new DatabaseConnection();
        $this->connection = $this->db->getPostgresConnection();
        $this->db->createTestTable($this->connection, 'postgres', 'users');

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
            ->sql('postgres');

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
            ->sql('postgres');

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
            ->sql('postgres');

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
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(1, $affectedRows);

        // Verify only John Doe (age 30) was updated
        $stmt = $this->connection->query("SELECT name FROM users WHERE status = 'senior'");
        $result = $stmt->fetch();
        $this->assertEquals('John Doe', $result['name']);
    }

    // Note: LIMIT in UPDATE is not supported in PostgreSQL
    /*
    public function testUpdateWithLimit(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->setMultiple(['status' => 'limited'])
            ->where('status')->equals('active')
            ->limit(2)
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(2, $affectedRows);

        // Verify only 2 rows were updated
        $count = $this->connection->query("SELECT COUNT(*) as cnt FROM users WHERE status = 'limited'")->fetch();
        $this->assertEquals(2, $count['cnt']);
    }
    */

    // Note: ORDER BY and LIMIT in UPDATE are not supported in PostgreSQL
    /*
    public function testUpdateWithOrderByAndLimit(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->setMultiple(['status' => 'youngest'])
            ->where('status')->equals('active')
            ->orderBy('age', 'ASC')
            ->limit(1)
            ->sql('postgres');

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
    */

    public function testUpdateWithInCondition(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->setMultiple(['status' => 'selected'])
            ->where('name')->in(['John Doe', 'Jane Smith'])
            ->sql('postgres');

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
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        // Verify age is now NULL
        $stmt = $this->connection->query("SELECT age FROM users WHERE name = 'John Doe'");
        $result = $stmt->fetch();
        $this->assertNull($result['age']);
    }

    public function testUpdateNoRowsMatched(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->setMultiple(['status' => 'updated'])
            ->where('name')->equals('Non Existent User')
            ->sql('postgres');

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
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(3, $affectedRows); // John (30), Bob (35), Alice (28)
    }

    public function testUpdateWithBetweenCondition(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->setMultiple(['status' => 'mid_age'])
            ->where('age')->between(26, 32)
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(2, $affectedRows); // John (30), Alice (28)

        $count = $this->connection->query("SELECT COUNT(*) as cnt FROM users WHERE status = 'mid_age'")->fetch();
        $this->assertEquals(2, $count['cnt']);
    }

    public function testUpdateWithLikeCondition(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->setMultiple(['status' => 'has_john'])
            ->where('name')->like('%John%')
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(2, $affectedRows); // John Doe and Bob Johnson

        $count = $this->connection->query("SELECT COUNT(*) as cnt FROM users WHERE status = 'has_john'")->fetch();
        $this->assertEquals(2, $count['cnt']);
    }

    public function testUpdateWithOrCondition(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->setMultiple(['status' => 'special'])
            ->where('status')->equals('inactive')
            ->or('age')->lower(26)
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $affectedRows = $stmt->rowCount();
        $this->assertEquals(2, $affectedRows); // Bob (inactive) and Jane (age 25)

        $count = $this->connection->query("SELECT COUNT(*) as cnt FROM users WHERE status = 'special'")->fetch();
        $this->assertEquals(2, $count['cnt']);
    }

    public function testUpdateWithEmptyInConditionUpdatesNothing(): void
    {
        $update = new DbUpdate();
        $prepared = $update
            ->table('users')
            ->set('status', 'deleted')
            ->where('id')->in([])
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $this->assertEquals(0, $stmt->rowCount());

        // Verify nothing was changed
        $stmt = $this->connection->query("SELECT COUNT(*) as cnt FROM users WHERE status = 'deleted'");
        $result = $stmt->fetch();
        $this->assertEquals(0, (int)$result['cnt']);
    }
}
