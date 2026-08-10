<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Integration\Postgres;

use JardisSupport\DbQuery\DbInsert;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Tests\Integration\DatabaseConnection;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * PostgreSQL integration test for camelCase identifier auto-quoting
 *
 * Reproduces the real-world failure (42703): a table created with QUOTED
 * camelCase DDL ("createdAt", "buyerName") could be written via DbInsert
 * (columns quoted) but not read via WHERE/ORDER BY/GROUP BY/SELECT because
 * condition fields were emitted raw and PostgreSQL folded them to lowercase.
 * Since the auto-quoting of simple identifiers (2026-08-10) the full
 * INSERT -> WHERE roundtrip works.
 */
class CamelCaseIdentifierPostgresTest extends TestCase
{
    private const TABLE = 'camel_orders';

    private DatabaseConnection $db;
    private PDO $connection;

    protected function setUp(): void
    {
        $this->db = new DatabaseConnection();
        $this->connection = $this->db->getPostgresConnection();

        $this->connection->exec('DROP TABLE IF EXISTS ' . self::TABLE);
        // Quoted DDL - the case of "createdAt"/"buyerName" is preserved
        $this->connection->exec(
            'CREATE TABLE ' . self::TABLE . ' ('
            . '"id" SERIAL PRIMARY KEY, '
            . '"buyerName" VARCHAR(100) NOT NULL, '
            . '"createdAt" DATE NOT NULL, '
            . '"totalAmount" INT NOT NULL)'
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

    public function testInsertToWhereRoundtripOnQuotedCamelCaseDdl(): void
    {
        // INSERT via DbInsert (columns arrive quoted, as before the fix)
        $insert = (new DbInsert())
            ->into(self::TABLE)
            ->set([
                'buyerName' => 'Alice',
                'createdAt' => '2024-03-01',
                'totalAmount' => 100,
            ]);
        $prepared = $insert->sql('postgres');
        $stmt = $this->connection->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        // WHERE on the camelCase column - failed with 42703 before the fix
        $results = $this->execute(
            (new DbQuery())
                ->select('*')
                ->from(self::TABLE)
                ->where('buyerName')->equals('Alice')
                ->sql('postgres')
        );

        $this->assertCount(1, $results);
        $this->assertEquals('Alice', $results[0]['buyerName']);
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
                ->sql('postgres');
            $stmt = $this->connection->prepare($prepared->sql());
            $stmt->execute($prepared->bindings());
        }

        // SELECT field list + WHERE + ORDER BY
        $results = $this->execute(
            (new DbQuery())
                ->select('buyerName, createdAt, totalAmount')
                ->from(self::TABLE)
                ->where('createdAt')->greaterEquals('2024-02-01')
                ->orderBy('createdAt', 'DESC')
                ->sql('postgres')
        );

        $this->assertCount(2, $results);
        $this->assertEquals('2024-03-02', $results[0]['createdAt']);
        $this->assertArrayHasKey('buyerName', $results[0]);

        // GROUP BY + HAVING on aggregate alias-free expression
        $results = $this->execute(
            (new DbQuery())
                ->select('buyerName, COUNT(*) AS orderCount')
                ->from(self::TABLE)
                ->groupBy('buyerName')
                ->having('COUNT(*)')->greater(1)
                ->sql('postgres')
        );

        $this->assertCount(1, $results);
        $this->assertEquals('Alice', $results[0]['buyerName']);
        $this->assertEquals(2, $results[0]['orderCount']);
    }
}
