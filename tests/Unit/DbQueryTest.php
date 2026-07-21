<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit;

use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for DbQuery
 *
 * Tests the main SELECT query builder functionality.
 * Note: Detailed SQL generation tests are in tests/unit/query/mysql/, postgres/, sqlite/
 */
class DbQueryTest extends TestCase
{
    // ==================== Constructor Tests ====================

    public function testConstructorCreatesInstance(): void
    {
        $query = new DbQuery();

        $this->assertInstanceOf(DbQuery::class, $query);
        $this->assertInstanceOf(DbQueryBuilderInterface::class, $query);
    }

    // ==================== Fluent Interface Tests ====================

    public function testSelectReturnsInstance(): void
    {
        $query = new DbQuery();
        $result = $query->select('id, name');

        $this->assertSame($query, $result);
    }

    public function testFromReturnsInstance(): void
    {
        $query = new DbQuery();
        $result = $query->from('users');

        $this->assertSame($query, $result);
    }

    public function testDistinctReturnsInstance(): void
    {
        $query = new DbQuery();
        $result = $query->distinct(true);

        $this->assertSame($query, $result);
    }

    public function testDistinctWithoutArgumentActivatesDistinct(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('name')
            ->from('users')
            ->distinct()
            ->sql('mysql', false);

        $this->assertStringContainsString('SELECT DISTINCT', $sql);
    }

    public function testWhereReturnsConditionBuilder(): void
    {
        $query = new DbQuery();
        $result = $query->where('id');

        $this->assertNotNull($result);
    }

    public function testLimitReturnsInstance(): void
    {
        $query = new DbQuery();
        $result = $query->limit(10);

        $this->assertSame($query, $result);
    }

    public function testOrderByReturnsInstance(): void
    {
        $query = new DbQuery();
        $result = $query->orderBy('name ASC');

        $this->assertSame($query, $result);
    }

    public function testGroupByReturnsInstance(): void
    {
        $query = new DbQuery();
        $result = $query->groupBy('category');

        $this->assertSame($query, $result);
    }

    // ==================== Method Chaining Tests ====================

    public function testMethodChaining(): void
    {
        $query = new DbQuery();

        $result = $query->select('id, name')
            ->from('users')
            ->orderBy('name ASC')
            ->limit(10);

        $this->assertInstanceOf(DbQuery::class, $result);
    }

    public function testComplexMethodChaining(): void
    {
        $query = new DbQuery();

        $result = $query->distinct(true)
            ->select('u.id, u.name')
            ->from('users', 'u')
            ->groupBy('u.id')
            ->orderBy('u.name ASC')
            ->limit(20, 10);

        $this->assertInstanceOf(DbQuery::class, $result);
    }

    // ==================== Subquery Tests ====================

    public function testFromWithSubquery(): void
    {
        $subquery = new DbQuery();
        $subquery->select('*')->from('users');

        $query = new DbQuery();
        $result = $query->from($subquery, 'sub');

        $this->assertSame($query, $result);
    }

    public function testSelectSubquery(): void
    {
        $subquery = new DbQuery();
        $subquery->select('COUNT(*)')->from('orders');

        $query = new DbQuery();
        $result = $query->selectSubquery($subquery, 'order_count');

        $this->assertSame($query, $result);
    }

    // ==================== CTE Tests ====================

    public function testWith(): void
    {
        $cteQuery = new DbQuery();
        $cteQuery->select('*')->from('users');

        $query = new DbQuery();
        $result = $query->with('user_cte', $cteQuery);

        $this->assertSame($query, $result);
    }

    public function testWithRecursive(): void
    {
        $cteQuery = new DbQuery();
        $cteQuery->select('*')->from('categories');

        $query = new DbQuery();
        $result = $query->withRecursive('category_tree', $cteQuery);

        $this->assertSame($query, $result);
    }

    // ==================== Multiple Instances Tests ====================

    public function testMultipleInstancesAreIndependent(): void
    {
        $query1 = new DbQuery();
        $query1->select('id')->from('users');

        $query2 = new DbQuery();
        $query2->select('name')->from('orders');

        // Both queries should be independent
        $this->assertNotSame($query1, $query2);
    }
}
