<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Integration\Postgres;

use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Tests\Integration\DatabaseConnection;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * PostgreSQL Integration Tests for DbQuery (SELECT)
 *
 * Tests actual query execution against PostgreSQL database
 */
class DbQueryPostgresIntegrationTest extends TestCase
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

    public function testSimpleSelectAll(): void
    {
        $query = new DbQuery();
        $prepared = $query
            ->select('*')
            ->from('users')
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        $results = $stmt->fetchAll();

        $this->assertCount(4, $results);
        $this->assertEquals('John Doe', $results[0]['name']);
    }

    public function testSelectWithWhereEquals(): void
    {
        $query = new DbQuery();
        $prepared = $query
            ->select('name, email')
            ->from('users')
            ->where('status')->equals('active')
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        $results = $stmt->fetchAll();

        $this->assertCount(3, $results);
        foreach ($results as $row) {
            $this->assertArrayHasKey('name', $row);
            $this->assertArrayHasKey('email', $row);
        }
    }

    public function testSelectWithMultipleConditions(): void
    {
        $query = new DbQuery();
        $prepared = $query
            ->select('*')
            ->from('users')
            ->where('status')->equals('active')
            ->and('age')->greater(27)
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        $results = $stmt->fetchAll();

        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]['name']);
        $this->assertEquals('Alice Brown', $results[1]['name']);
    }

    public function testSelectWithOrderBy(): void
    {
        $query = new DbQuery();
        $prepared = $query
            ->select('name, age')
            ->from('users')
            ->orderBy('age', 'ASC')
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        $results = $stmt->fetchAll();

        $this->assertCount(4, $results);
        $this->assertEquals('Jane Smith', $results[0]['name']);
        $this->assertEquals(25, $results[0]['age']);
        $this->assertEquals('Bob Johnson', $results[3]['name']);
        $this->assertEquals(35, $results[3]['age']);
    }

    public function testSelectWithLimit(): void
    {
        $query = new DbQuery();
        $prepared = $query
            ->select('*')
            ->from('users')
            ->orderBy('name', 'ASC')
            ->limit(2)
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        $results = $stmt->fetchAll();

        $this->assertCount(2, $results);
    }

    public function testSelectWithLimitAndOffset(): void
    {
        $query = new DbQuery();
        $prepared = $query
            ->select('name')
            ->from('users')
            ->orderBy('name', 'ASC')
            ->limit(2, 1)
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        $results = $stmt->fetchAll();

        $this->assertCount(2, $results);
        $this->assertEquals('Bob Johnson', $results[0]['name']);
    }

    public function testSelectWithGroupBy(): void
    {
        $query = new DbQuery();
        $prepared = $query
            ->select('status, COUNT(*) as count')
            ->from('users')
            ->groupBy('status')
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        $results = $stmt->fetchAll();

        $this->assertCount(2, $results);

        $statusCounts = [];
        foreach ($results as $row) {
            $statusCounts[$row['status']] = (int)$row['count'];
        }

        $this->assertEquals(3, $statusCounts['active']);
        $this->assertEquals(1, $statusCounts['inactive']);
    }

    public function testSelectWithHaving(): void
    {
        $query = new DbQuery();
        $prepared = $query
            ->select('status, COUNT(*) as count')
            ->from('users')
            ->groupBy('status')
            ->having('COUNT(*)')->greater(1)
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        $results = $stmt->fetchAll();

        $this->assertCount(1, $results);
        $this->assertEquals('active', $results[0]['status']);
        $this->assertEquals(3, (int)$results[0]['count']);
    }

    public function testSelectWithInCondition(): void
    {
        $query = new DbQuery();
        $prepared = $query
            ->select('name')
            ->from('users')
            ->where('name')->in(['John Doe', 'Jane Smith'])
            ->orderBy('name', 'ASC')
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        $results = $stmt->fetchAll();

        $this->assertCount(2, $results);
        $this->assertEquals('Jane Smith', $results[0]['name']);
        $this->assertEquals('John Doe', $results[1]['name']);
    }

    public function testSelectWithEmptyInConditionReturnsNoRows(): void
    {
        $query = new DbQuery();
        $prepared = $query
            ->select('name')
            ->from('users')
            ->where('id')->in([])
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        $results = $stmt->fetchAll();

        $this->assertCount(0, $results);
    }

    public function testSelectWithEmptyNotInConditionReturnsAllRows(): void
    {
        $query = new DbQuery();
        $prepared = $query
            ->select('name')
            ->from('users')
            ->where('id')->notIn([])
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        $results = $stmt->fetchAll();

        $this->assertCount(4, $results);
    }

    public function testSelectWithEmptyInCombinedWithOtherCondition(): void
    {
        $query = new DbQuery();
        $prepared = $query
            ->select('name')
            ->from('users')
            ->where('status')->equals('active')
            ->and('id')->in([])
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        $results = $stmt->fetchAll();

        $this->assertCount(0, $results);
    }

    public function testSelectWithEmptyInOrOtherCondition(): void
    {
        $query = new DbQuery();
        $prepared = $query
            ->select('name')
            ->from('users')
            ->where('id')->in([])
            ->or('status')->equals('active')
            ->orderBy('name', 'ASC')
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        $results = $stmt->fetchAll();

        $this->assertCount(3, $results);
    }

    public function testSelectWithBetweenCondition(): void
    {
        $query = new DbQuery();
        $prepared = $query
            ->select('name, age')
            ->from('users')
            ->where('age')->between(25, 30)
            ->orderBy('age', 'ASC')
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        $results = $stmt->fetchAll();

        $this->assertCount(3, $results);
        $this->assertEquals('Jane Smith', $results[0]['name']);
        $this->assertEquals('Alice Brown', $results[1]['name']);
        $this->assertEquals('John Doe', $results[2]['name']);
    }

    public function testSelectWithLikeCondition(): void
    {
        $query = new DbQuery();
        $prepared = $query
            ->select('name')
            ->from('users')
            ->where('name')->like('%John%')
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        $results = $stmt->fetchAll();

        $this->assertCount(2, $results);
    }

    public function testSelectWithJoin(): void
    {
        // Create orders table
        $this->db->createOrdersTable($this->connection, 'postgres', 'orders');
        $this->db->insertTestData($this->connection, 'orders', [
            ['user_id' => 1, 'product' => 'Laptop', 'amount' => 1200.00],
            ['user_id' => 1, 'product' => 'Mouse', 'amount' => 25.00],
            ['user_id' => 2, 'product' => 'Keyboard', 'amount' => 75.00],
        ]);

        $query = new DbQuery();
        $prepared = $query
            ->select('u.name, o.product, o.amount')
            ->from('users', 'u')
            ->innerJoin('orders', 'u.id = o.user_id', 'o')
            ->where('u.status')->equals('active')
            ->orderBy('o.amount', 'DESC')
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        $results = $stmt->fetchAll();

        $this->assertCount(3, $results);
        $this->assertEquals('Laptop', $results[0]['product']);
        $this->assertEquals(1200.00, (float)$results[0]['amount']);

        $this->db->dropTestTable($this->connection, 'orders');
    }

    public function testSelectDistinct(): void
    {
        $query = new DbQuery();
        $prepared = $query
            ->distinct(true)
            ->select('status')
            ->from('users')
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        $results = $stmt->fetchAll();

        $this->assertCount(2, $results);
    }

    public function testSelectWithAggregates(): void
    {
        $query = new DbQuery();
        $prepared = $query
            ->select('COUNT(*) as total, AVG(age) as avg_age, MIN(age) as min_age, MAX(age) as max_age')
            ->from('users')
            ->where('status')->equals('active')
            ->sql('postgres');

        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());
        $result = $stmt->fetch();

        $this->assertEquals(3, (int)$result['total']);
        $this->assertEquals(27.67, round((float)$result['avg_age'], 2));
        $this->assertEquals(25, (int)$result['min_age']);
        $this->assertEquals(30, (int)$result['max_age']);
    }
}
