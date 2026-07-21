<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Data;

use JardisSupport\DbQuery\Data\QueryResult;
use JardisSupport\Contract\DbQuery\QueryResultInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for QueryResult
 *
 * Tests the SELECT query result implementation with fetchAll, fetchOne, and rowCount.
 */
class QueryResultTest extends TestCase
{
    // ==================== Constructor / Interface Tests ====================

    public function testImplementsQueryResultInterface(): void
    {
        $result = new QueryResult([]);

        $this->assertInstanceOf(QueryResultInterface::class, $result);
    }

    // ==================== fetchAll() Method Tests ====================

    public function testFetchAllReturnsEmptyArrayWhenNoRows(): void
    {
        $result = new QueryResult([]);

        $this->assertSame([], $result->fetchAll());
    }

    public function testFetchAllReturnsSingleRow(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Alice'],
        ];
        $result = new QueryResult($rows);

        $this->assertSame($rows, $result->fetchAll());
    }

    public function testFetchAllReturnsMultipleRows(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie'],
        ];
        $result = new QueryResult($rows);

        $this->assertSame($rows, $result->fetchAll());
        $this->assertCount(3, $result->fetchAll());
    }

    public function testFetchAllPreservesDataTypes(): void
    {
        $rows = [
            [
                'id' => 42,
                'name' => 'Test',
                'score' => 9.5,
                'active' => true,
                'deleted_at' => null,
            ],
        ];
        $result = new QueryResult($rows);

        $row = $result->fetchAll()[0];

        $this->assertIsInt($row['id']);
        $this->assertIsString($row['name']);
        $this->assertIsFloat($row['score']);
        $this->assertIsBool($row['active']);
        $this->assertNull($row['deleted_at']);
    }

    // ==================== fetchOne() Method Tests ====================

    public function testFetchOneReturnsNullWhenNoRows(): void
    {
        $result = new QueryResult([]);

        $this->assertNull($result->fetchOne());
    }

    public function testFetchOneReturnsFirstRow(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];
        $result = new QueryResult($rows);

        $this->assertSame(['id' => 1, 'name' => 'Alice'], $result->fetchOne());
    }

    public function testFetchOneReturnsSingleRowWhenOnlyOneExists(): void
    {
        $rows = [
            ['id' => 5, 'email' => 'test@example.com'],
        ];
        $result = new QueryResult($rows);

        $this->assertSame(['id' => 5, 'email' => 'test@example.com'], $result->fetchOne());
    }

    // ==================== rowCount() Method Tests ====================

    public function testRowCountReturnsZeroForEmptyResult(): void
    {
        $result = new QueryResult([]);

        $this->assertSame(0, $result->rowCount());
    }

    public function testRowCountReturnsOneForSingleRow(): void
    {
        $result = new QueryResult([
            ['id' => 1],
        ]);

        $this->assertSame(1, $result->rowCount());
    }

    public function testRowCountReturnsCorrectCountForMultipleRows(): void
    {
        $rows = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
            ['id' => 4],
            ['id' => 5],
        ];
        $result = new QueryResult($rows);

        $this->assertSame(5, $result->rowCount());
    }

    // ==================== Combination Tests ====================

    public function testEmptyResultConsistency(): void
    {
        $result = new QueryResult([]);

        $this->assertSame([], $result->fetchAll());
        $this->assertNull($result->fetchOne());
        $this->assertSame(0, $result->rowCount());
    }

    public function testSingleRowResultConsistency(): void
    {
        $row = ['id' => 1, 'name' => 'Alice'];
        $result = new QueryResult([$row]);

        $this->assertSame([$row], $result->fetchAll());
        $this->assertSame($row, $result->fetchOne());
        $this->assertSame(1, $result->rowCount());
    }

    public function testMultipleInstancesAreIndependent(): void
    {
        $result1 = new QueryResult([['id' => 1]]);
        $result2 = new QueryResult([['id' => 2], ['id' => 3]]);

        $this->assertSame(1, $result1->rowCount());
        $this->assertSame(2, $result2->rowCount());

        $this->assertSame(['id' => 1], $result1->fetchOne());
        $this->assertSame(['id' => 2], $result2->fetchOne());
    }
}
