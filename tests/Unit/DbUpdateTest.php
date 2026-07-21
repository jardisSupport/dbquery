<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit;

use JardisSupport\DbQuery\DbUpdate;
use JardisSupport\Contract\DbQuery\DbUpdateBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for DbUpdate
 *
 * Tests the main UPDATE query builder functionality.
 * Note: Detailed SQL generation tests are in tests/unit/command/update/
 */
class DbUpdateTest extends TestCase
{
    // ==================== Constructor Tests ====================

    public function testConstructorCreatesInstance(): void
    {
        $update = new DbUpdate();

        $this->assertInstanceOf(DbUpdate::class, $update);
        $this->assertInstanceOf(DbUpdateBuilderInterface::class, $update);
    }

    // ==================== Fluent Interface Tests ====================

    public function testTableReturnsInstance(): void
    {
        $update = new DbUpdate();
        $result = $update->table('users');

        $this->assertSame($update, $result);
    }

    public function testSetReturnsInstance(): void
    {
        $update = new DbUpdate();
        $result = $update->table('users')
            ->set('name', 'John');

        $this->assertSame($update, $result);
    }

    public function testWhereReturnsConditionBuilder(): void
    {
        $update = new DbUpdate();
        $result = $update->table('users')
            ->set('name', 'John')
            ->where('id');

        $this->assertNotNull($result);
    }

    public function testLimitReturnsInstance(): void
    {
        $update = new DbUpdate();
        $result = $update->table('users')
            ->set('name', 'John')
            ->limit(10);

        $this->assertSame($update, $result);
    }

    public function testOrderByReturnsInstance(): void
    {
        $update = new DbUpdate();
        $result = $update->table('users')
            ->set('name', 'John')
            ->orderBy('id ASC');

        $this->assertSame($update, $result);
    }

    // ==================== Method Chaining Tests ====================

    public function testBasicMethodChaining(): void
    {
        $update = new DbUpdate();

        $result = $update->table('users')
            ->set('name', 'John')
            ->set('status', 'active')
            ->limit(10);

        $this->assertInstanceOf(DbUpdate::class, $result);
    }

    public function testComplexMethodChaining(): void
    {
        $update = new DbUpdate();

        $result = $update->table('users', 'u')
            ->set('status', 'inactive')
            ->orderBy('u.id ASC')
            ->limit(100);

        $this->assertInstanceOf(DbUpdate::class, $result);
    }

    // ==================== Ignore Method Tests ====================

    public function testIgnoreReturnsInstance(): void
    {
        $update = new DbUpdate();
        $result = $update->table('users')
            ->set('name', 'John')
            ->ignore();

        $this->assertSame($update, $result);
    }

    // ==================== Multiple Instances Tests ====================

    public function testMultipleInstancesAreIndependent(): void
    {
        $update1 = new DbUpdate();
        $update1->table('users')->set('name', 'John');

        $update2 = new DbUpdate();
        $update2->table('orders')->set('status', 'shipped');

        $this->assertNotSame($update1, $update2);
    }
}
