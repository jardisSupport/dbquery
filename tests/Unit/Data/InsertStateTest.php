<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Data;

use JardisSupport\DbQuery\Data\InsertState;
use JardisSupport\DbQuery\DbQuery;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for InsertState
 *
 * Tests the state management for INSERT queries.
 */
class InsertStateTest extends TestCase
{
    // ==================== Constructor Tests ====================

    public function testConstructorCreatesEmptyState(): void
    {
        $state = new InsertState();

        $this->assertInstanceOf(InsertState::class, $state);
        $this->assertEquals('', $state->getTable());
        $this->assertEmpty($state->getFields());
        $this->assertEmpty($state->getValueRows());
        $this->assertNull($state->getSelectQuery());
    }

    // ==================== Table Tests ====================

    public function testSetAndGetTable(): void
    {
        $state = new InsertState();
        $state->setTable('users');

        $this->assertEquals('users', $state->getTable());
    }

    // ==================== Fields Tests ====================

    public function testSetAndGetFields(): void
    {
        $state = new InsertState();
        $state->setFields(['name', 'email', 'status']);

        $this->assertEquals(['name', 'email', 'status'], $state->getFields());
    }

    public function testSetFieldsWithEmptyArray(): void
    {
        $state = new InsertState();
        $state->setFields([]);

        $this->assertEmpty($state->getFields());
    }

    // ==================== Value Rows Tests ====================

    public function testAddValueRow(): void
    {
        $state = new InsertState();
        $state->addValueRow(['John', 'john@example.com', 'active']);

        $rows = $state->getValueRows();
        $this->assertCount(1, $rows);
        $this->assertEquals(['John', 'john@example.com', 'active'], $rows[0]);
    }

    public function testAddMultipleValueRows(): void
    {
        $state = new InsertState();
        $state->addValueRow(['John', 'john@example.com', 'active']);
        $state->addValueRow(['Jane', 'jane@example.com', 'pending']);

        $rows = $state->getValueRows();
        $this->assertCount(2, $rows);
        $this->assertEquals(['John', 'john@example.com', 'active'], $rows[0]);
        $this->assertEquals(['Jane', 'jane@example.com', 'pending'], $rows[1]);
    }

    public function testClearValueRows(): void
    {
        $state = new InsertState();
        $state->addValueRow(['John', 'john@example.com', 'active']);
        $state->addValueRow(['Jane', 'jane@example.com', 'pending']);

        $state->clearValueRows();

        $this->assertEmpty($state->getValueRows());
    }

    // ==================== Select Query Tests ====================

    public function testSetAndGetSelectQuery(): void
    {
        $state = new InsertState();
        $query = new DbQuery();

        $state->setSelectQuery($query);

        $this->assertInstanceOf(DbQuery::class, $state->getSelectQuery());
        $this->assertSame($query, $state->getSelectQuery());
    }

    public function testSetSelectQueryToNull(): void
    {
        $state = new InsertState();
        $query = new DbQuery();

        $state->setSelectQuery($query);
        $state->setSelectQuery(null);

        $this->assertNull($state->getSelectQuery());
    }

    // ==================== OrIgnore Tests ====================

    public function testIsOrIgnoreDefaultsFalse(): void
    {
        $state = new InsertState();

        $this->assertFalse($state->isOrIgnore());
    }

    public function testSetOrIgnore(): void
    {
        $state = new InsertState();
        $state->setOrIgnore(true);

        $this->assertTrue($state->isOrIgnore());
    }

    public function testSetOrIgnoreToFalse(): void
    {
        $state = new InsertState();
        $state->setOrIgnore(true);
        $state->setOrIgnore(false);

        $this->assertFalse($state->isOrIgnore());
    }

    // ==================== Replace Tests ====================

    public function testIsReplaceDefaultsFalse(): void
    {
        $state = new InsertState();

        $this->assertFalse($state->isReplace());
    }

    public function testSetReplace(): void
    {
        $state = new InsertState();
        $state->setReplace(true);

        $this->assertTrue($state->isReplace());
    }

    public function testSetReplaceToFalse(): void
    {
        $state = new InsertState();
        $state->setReplace(true);
        $state->setReplace(false);

        $this->assertFalse($state->isReplace());
    }

    // ==================== OnDuplicateKeyUpdate Tests ====================

    public function testGetOnDuplicateKeyUpdateReturnsEmptyArrayByDefault(): void
    {
        $state = new InsertState();

        $this->assertEmpty($state->getOnDuplicateKeyUpdate());
    }

    public function testAddOnDuplicateKeyUpdate(): void
    {
        $state = new InsertState();
        $state->addOnDuplicateKeyUpdate('status', 'updated');

        $updates = $state->getOnDuplicateKeyUpdate();
        $this->assertCount(1, $updates);
        $this->assertEquals('updated', $updates['status']);
    }

    public function testAddMultipleOnDuplicateKeyUpdate(): void
    {
        $state = new InsertState();
        $state->addOnDuplicateKeyUpdate('status', 'updated');
        $state->addOnDuplicateKeyUpdate('modified_at', 'NOW()');

        $updates = $state->getOnDuplicateKeyUpdate();
        $this->assertCount(2, $updates);
        $this->assertEquals('updated', $updates['status']);
        $this->assertEquals('NOW()', $updates['modified_at']);
    }

    // ==================== OnConflictColumns Tests ====================

    public function testGetOnConflictColumnsReturnsEmptyArrayByDefault(): void
    {
        $state = new InsertState();

        $this->assertEmpty($state->getOnConflictColumns());
    }

    public function testSetOnConflictColumns(): void
    {
        $state = new InsertState();
        $state->setOnConflictColumns(['email']);

        $this->assertEquals(['email'], $state->getOnConflictColumns());
    }

    public function testSetOnConflictColumnsWithMultiple(): void
    {
        $state = new InsertState();
        $state->setOnConflictColumns(['email', 'username']);

        $this->assertEquals(['email', 'username'], $state->getOnConflictColumns());
    }

    // ==================== DoUpdateFields Tests ====================

    public function testGetDoUpdateFieldsReturnsEmptyArrayByDefault(): void
    {
        $state = new InsertState();

        $this->assertEmpty($state->getDoUpdateFields());
    }

    public function testSetDoUpdateFields(): void
    {
        $state = new InsertState();
        $state->setDoUpdateFields(['status' => 'active', 'updated_at' => 'NOW()']);

        $fields = $state->getDoUpdateFields();
        $this->assertCount(2, $fields);
        $this->assertEquals('active', $fields['status']);
        $this->assertEquals('NOW()', $fields['updated_at']);
    }

    // ==================== DoNothing Tests ====================

    public function testIsDoNothingDefaultsFalse(): void
    {
        $state = new InsertState();

        $this->assertFalse($state->isDoNothing());
    }

    public function testSetDoNothing(): void
    {
        $state = new InsertState();
        $state->setDoNothing(true);

        $this->assertTrue($state->isDoNothing());
    }

    public function testSetDoNothingToFalse(): void
    {
        $state = new InsertState();
        $state->setDoNothing(true);
        $state->setDoNothing(false);

        $this->assertFalse($state->isDoNothing());
    }

    // ==================== Complete State Tests ====================

    public function testCompleteInsertValuesState(): void
    {
        $state = new InsertState();
        $state->setTable('users');
        $state->setFields(['name', 'email', 'status']);
        $state->addValueRow(['John', 'john@example.com', 'active']);
        $state->addValueRow(['Jane', 'jane@example.com', 'pending']);

        $this->assertEquals('users', $state->getTable());
        $this->assertCount(3, $state->getFields());
        $this->assertCount(2, $state->getValueRows());
    }

    public function testCompleteInsertSelectState(): void
    {
        $state = new InsertState();
        $query = new DbQuery();

        $state->setTable('users_archive');
        $state->setFields(['name', 'email', 'status']);
        $state->setSelectQuery($query);

        $this->assertEquals('users_archive', $state->getTable());
        $this->assertCount(3, $state->getFields());
        $this->assertInstanceOf(DbQuery::class, $state->getSelectQuery());
    }

    public function testCompleteUpsertStateMySql(): void
    {
        $state = new InsertState();
        $state->setTable('users');
        $state->setFields(['email', 'name', 'status']);
        $state->addValueRow(['john@example.com', 'John', 'active']);
        $state->addOnDuplicateKeyUpdate('name', 'John');
        $state->addOnDuplicateKeyUpdate('status', 'active');

        $this->assertEquals('users', $state->getTable());
        $this->assertCount(1, $state->getValueRows());
        $this->assertCount(2, $state->getOnDuplicateKeyUpdate());
    }

    public function testCompleteUpsertStatePostgres(): void
    {
        $state = new InsertState();
        $state->setTable('users');
        $state->setFields(['email', 'name', 'status']);
        $state->addValueRow(['john@example.com', 'John', 'active']);
        $state->setOnConflictColumns(['email']);
        $state->setDoUpdateFields(['name' => 'John', 'status' => 'active']);

        $this->assertEquals('users', $state->getTable());
        $this->assertEquals(['email'], $state->getOnConflictColumns());
        $this->assertCount(2, $state->getDoUpdateFields());
    }

    // ==================== Multiple Instances Tests ====================

    public function testMultipleInstancesAreIndependent(): void
    {
        $state1 = new InsertState();
        $state1->setTable('users');
        $state1->setFields(['name', 'email']);

        $state2 = new InsertState();
        $state2->setTable('orders');
        $state2->setFields(['user_id', 'total']);

        $this->assertEquals('users', $state1->getTable());
        $this->assertEquals('orders', $state2->getTable());
        $this->assertCount(2, $state1->getFields());
        $this->assertCount(2, $state2->getFields());
    }
}
