<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Data;

use JardisSupport\DbQuery\Data\QueryState;
use JardisSupport\DbQuery\Data\WindowFunction;
use JardisSupport\DbQuery\Data\WindowReference;
use JardisSupport\DbQuery\Data\WindowSpec;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Data\Contract\FromStateInterface;
use JardisSupport\DbQuery\Data\Contract\JoinStateInterface;
use JardisSupport\DbQuery\Data\Contract\LimitStateInterface;
use JardisSupport\DbQuery\Data\Contract\OrderByStateInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for QueryState
 *
 * Tests the state management for SELECT queries.
 */
class QueryStateTest extends TestCase
{
    // ==================== Constructor and Interface Tests ====================

    public function testConstructorCreatesDefaultState(): void
    {
        $state = new QueryState();

        $this->assertInstanceOf(QueryState::class, $state);
        $this->assertInstanceOf(FromStateInterface::class, $state);
        $this->assertInstanceOf(JoinStateInterface::class, $state);
        $this->assertInstanceOf(OrderByStateInterface::class, $state);
        $this->assertInstanceOf(LimitStateInterface::class, $state);
        $this->assertEquals('*', $state->getFields());
        $this->assertFalse($state->isDistinct());
    }

    // ==================== Fields Tests ====================

    public function testSetAndGetFields(): void
    {
        $state = new QueryState();
        $state->setFields('id, name, email');

        $this->assertEquals('id, name, email', $state->getFields());
    }

    // ==================== Distinct Tests ====================

    public function testSetAndGetDistinct(): void
    {
        $state = new QueryState();
        $state->setDistinct(true);

        $this->assertTrue($state->isDistinct());
    }

    // ==================== Container Tests ====================

    public function testSetContainerWithString(): void
    {
        $state = new QueryState();
        $state->setContainer('users');

        $this->assertEquals('users', $state->getContainer());
    }

    public function testSetContainerWithSubquery(): void
    {
        $state = new QueryState();
        $subquery = new DbQuery();
        $state->setContainer($subquery);

        $this->assertInstanceOf(DbQuery::class, $state->getContainer());
    }

    // ==================== Alias Tests ====================

    public function testSetAndGetAlias(): void
    {
        $state = new QueryState();
        $state->setAlias('u');

        $this->assertEquals('u', $state->getAlias());
    }

    // ==================== Joins Tests ====================

    public function testAddJoin(): void
    {
        $state = new QueryState();
        $state->addJoin([
            'join' => 'INNER JOIN',
            'container' => 'orders',
            'alias' => 'o',
            'constraint' => 'u.id = o.user_id',
        ]);

        $joins = $state->getJoins();
        $this->assertCount(1, $joins);
        $this->assertEquals('INNER JOIN', $joins[0]['join']);
    }

    // ==================== GroupBy Tests ====================

    public function testAddGroupBy(): void
    {
        $state = new QueryState();
        $state->addGroupBy('department');

        $this->assertEquals(['department'], $state->getGroupBy());
    }

    public function testAddMultipleGroupBy(): void
    {
        $state = new QueryState();
        $state->addGroupBy('department');
        $state->addGroupBy('location');

        $this->assertEquals(['department', 'location'], $state->getGroupBy());
    }

    // ==================== OrderBy Tests ====================

    public function testAddOrderBy(): void
    {
        $state = new QueryState();
        $state->addOrderBy('name', 'ASC');

        $this->assertEquals(['name ASC'], $state->getOrderBy());
    }

    // ==================== Limit and Offset Tests ====================

    public function testSetLimit(): void
    {
        $state = new QueryState();
        $state->setLimit(10, null);

        $this->assertEquals(10, $state->getLimit());
        $this->assertNull($state->getOffset());
    }

    public function testSetLimitWithOffset(): void
    {
        $state = new QueryState();
        $state->setLimit(10, 20);

        $this->assertEquals(10, $state->getLimit());
        $this->assertEquals(20, $state->getOffset());
    }

    // ==================== Union Tests ====================

    public function testAddUnion(): void
    {
        $state = new QueryState();
        $query = new DbQuery();
        $state->addUnion($query);

        $unions = $state->getUnion();
        $this->assertCount(1, $unions);
        $this->assertInstanceOf(DbQuery::class, $unions[0]);
    }

    // ==================== UnionAll Tests ====================

    public function testAddUnionAll(): void
    {
        $state = new QueryState();
        $query = new DbQuery();
        $state->addUnionAll($query);

        $unions = $state->getUnionAll();
        $this->assertCount(1, $unions);
        $this->assertInstanceOf(DbQuery::class, $unions[0]);
    }

    // ==================== CTE Tests ====================

    public function testAddCte(): void
    {
        $state = new QueryState();
        $query = new DbQuery();
        $state->addCte('cte_name', $query);

        $ctes = $state->getCte();
        $this->assertCount(1, $ctes);
        $this->assertInstanceOf(DbQuery::class, $ctes['cte_name']);
    }

    // ==================== CTE Recursive Tests ====================

    public function testAddCteRecursive(): void
    {
        $state = new QueryState();
        $query = new DbQuery();
        $state->addCteRecursive('recursive_cte', $query);

        $ctes = $state->getCteRecursive();
        $this->assertCount(1, $ctes);
        $this->assertInstanceOf(DbQuery::class, $ctes['recursive_cte']);
    }

    // ==================== Select Subqueries Tests ====================

    public function testAddSelectSubquery(): void
    {
        $state = new QueryState();
        $subquery = new DbQuery();
        $state->addSelectSubquery('sub_alias', $subquery);

        $subqueries = $state->getSelectSubqueries();
        $this->assertCount(1, $subqueries);
        $this->assertInstanceOf(DbQuery::class, $subqueries['sub_alias']);
    }

    // ==================== Window Functions Tests ====================

    public function testAddWindowFunction(): void
    {
        $state = new QueryState();
        $spec = new WindowSpec();
        $windowFunc = new WindowFunction('ROW_NUMBER', 'row_num', null, $spec);
        $state->addWindowFunction($windowFunc);

        $functions = $state->getWindowFunctions();
        $this->assertCount(1, $functions);
        $this->assertInstanceOf(WindowFunction::class, $functions[0]);
    }

    // ==================== Named Windows Tests ====================

    public function testAddNamedWindow(): void
    {
        $state = new QueryState();
        $spec = new WindowSpec();
        $spec->addPartition('department');
        $state->addNamedWindow('dept_window', $spec);

        $windows = $state->getNamedWindows();
        $this->assertCount(1, $windows);
        $this->assertInstanceOf(WindowSpec::class, $windows['dept_window']);
    }

    // ==================== Window References Tests ====================

    public function testAddWindowReference(): void
    {
        $state = new QueryState();
        $ref = new WindowReference('ROW_NUMBER', 'my_window', 'row_num', null);
        $state->addWindowReference($ref);

        $refs = $state->getWindowReferences();
        $this->assertCount(1, $refs);
        $this->assertInstanceOf(WindowReference::class, $refs[0]);
    }

    // ==================== Complete State Tests ====================

    public function testCompleteQueryState(): void
    {
        $state = new QueryState();
        $state->setFields('id, name');
        $state->setDistinct(true);
        $state->setContainer('users');
        $state->setAlias('u');
        $state->addJoin([
            'join' => 'INNER JOIN',
            'container' => 'orders',
            'alias' => 'o',
            'constraint' => 'u.id = o.user_id',
        ]);
        $state->addGroupBy('department');
        $state->addOrderBy('name', 'ASC');
        $state->setLimit(10, 5);

        $this->assertEquals('id, name', $state->getFields());
        $this->assertTrue($state->isDistinct());
        $this->assertEquals('users', $state->getContainer());
        $this->assertEquals('u', $state->getAlias());
        $this->assertCount(1, $state->getJoins());
        $this->assertCount(1, $state->getGroupBy());
        $this->assertCount(1, $state->getOrderBy());
        $this->assertEquals(10, $state->getLimit());
        $this->assertEquals(5, $state->getOffset());
    }

    // ==================== Multiple Instances Tests ====================

    public function testMultipleInstancesAreIndependent(): void
    {
        $state1 = new QueryState();
        $state1->setFields('id, name');
        $state1->setContainer('users');

        $state2 = new QueryState();
        $state2->setFields('id, total');
        $state2->setContainer('orders');

        $this->assertEquals('id, name', $state1->getFields());
        $this->assertEquals('id, total', $state2->getFields());
        $this->assertEquals('users', $state1->getContainer());
        $this->assertEquals('orders', $state2->getContainer());
    }
}
