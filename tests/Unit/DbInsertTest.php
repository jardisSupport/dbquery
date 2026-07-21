<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit;

use InvalidArgumentException;
use JardisSupport\DbQuery\DbInsert;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Contract\DbQuery\DbInsertBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for DbInsert
 *
 * Tests the main INSERT query builder functionality.
 * Note: Detailed SQL generation tests are in tests/unit/command/insert/
 */
class DbInsertTest extends TestCase
{
    // ==================== Constructor Tests ====================

    public function testConstructorCreatesInstance(): void
    {
        $insert = new DbInsert();

        $this->assertInstanceOf(DbInsert::class, $insert);
        $this->assertInstanceOf(DbInsertBuilderInterface::class, $insert);
    }

    // ==================== Fluent Interface Tests ====================

    public function testIntoReturnsInstance(): void
    {
        $insert = new DbInsert();
        $result = $insert->into('users');

        $this->assertSame($insert, $result);
    }

    public function testFieldsReturnsInstance(): void
    {
        $insert = new DbInsert();
        $result = $insert->fields('name', 'email');

        $this->assertSame($insert, $result);
    }

    public function testValuesReturnsInstance(): void
    {
        $insert = new DbInsert();
        $result = $insert->into('users')
            ->fields('name', 'email')
            ->values('John', 'john@example.com');

        $this->assertSame($insert, $result);
    }

    // ==================== Method Chaining Tests ====================

    public function testBasicMethodChaining(): void
    {
        $insert = new DbInsert();

        $result = $insert->into('users')
            ->fields('name', 'email', 'status')
            ->values('John', 'john@example.com', 'active');

        $this->assertInstanceOf(DbInsert::class, $result);
    }

    public function testMultipleValuesChaining(): void
    {
        $insert = new DbInsert();

        $result = $insert->into('users')
            ->fields('name', 'email')
            ->values('John', 'john@example.com')
            ->values('Jane', 'jane@example.com');

        $this->assertInstanceOf(DbInsert::class, $result);
    }

    // ==================== Validation Tests ====================

    public function testValuesThrowsExceptionWithoutFields(): void
    {
        $insert = new DbInsert();
        $insert->into('users');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Column names must be specified');

        $insert->values('John', 'john@example.com');
    }

    public function testValuesThrowsExceptionWhenCountMismatch(): void
    {
        $insert = new DbInsert();
        $insert->into('users')
            ->fields('name', 'email');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Number of values');

        $insert->values('John'); // Only 1 value but 2 fields
    }

    // ==================== Set Method Tests ====================

    public function testSetReturnsInstance(): void
    {
        $insert = new DbInsert();
        $result = $insert->into('users')
            ->set(['name' => 'John', 'email' => 'john@example.com']);

        $this->assertSame($insert, $result);
    }

    // ==================== FromSelect Tests ====================

    public function testFromSelectReturnsInstance(): void
    {
        $select = new DbQuery();
        $select->select('name, email')->from('temp_users');

        $insert = new DbInsert();
        $result = $insert->into('users')
            ->fields('name', 'email')
            ->fromSelect($select);

        $this->assertSame($insert, $result);
    }

    // ==================== Upsert Methods Tests ====================

    public function testOrIgnoreReturnsInstance(): void
    {
        $insert = new DbInsert();
        $result = $insert->into('users')
            ->fields('email', 'name')
            ->values('john@example.com', 'John')
            ->orIgnore();

        $this->assertSame($insert, $result);
    }

    public function testReplaceReturnsInstance(): void
    {
        $insert = new DbInsert();
        $result = $insert->into('users')
            ->fields('email', 'name')
            ->values('john@example.com', 'John')
            ->replace();

        $this->assertSame($insert, $result);
    }

    public function testOnConflictReturnsInstance(): void
    {
        $insert = new DbInsert();
        $result = $insert->into('users')
            ->fields('email', 'name')
            ->values('john@example.com', 'John')
            ->onConflict('email');

        $this->assertSame($insert, $result);
    }

    public function testDoNothingReturnsInstance(): void
    {
        $insert = new DbInsert();
        $result = $insert->into('users')
            ->fields('email', 'name')
            ->values('john@example.com', 'John')
            ->onConflict('email')
            ->doNothing();

        $this->assertSame($insert, $result);
    }

    public function testDoUpdateReturnsInstance(): void
    {
        $insert = new DbInsert();
        $result = $insert->into('users')
            ->fields('email', 'name')
            ->values('john@example.com', 'John')
            ->onConflict('email')
            ->doUpdate(['name' => 'John Updated']);

        $this->assertSame($insert, $result);
    }

    public function testOnDuplicateKeyUpdateReturnsInstance(): void
    {
        $insert = new DbInsert();
        $result = $insert->into('users')
            ->fields('email', 'name')
            ->values('john@example.com', 'John')
            ->onDuplicateKeyUpdate('name', 'John Updated');

        $this->assertSame($insert, $result);
    }

    // ==================== Multiple Instances Tests ====================

    public function testMultipleInstancesAreIndependent(): void
    {
        $insert1 = new DbInsert();
        $insert1->into('users')->fields('name')->values('John');

        $insert2 = new DbInsert();
        $insert2->into('orders')->fields('total')->values(100);

        $this->assertNotSame($insert1, $insert2);
    }
}
