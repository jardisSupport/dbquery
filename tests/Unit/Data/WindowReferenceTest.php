<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Data;

use JardisSupport\DbQuery\Data\WindowReference;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for WindowReference
 *
 * Tests the window function reference to named windows.
 */
class WindowReferenceTest extends TestCase
{
    // ==================== Constructor Tests ====================

    public function testConstructorWithBasicParameters(): void
    {
        $ref = new WindowReference('ROW_NUMBER', 'my_window', 'row_num', null);

        $this->assertInstanceOf(WindowReference::class, $ref);
    }

    public function testConstructorWithFunctionArguments(): void
    {
        $ref = new WindowReference('SUM', 'dept_window', 'total_amount', 'amount');

        $this->assertInstanceOf(WindowReference::class, $ref);
    }

    // ==================== getFunction() Tests ====================

    public function testGetFunctionReturnsCorrectName(): void
    {
        $ref = new WindowReference('ROW_NUMBER', 'my_window', 'row_num', null);

        $this->assertEquals('ROW_NUMBER', $ref->getFunction());
    }

    public function testGetFunctionWithDifferentFunctions(): void
    {
        $ref1 = new WindowReference('RANK', 'window1', 'rank', null);
        $ref2 = new WindowReference('DENSE_RANK', 'window2', 'dense_rank', null);
        $ref3 = new WindowReference('SUM', 'window3', 'total', 'amount');

        $this->assertEquals('RANK', $ref1->getFunction());
        $this->assertEquals('DENSE_RANK', $ref2->getFunction());
        $this->assertEquals('SUM', $ref3->getFunction());
    }

    // ==================== getWindowName() Tests ====================

    public function testGetWindowNameReturnsCorrectValue(): void
    {
        $ref = new WindowReference('ROW_NUMBER', 'my_window', 'row_num', null);

        $this->assertEquals('my_window', $ref->getWindowName());
    }

    public function testGetWindowNameWithDifferentNames(): void
    {
        $ref1 = new WindowReference('RANK', 'dept_window', 'rank', null);
        $ref2 = new WindowReference('SUM', 'sales_window', 'total', 'amount');
        $ref3 = new WindowReference('AVG', 'price_window', 'avg_price', 'price');

        $this->assertEquals('dept_window', $ref1->getWindowName());
        $this->assertEquals('sales_window', $ref2->getWindowName());
        $this->assertEquals('price_window', $ref3->getWindowName());
    }

    // ==================== getAlias() Tests ====================

    public function testGetAliasReturnsCorrectValue(): void
    {
        $ref = new WindowReference('ROW_NUMBER', 'my_window', 'row_num', null);

        $this->assertEquals('row_num', $ref->getAlias());
    }

    public function testGetAliasWithDifferentAliases(): void
    {
        $ref1 = new WindowReference('RANK', 'window1', 'salary_rank', null);
        $ref2 = new WindowReference('SUM', 'window2', 'running_total', 'amount');

        $this->assertEquals('salary_rank', $ref1->getAlias());
        $this->assertEquals('running_total', $ref2->getAlias());
    }

    // ==================== getArgs() Tests ====================

    public function testGetArgsReturnsNullWhenNoArgs(): void
    {
        $ref = new WindowReference('ROW_NUMBER', 'my_window', 'row_num', null);

        $this->assertNull($ref->getArgs());
    }

    public function testGetArgsReturnsCorrectValue(): void
    {
        $ref = new WindowReference('SUM', 'my_window', 'total', 'amount');

        $this->assertEquals('amount', $ref->getArgs());
    }

    public function testGetArgsWithDifferentArguments(): void
    {
        $ref1 = new WindowReference('SUM', 'window1', 'total_sales', 'sales_amount');
        $ref2 = new WindowReference('AVG', 'window2', 'avg_price', 'price');
        $ref3 = new WindowReference('COUNT', 'window3', 'order_count', '*');

        $this->assertEquals('sales_amount', $ref1->getArgs());
        $this->assertEquals('price', $ref2->getArgs());
        $this->assertEquals('*', $ref3->getArgs());
    }

    // ==================== Common Window Functions Tests ====================

    public function testRowNumberReference(): void
    {
        $ref = new WindowReference('ROW_NUMBER', 'dept_window', 'row_num', null);

        $this->assertEquals('ROW_NUMBER', $ref->getFunction());
        $this->assertEquals('dept_window', $ref->getWindowName());
        $this->assertEquals('row_num', $ref->getAlias());
        $this->assertNull($ref->getArgs());
    }

    public function testRankReference(): void
    {
        $ref = new WindowReference('RANK', 'salary_window', 'salary_rank', null);

        $this->assertEquals('RANK', $ref->getFunction());
        $this->assertEquals('salary_window', $ref->getWindowName());
        $this->assertEquals('salary_rank', $ref->getAlias());
        $this->assertNull($ref->getArgs());
    }

    public function testDenseRankReference(): void
    {
        $ref = new WindowReference('DENSE_RANK', 'score_window', 'dense_rank', null);

        $this->assertEquals('DENSE_RANK', $ref->getFunction());
        $this->assertEquals('score_window', $ref->getWindowName());
        $this->assertEquals('dense_rank', $ref->getAlias());
        $this->assertNull($ref->getArgs());
    }

    public function testSumReference(): void
    {
        $ref = new WindowReference('SUM', 'transaction_window', 'running_balance', 'amount');

        $this->assertEquals('SUM', $ref->getFunction());
        $this->assertEquals('transaction_window', $ref->getWindowName());
        $this->assertEquals('running_balance', $ref->getAlias());
        $this->assertEquals('amount', $ref->getArgs());
    }

    public function testAvgReference(): void
    {
        $ref = new WindowReference('AVG', 'dept_window', 'avg_salary', 'salary');

        $this->assertEquals('AVG', $ref->getFunction());
        $this->assertEquals('dept_window', $ref->getWindowName());
        $this->assertEquals('avg_salary', $ref->getAlias());
        $this->assertEquals('salary', $ref->getArgs());
    }

    public function testCountReference(): void
    {
        $ref = new WindowReference('COUNT', 'category_window', 'item_count', '*');

        $this->assertEquals('COUNT', $ref->getFunction());
        $this->assertEquals('category_window', $ref->getWindowName());
        $this->assertEquals('item_count', $ref->getAlias());
        $this->assertEquals('*', $ref->getArgs());
    }

    public function testMinReference(): void
    {
        $ref = new WindowReference('MIN', 'price_window', 'min_price', 'price');

        $this->assertEquals('MIN', $ref->getFunction());
        $this->assertEquals('price_window', $ref->getWindowName());
        $this->assertEquals('min_price', $ref->getAlias());
        $this->assertEquals('price', $ref->getArgs());
    }

    public function testMaxReference(): void
    {
        $ref = new WindowReference('MAX', 'price_window', 'max_price', 'price');

        $this->assertEquals('MAX', $ref->getFunction());
        $this->assertEquals('price_window', $ref->getWindowName());
        $this->assertEquals('max_price', $ref->getAlias());
        $this->assertEquals('price', $ref->getArgs());
    }

    // ==================== Multiple Instances Tests ====================

    public function testMultipleInstancesAreIndependent(): void
    {
        $ref1 = new WindowReference('ROW_NUMBER', 'window1', 'row1', null);
        $ref2 = new WindowReference('RANK', 'window2', 'rank1', null);

        $this->assertEquals('ROW_NUMBER', $ref1->getFunction());
        $this->assertEquals('RANK', $ref2->getFunction());
        $this->assertEquals('window1', $ref1->getWindowName());
        $this->assertEquals('window2', $ref2->getWindowName());
        $this->assertEquals('row1', $ref1->getAlias());
        $this->assertEquals('rank1', $ref2->getAlias());
    }

    public function testMultipleReferencesToSameWindow(): void
    {
        $ref1 = new WindowReference('ROW_NUMBER', 'my_window', 'row_num', null);
        $ref2 = new WindowReference('RANK', 'my_window', 'rank', null);
        $ref3 = new WindowReference('SUM', 'my_window', 'total', 'amount');

        $this->assertEquals('my_window', $ref1->getWindowName());
        $this->assertEquals('my_window', $ref2->getWindowName());
        $this->assertEquals('my_window', $ref3->getWindowName());

        $this->assertEquals('ROW_NUMBER', $ref1->getFunction());
        $this->assertEquals('RANK', $ref2->getFunction());
        $this->assertEquals('SUM', $ref3->getFunction());
    }

    // ==================== Immutability Tests ====================

    public function testWindowReferenceIsImmutable(): void
    {
        $ref = new WindowReference('ROW_NUMBER', 'my_window', 'row_num', null);

        // Multiple calls should return the same values
        $this->assertEquals('ROW_NUMBER', $ref->getFunction());
        $this->assertEquals('ROW_NUMBER', $ref->getFunction());

        $this->assertEquals('my_window', $ref->getWindowName());
        $this->assertEquals('my_window', $ref->getWindowName());

        $this->assertEquals('row_num', $ref->getAlias());
        $this->assertEquals('row_num', $ref->getAlias());

        $this->assertNull($ref->getArgs());
        $this->assertNull($ref->getArgs());
    }

    // ==================== Edge Cases Tests ====================

    public function testFunctionNameCaseSensitivity(): void
    {
        $ref1 = new WindowReference('row_number', 'window1', 'row_num', null);
        $ref2 = new WindowReference('ROW_NUMBER', 'window2', 'row_num2', null);

        $this->assertEquals('row_number', $ref1->getFunction());
        $this->assertEquals('ROW_NUMBER', $ref2->getFunction());
    }

    public function testWindowNameWithUnderscores(): void
    {
        $ref = new WindowReference('ROW_NUMBER', 'dept_location_window', 'row_num', null);

        $this->assertEquals('dept_location_window', $ref->getWindowName());
    }

    public function testAliasWithUnderscores(): void
    {
        $ref = new WindowReference('ROW_NUMBER', 'my_window', 'row_num_alias', null);

        $this->assertEquals('row_num_alias', $ref->getAlias());
    }

    public function testArgsWithComplexExpression(): void
    {
        $ref = new WindowReference('SUM', 'my_window', 'total', 'price * quantity');

        $this->assertEquals('price * quantity', $ref->getArgs());
    }
}
