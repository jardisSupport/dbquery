<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Data;

use JardisSupport\DbQuery\Data\ExecuteResult;
use JardisSupport\Contract\DbQuery\ExecuteResultInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for ExecuteResult
 *
 * Tests the DML operation result implementation with affected rows and last insert ID.
 */
class ExecuteResultTest extends TestCase
{
    // ==================== Constructor / Interface Tests ====================

    public function testImplementsExecuteResultInterface(): void
    {
        $result = new ExecuteResult(0, false);

        $this->assertInstanceOf(ExecuteResultInterface::class, $result);
    }

    // ==================== affectedRows() Method Tests ====================

    public function testAffectedRowsReturnsZero(): void
    {
        $result = new ExecuteResult(0, false);

        $this->assertSame(0, $result->affectedRows());
    }

    public function testAffectedRowsReturnsSingleRow(): void
    {
        $result = new ExecuteResult(1, '42');

        $this->assertSame(1, $result->affectedRows());
    }

    public function testAffectedRowsReturnsMultipleRows(): void
    {
        $result = new ExecuteResult(150, false);

        $this->assertSame(150, $result->affectedRows());
    }

    // ==================== lastInsertId() Method Tests ====================

    public function testLastInsertIdReturnsFalseWhenNotApplicable(): void
    {
        $result = new ExecuteResult(3, false);

        $this->assertFalse($result->lastInsertId());
    }

    public function testLastInsertIdReturnsStringId(): void
    {
        $result = new ExecuteResult(1, '99');

        $this->assertSame('99', $result->lastInsertId());
    }

    public function testLastInsertIdReturnsZeroString(): void
    {
        $result = new ExecuteResult(1, '0');

        $this->assertSame('0', $result->lastInsertId());
    }

    public function testLastInsertIdReturnsLargeId(): void
    {
        $result = new ExecuteResult(1, '9999999999');

        $this->assertSame('9999999999', $result->lastInsertId());
    }

    // ==================== Combination Tests ====================

    public function testInsertResultWithAffectedRowAndInsertId(): void
    {
        $result = new ExecuteResult(1, '7');

        $this->assertSame(1, $result->affectedRows());
        $this->assertSame('7', $result->lastInsertId());
    }

    public function testUpdateResultWithMultipleRowsAndNoInsertId(): void
    {
        $result = new ExecuteResult(25, false);

        $this->assertSame(25, $result->affectedRows());
        $this->assertFalse($result->lastInsertId());
    }

    public function testDeleteResultWithZeroAffectedRows(): void
    {
        $result = new ExecuteResult(0, false);

        $this->assertSame(0, $result->affectedRows());
        $this->assertFalse($result->lastInsertId());
    }

    public function testMultipleInstancesAreIndependent(): void
    {
        $result1 = new ExecuteResult(1, '10');
        $result2 = new ExecuteResult(5, false);

        $this->assertSame(1, $result1->affectedRows());
        $this->assertSame('10', $result1->lastInsertId());

        $this->assertSame(5, $result2->affectedRows());
        $this->assertFalse($result2->lastInsertId());
    }
}
