<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Integration\Sqlite;

use JardisSupport\DbQuery\DbInsert;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Tests\Integration\DatabaseConnection;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * SQLite integration test for camelCase identifier auto-quoting (2026-08-10)
 *
 * camelCase columns referenced in WHERE/ORDER BY/GROUP BY/SELECT are emitted
 * backtick-quoted (the package's existing SQLite identifier quoting, accepted
 * by SQLite) and must execute against a real SQLite database.
 */
class CamelCaseIdentifierSqliteTest extends TestCase
{
    private const TABLE = 'camel_orders';

    private DatabaseConnection $db;
    private PDO $connection;

    protected function setUp(): void
    {
        $this->db = new DatabaseConnection();
        $this->connection = $this->db->getSqliteConnection();

        $this->connection->exec('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->connection->exec(
            'CREATE TABLE ' . self::TABLE . ' ('
            . '"id" INTEGER PRIMARY KEY AUTOINCREMENT, '
            . '"buyerName" TEXT NOT NULL, '
            . '"createdAt" TEXT NOT NULL, '
            . '"totalAmount" INTEGER NOT NULL)'
        );
    }

    protected function tearDown(): void
    {
        $this->connection->exec('DROP TABLE IF EXISTS ' . self::TABLE);
    }

    private function execute(DbPreparedQueryInterface $prepared): array
    {
        $stmt = $this->connection->prepare($prepared->sql());
        // Type-aware binding: SQLite compares strictly by type, a string-bound
        // integer would never satisfy an integer comparison (e.g. HAVING).
        foreach (array_values($prepared->bindings()) as $index => $value) {
            $stmt->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function testCamelCaseOverWhereOrderGroupSelect(): void
    {
        foreach (
            [
                ['Alice', '2024-03-01', 100],
                ['Alice', '2024-03-02', 50],
                ['Bob', '2024-01-15', 200],
            ] as [$name, $date, $amount]
        ) {
            $prepared = (new DbInsert())
                ->into(self::TABLE)
                ->set(['buyerName' => $name, 'createdAt' => $date, 'totalAmount' => $amount])
                ->sql('sqlite');
            $stmt = $this->connection->prepare($prepared->sql());
            $stmt->execute($prepared->bindings());
        }

        $results = $this->execute(
            (new DbQuery())
                ->select('buyerName, createdAt, totalAmount')
                ->from(self::TABLE)
                ->where('createdAt')->greaterEquals('2024-02-01')
                ->orderBy('createdAt', 'DESC')
                ->sql('sqlite')
        );

        $this->assertCount(2, $results);
        $this->assertEquals('2024-03-02', $results[0]['createdAt']);

        $results = $this->execute(
            (new DbQuery())
                ->select('buyerName, COUNT(*) AS orderCount')
                ->from(self::TABLE)
                ->groupBy('buyerName')
                ->having('orderCount')->greater(1)
                ->sql('sqlite')
        );

        $this->assertCount(1, $results);
        $this->assertEquals('Alice', $results[0]['buyerName']);
        $this->assertEquals(2, $results[0]['orderCount']);
    }
}
