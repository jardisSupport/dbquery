<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Data;

use JardisSupport\DbQuery\Data\DeleteState;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Data\Contract\JoinStateInterface;
use JardisSupport\DbQuery\Data\Contract\LimitStateInterface;
use JardisSupport\DbQuery\Data\Contract\OrderByStateInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for DeleteState
 *
 * Tests the state management for DELETE queries.
 */
class DeleteStateTest extends TestCase
{
    // ==================== Constructor and Interface Tests ====================

    public function testConstructorCreatesEmptyState(): void
    {
        $state = new DeleteState();

        $this->assertInstanceOf(DeleteState::class, $state);
        $this->assertInstanceOf(JoinStateInterface::class, $state);
        $this->assertInstanceOf(OrderByStateInterface::class, $state);
        $this->assertInstanceOf(LimitStateInterface::class, $state);
    }

    // ==================== Table Tests ====================

    public function testGetTableReturnsEmptyStringByDefault(): void
    {
        $state = new DeleteState();

        $this->assertEquals('', $state->getTable());
    }

    public function testSetTableStoresValue(): void
    {
        $state = new DeleteState();
        $state->setTable('users');

        $this->assertEquals('users', $state->getTable());
    }

    public function testSetTableWithQualifiedName(): void
    {
        $state = new DeleteState();
        $state->setTable('schema.users');

        $this->assertEquals('schema.users', $state->getTable());
    }

    // ==================== Alias Tests ====================

    public function testGetAliasReturnsNullByDefault(): void
    {
        $state = new DeleteState();

        $this->assertNull($state->getAlias());
    }

    public function testSetAliasStoresValue(): void
    {
        $state = new DeleteState();
        $state->setAlias('u');

        $this->assertEquals('u', $state->getAlias());
    }

    public function testSetAliasToNull(): void
    {
        $state = new DeleteState();
        $state->setAlias('u');
        $state->setAlias(null);

        $this->assertNull($state->getAlias());
    }

    // ==================== Joins Tests ====================

    public function testGetJoinsReturnsEmptyArrayByDefault(): void
    {
        $state = new DeleteState();

        $this->assertIsArray($state->getJoins());
        $this->assertEmpty($state->getJoins());
    }

    public function testAddJoinWithTableString(): void
    {
        $state = new DeleteState();
        $state->addJoin([
            'join' => 'INNER JOIN',
            'container' => 'orders',
            'alias' => 'o',
            'constraint' => 'u.id = o.user_id',
        ]);

        $joins = $state->getJoins();
        $this->assertCount(1, $joins);
        $this->assertEquals('INNER JOIN', $joins[0]['join']);
        $this->assertEquals('orders', $joins[0]['container']);
        $this->assertEquals('o', $joins[0]['alias']);
        $this->assertEquals('u.id = o.user_id', $joins[0]['constraint']);
    }

    public function testAddJoinWithSubquery(): void
    {
        $state = new DeleteState();
        $subquery = new DbQuery();

        $state->addJoin([
            'join' => 'LEFT JOIN',
            'container' => $subquery,
            'alias' => 'sq',
            'constraint' => 'u.id = sq.user_id',
        ]);

        $joins = $state->getJoins();
        $this->assertCount(1, $joins);
        $this->assertInstanceOf(DbQuery::class, $joins[0]['container']);
    }

    public function testAddMultipleJoins(): void
    {
        $state = new DeleteState();
        $state->addJoin([
            'join' => 'INNER JOIN',
            'container' => 'orders',
            'alias' => 'o',
            'constraint' => 'u.id = o.user_id',
        ]);
        $state->addJoin([
            'join' => 'LEFT JOIN',
            'container' => 'products',
            'alias' => 'p',
            'constraint' => 'o.product_id = p.id',
        ]);

        $joins = $state->getJoins();
        $this->assertCount(2, $joins);
    }

    public function testAddJoinWithNullAlias(): void
    {
        $state = new DeleteState();
        $state->addJoin([
            'join' => 'INNER JOIN',
            'container' => 'orders',
            'alias' => null,
            'constraint' => 'users.id = orders.user_id',
        ]);

        $joins = $state->getJoins();
        $this->assertNull($joins[0]['alias']);
    }

    public function testAddJoinWithNullConstraint(): void
    {
        $state = new DeleteState();
        $state->addJoin([
            'join' => 'CROSS JOIN',
            'container' => 'categories',
            'alias' => 'c',
            'constraint' => null,
        ]);

        $joins = $state->getJoins();
        $this->assertNull($joins[0]['constraint']);
    }

    // ==================== OrderBy Tests ====================

    public function testGetOrderByReturnsEmptyArrayByDefault(): void
    {
        $state = new DeleteState();

        $this->assertIsArray($state->getOrderBy());
        $this->assertEmpty($state->getOrderBy());
    }

    public function testAddOrderByWithDefaultDirection(): void
    {
        $state = new DeleteState();
        $state->addOrderBy('created_at');

        $this->assertEquals(['created_at ASC'], $state->getOrderBy());
    }

    public function testAddOrderByWithDescDirection(): void
    {
        $state = new DeleteState();
        $state->addOrderBy('created_at', 'DESC');

        $this->assertEquals(['created_at DESC'], $state->getOrderBy());
    }

    public function testAddMultipleOrderBy(): void
    {
        $state = new DeleteState();
        $state->addOrderBy('status', 'ASC');
        $state->addOrderBy('created_at', 'DESC');
        $state->addOrderBy('id', 'ASC');

        $orderBy = $state->getOrderBy();
        $this->assertCount(3, $orderBy);
        $this->assertEquals('status ASC', $orderBy[0]);
        $this->assertEquals('created_at DESC', $orderBy[1]);
        $this->assertEquals('id ASC', $orderBy[2]);
    }

    // ==================== Limit Tests ====================

    public function testGetLimitReturnsNullByDefault(): void
    {
        $state = new DeleteState();

        $this->assertNull($state->getLimit());
    }

    public function testSetLimitStoresValue(): void
    {
        $state = new DeleteState();
        $state->setLimit(10);

        $this->assertEquals(10, $state->getLimit());
    }

    public function testSetLimitToNull(): void
    {
        $state = new DeleteState();
        $state->setLimit(10);
        $state->setLimit(null);

        $this->assertNull($state->getLimit());
    }

    public function testSetLimitIgnoresOffset(): void
    {
        $state = new DeleteState();
        $state->setLimit(10, 20);

        $this->assertEquals(10, $state->getLimit());
        $this->assertNull($state->getOffset());
    }

    // ==================== Offset Tests ====================

    public function testGetOffsetAlwaysReturnsNull(): void
    {
        $state = new DeleteState();

        $this->assertNull($state->getOffset());
    }

    public function testGetOffsetRemainsNullAfterSetLimit(): void
    {
        $state = new DeleteState();
        $state->setLimit(10, 50);

        $this->assertNull($state->getOffset());
    }

    // ==================== Complete State Tests ====================

    public function testCompleteDeleteState(): void
    {
        $state = new DeleteState();
        $state->setTable('users');
        $state->setAlias('u');
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
        $this->assertCount(1, $state->getJoins());
        $this->assertCount(1, $state->getOrderBy());
        $this->assertEquals(100, $state->getLimit());
        $this->assertNull($state->getOffset());
    }

    // ==================== Multiple Instances Tests ====================

    public function testMultipleInstancesAreIndependent(): void
    {
        $state1 = new DeleteState();
        $state1->setTable('users');
        $state1->setAlias('u');
        $state1->setLimit(10);

        $state2 = new DeleteState();
        $state2->setTable('orders');
        $state2->setAlias('o');
        $state2->setLimit(20);

        $this->assertEquals('users', $state1->getTable());
        $this->assertEquals('orders', $state2->getTable());
        $this->assertEquals('u', $state1->getAlias());
        $this->assertEquals('o', $state2->getAlias());
        $this->assertEquals(10, $state1->getLimit());
        $this->assertEquals(20, $state2->getLimit());
    }

    // ==================== State Modification Tests ====================

    public function testTableCanBeModifiedMultipleTimes(): void
    {
        $state = new DeleteState();
        $state->setTable('users');
        $this->assertEquals('users', $state->getTable());

        $state->setTable('orders');
        $this->assertEquals('orders', $state->getTable());
    }

    public function testAliasCanBeModifiedMultipleTimes(): void
    {
        $state = new DeleteState();
        $state->setAlias('u');
        $this->assertEquals('u', $state->getAlias());

        $state->setAlias('usr');
        $this->assertEquals('usr', $state->getAlias());
    }

    public function testLimitCanBeModifiedMultipleTimes(): void
    {
        $state = new DeleteState();
        $state->setLimit(10);
        $this->assertEquals(10, $state->getLimit());

        $state->setLimit(50);
        $this->assertEquals(50, $state->getLimit());
    }
}
