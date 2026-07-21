<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Data;

use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\DbQuery\Data\UpdateState;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Data\Contract\JoinStateInterface;
use JardisSupport\DbQuery\Data\Contract\LimitStateInterface;
use JardisSupport\DbQuery\Data\Contract\OrderByStateInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for UpdateState
 *
 * Tests the state management for UPDATE queries.
 */
class UpdateStateTest extends TestCase
{
    // ==================== Constructor and Interface Tests ====================

    public function testConstructorCreatesEmptyState(): void
    {
        $state = new UpdateState();

        $this->assertInstanceOf(UpdateState::class, $state);
        $this->assertInstanceOf(JoinStateInterface::class, $state);
        $this->assertInstanceOf(OrderByStateInterface::class, $state);
        $this->assertInstanceOf(LimitStateInterface::class, $state);
    }

    // ==================== Table Tests ====================

    public function testGetTableReturnsEmptyStringByDefault(): void
    {
        $state = new UpdateState();

        $this->assertEquals('', $state->getTable());
    }

    public function testSetTableStoresValue(): void
    {
        $state = new UpdateState();
        $state->setTable('users');

        $this->assertEquals('users', $state->getTable());
    }

    // ==================== Alias Tests ====================

    public function testGetAliasReturnsNullByDefault(): void
    {
        $state = new UpdateState();

        $this->assertNull($state->getAlias());
    }

    public function testSetAliasStoresValue(): void
    {
        $state = new UpdateState();
        $state->setAlias('u');

        $this->assertEquals('u', $state->getAlias());
    }

    // ==================== SetData Tests ====================

    public function testGetSetDataReturnsEmptyArrayByDefault(): void
    {
        $state = new UpdateState();

        $this->assertEmpty($state->getSetData());
    }

    public function testSetColumnWithString(): void
    {
        $state = new UpdateState();
        $state->setColumn('name', 'John');

        $setData = $state->getSetData();
        $this->assertCount(1, $setData);
        $this->assertEquals('John', $setData['name']);
    }

    public function testSetColumnWithInteger(): void
    {
        $state = new UpdateState();
        $state->setColumn('age', 30);

        $setData = $state->getSetData();
        $this->assertEquals(30, $setData['age']);
    }

    public function testSetColumnWithBoolean(): void
    {
        $state = new UpdateState();
        $state->setColumn('active', true);

        $setData = $state->getSetData();
        $this->assertTrue($setData['active']);
    }

    public function testSetColumnWithNull(): void
    {
        $state = new UpdateState();
        $state->setColumn('deleted_at', null);

        $setData = $state->getSetData();
        $this->assertNull($setData['deleted_at']);
    }

    public function testSetColumnWithExpression(): void
    {
        $state = new UpdateState();
        $expr = new Expression('NOW()');
        $state->setColumn('updated_at', $expr);

        $setData = $state->getSetData();
        $this->assertInstanceOf(Expression::class, $setData['updated_at']);
    }

    public function testSetColumnWithSubquery(): void
    {
        $state = new UpdateState();
        $subquery = new DbQuery();
        $state->setColumn('status', $subquery);

        $setData = $state->getSetData();
        $this->assertInstanceOf(DbQuery::class, $setData['status']);
    }

    public function testSetMultipleColumns(): void
    {
        $state = new UpdateState();
        $state->setMultipleColumns([
            'name' => 'John',
            'age' => 30,
            'active' => true,
        ]);

        $setData = $state->getSetData();
        $this->assertCount(3, $setData);
        $this->assertEquals('John', $setData['name']);
        $this->assertEquals(30, $setData['age']);
        $this->assertTrue($setData['active']);
    }

    public function testSetMultipleColumnsWithExpression(): void
    {
        $state = new UpdateState();
        $state->setMultipleColumns([
            'name' => 'John',
            'updated_at' => new Expression('NOW()'),
        ]);

        $setData = $state->getSetData();
        $this->assertCount(2, $setData);
        $this->assertEquals('John', $setData['name']);
        $this->assertInstanceOf(Expression::class, $setData['updated_at']);
    }

    // ==================== Joins Tests ====================

    public function testGetJoinsReturnsEmptyArrayByDefault(): void
    {
        $state = new UpdateState();

        $this->assertEmpty($state->getJoins());
    }

    public function testAddJoin(): void
    {
        $state = new UpdateState();
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

    // ==================== OrderBy Tests ====================

    public function testGetOrderByReturnsEmptyArrayByDefault(): void
    {
        $state = new UpdateState();

        $this->assertEmpty($state->getOrderBy());
    }

    public function testAddOrderBy(): void
    {
        $state = new UpdateState();
        $state->addOrderBy('created_at', 'DESC');

        $this->assertEquals(['created_at DESC'], $state->getOrderBy());
    }

    // ==================== Limit Tests ====================

    public function testGetLimitReturnsNullByDefault(): void
    {
        $state = new UpdateState();

        $this->assertNull($state->getLimit());
    }

    public function testSetLimit(): void
    {
        $state = new UpdateState();
        $state->setLimit(10);

        $this->assertEquals(10, $state->getLimit());
    }

    public function testSetLimitIgnoresOffset(): void
    {
        $state = new UpdateState();
        $state->setLimit(10, 20);

        $this->assertEquals(10, $state->getLimit());
        $this->assertNull($state->getOffset());
    }

    // ==================== Offset Tests ====================

    public function testGetOffsetAlwaysReturnsNull(): void
    {
        $state = new UpdateState();

        $this->assertNull($state->getOffset());
    }

    // ==================== Ignore Tests ====================

    public function testIsIgnoreDefaultsFalse(): void
    {
        $state = new UpdateState();

        $this->assertFalse($state->isIgnore());
    }

    public function testSetIgnore(): void
    {
        $state = new UpdateState();
        $state->setIgnore(true);

        $this->assertTrue($state->isIgnore());
    }

    // ==================== Complete State Tests ====================

    public function testCompleteUpdateState(): void
    {
        $state = new UpdateState();
        $state->setTable('users');
        $state->setAlias('u');
        $state->setColumn('name', 'John');
        $state->setColumn('updated_at', new Expression('NOW()'));
        $state->addJoin([
            'join' => 'INNER JOIN',
            'container' => 'orders',
            'alias' => 'o',
            'constraint' => 'u.id = o.user_id',
        ]);
        $state->addOrderBy('created_at', 'DESC');
        $state->setLimit(100);

        $this->assertEquals('users', $state->getTable());
        $this->assertEquals('u', $state->getAlias());
        $this->assertCount(2, $state->getSetData());
        $this->assertCount(1, $state->getJoins());
        $this->assertCount(1, $state->getOrderBy());
        $this->assertEquals(100, $state->getLimit());
    }

    // ==================== Multiple Instances Tests ====================

    public function testMultipleInstancesAreIndependent(): void
    {
        $state1 = new UpdateState();
        $state1->setTable('users');
        $state1->setColumn('status', 'active');

        $state2 = new UpdateState();
        $state2->setTable('orders');
        $state2->setColumn('status', 'shipped');

        $this->assertEquals('users', $state1->getTable());
        $this->assertEquals('orders', $state2->getTable());
        $this->assertEquals('active', $state1->getSetData()['status']);
        $this->assertEquals('shipped', $state2->getSetData()['status']);
    }
}
