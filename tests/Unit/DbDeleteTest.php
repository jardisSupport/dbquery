<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit;

use JardisSupport\DbQuery\DbDelete;
use JardisSupport\Contract\DbQuery\DbDeleteBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for DbDelete
 *
 * Tests the main DELETE query builder functionality.
 * Note: Detailed SQL generation tests are in tests/unit/command/delete/
 */
class DbDeleteTest extends TestCase
{
    // ==================== Constructor Tests ====================

    public function testConstructorCreatesInstance(): void
    {
        $delete = new DbDelete();

        $this->assertInstanceOf(DbDelete::class, $delete);
        $this->assertInstanceOf(DbDeleteBuilderInterface::class, $delete);
    }

    // ==================== Fluent Interface Tests ====================

    public function testFromReturnsInstance(): void
    {
        $delete = new DbDelete();
        $result = $delete->from('users');

        $this->assertSame($delete, $result);
    }

    public function testWhereReturnsConditionBuilder(): void
    {
        $delete = new DbDelete();
        $result = $delete->from('users')
            ->where('id');

        $this->assertNotNull($result);
    }

    public function testAndReturnsConditionBuilder(): void
    {
        $delete = new DbDelete();
        $result = $delete->from('users')
            ->where('id')->equals(1)
            ->and('status');

        $this->assertNotNull($result);
    }

    public function testOrReturnsConditionBuilder(): void
    {
        $delete = new DbDelete();
        $result = $delete->from('users')
            ->where('id')->equals(1)
            ->or('status');

        $this->assertNotNull($result);
    }

    public function testWhereJsonReturnsJsonConditionBuilder(): void
    {
        $delete = new DbDelete();
        $result = $delete->from('users')
            ->whereJson('data');

        $this->assertNotNull($result);
    }

    public function testAndJsonReturnsJsonConditionBuilder(): void
    {
        $delete = new DbDelete();
        $result = $delete->from('users')
            ->where('id')->equals(1)
            ->andJson('data');

        $this->assertNotNull($result);
    }

    public function testOrJsonReturnsJsonConditionBuilder(): void
    {
        $delete = new DbDelete();
        $result = $delete->from('users')
            ->where('id')->equals(1)
            ->orJson('data');

        $this->assertNotNull($result);
    }

    public function testExistsReturnsInstance(): void
    {
        $delete = new DbDelete();
        $subquery = new \JardisSupport\DbQuery\DbQuery();
        $subquery->select('1')->from('orders')->where('user_id')->equals(1);

        $result = $delete->from('users')
            ->where()->exists($subquery);

        $this->assertInstanceOf(DbDelete::class, $result);
    }

    public function testNotExistsReturnsInstance(): void
    {
        $delete = new DbDelete();
        $subquery = new \JardisSupport\DbQuery\DbQuery();
        $subquery->select('1')->from('orders')->where('user_id')->equals(1);

        $result = $delete->from('users')
            ->where()->notExists($subquery);

        $this->assertInstanceOf(DbDelete::class, $result);
    }

    public function testInnerJoinReturnsInstance(): void
    {
        $delete = new DbDelete();
        $result = $delete->from('users')
            ->innerJoin('orders', 'users.id = orders.user_id');

        $this->assertSame($delete, $result);
    }

    public function testLeftJoinReturnsInstance(): void
    {
        $delete = new DbDelete();
        $result = $delete->from('users')
            ->leftJoin('orders', 'users.id = orders.user_id');

        $this->assertSame($delete, $result);
    }

    public function testLimitReturnsInstance(): void
    {
        $delete = new DbDelete();
        $result = $delete->from('users')
            ->limit(10);

        $this->assertSame($delete, $result);
    }

    public function testOrderByReturnsInstance(): void
    {
        $delete = new DbDelete();
        $result = $delete->from('users')
            ->orderBy('id ASC');

        $this->assertSame($delete, $result);
    }

    // ==================== Method Chaining Tests ====================

    public function testBasicMethodChaining(): void
    {
        $delete = new DbDelete();

        $result = $delete->from('users')
            ->orderBy('id ASC')
            ->limit(100);

        $this->assertInstanceOf(DbDelete::class, $result);
    }

    public function testComplexMethodChaining(): void
    {
        $delete = new DbDelete();

        $result = $delete->from('users', 'u')
            ->orderBy('u.created_at ASC')
            ->limit(50);

        $this->assertInstanceOf(DbDelete::class, $result);
    }

    // ==================== Multiple Instances Tests ====================

    public function testMultipleInstancesAreIndependent(): void
    {
        $delete1 = new DbDelete();
        $delete1->from('users');

        $delete2 = new DbDelete();
        $delete2->from('orders');

        $this->assertNotSame($delete1, $delete2);
    }
}
