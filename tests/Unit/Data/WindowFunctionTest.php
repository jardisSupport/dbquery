<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Data;

use JardisSupport\DbQuery\Data\WindowFunction;
use JardisSupport\DbQuery\Data\WindowSpec;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for WindowFunction
 *
 * Tests the window function wrapper with its specification.
 */
class WindowFunctionTest extends TestCase
{
    // ==================== Constructor Tests ====================

    public function testConstructorWithBasicParameters(): void
    {
        $spec = new WindowSpec();
        $windowFunc = new WindowFunction('ROW_NUMBER', 'row_num', null, $spec);

        $this->assertInstanceOf(WindowFunction::class, $windowFunc);
    }

    public function testConstructorWithFunctionArguments(): void
    {
        $spec = new WindowSpec();
        $windowFunc = new WindowFunction('SUM', 'total_amount', 'amount', $spec);

        $this->assertInstanceOf(WindowFunction::class, $windowFunc);
    }

    public function testConstructorWithComplexSpec(): void
    {
        $spec = new WindowSpec();
        $spec->addPartition('department');
        $spec->addOrder('salary', 'DESC');

        $windowFunc = new WindowFunction('RANK', 'salary_rank', null, $spec);

        $this->assertInstanceOf(WindowFunction::class, $windowFunc);
    }

    // ==================== getFunction() Tests ====================

    public function testGetFunctionReturnsCorrectName(): void
    {
        $spec = new WindowSpec();
        $windowFunc = new WindowFunction('ROW_NUMBER', 'row_num', null, $spec);

        $this->assertEquals('ROW_NUMBER', $windowFunc->getFunction());
    }

    public function testGetFunctionWithDifferentFunctions(): void
    {
        $spec = new WindowSpec();

        $func1 = new WindowFunction('RANK', 'rank', null, $spec);
        $func2 = new WindowFunction('DENSE_RANK', 'dense_rank', null, $spec);
        $func3 = new WindowFunction('SUM', 'total', 'amount', $spec);

        $this->assertEquals('RANK', $func1->getFunction());
        $this->assertEquals('DENSE_RANK', $func2->getFunction());
        $this->assertEquals('SUM', $func3->getFunction());
    }

    // ==================== getAlias() Tests ====================

    public function testGetAliasReturnsCorrectValue(): void
    {
        $spec = new WindowSpec();
        $windowFunc = new WindowFunction('ROW_NUMBER', 'row_num', null, $spec);

        $this->assertEquals('row_num', $windowFunc->getAlias());
    }

    public function testGetAliasWithDifferentAliases(): void
    {
        $spec = new WindowSpec();

        $func1 = new WindowFunction('RANK', 'salary_rank', null, $spec);
        $func2 = new WindowFunction('SUM', 'running_total', 'amount', $spec);

        $this->assertEquals('salary_rank', $func1->getAlias());
        $this->assertEquals('running_total', $func2->getAlias());
    }

    // ==================== getArgs() Tests ====================

    public function testGetArgsReturnsNullWhenNoArgs(): void
    {
        $spec = new WindowSpec();
        $windowFunc = new WindowFunction('ROW_NUMBER', 'row_num', null, $spec);

        $this->assertNull($windowFunc->getArgs());
    }

    public function testGetArgsReturnsCorrectValue(): void
    {
        $spec = new WindowSpec();
        $windowFunc = new WindowFunction('SUM', 'total', 'amount', $spec);

        $this->assertEquals('amount', $windowFunc->getArgs());
    }

    public function testGetArgsWithDifferentArguments(): void
    {
        $spec = new WindowSpec();

        $func1 = new WindowFunction('SUM', 'total_sales', 'sales_amount', $spec);
        $func2 = new WindowFunction('AVG', 'avg_price', 'price', $spec);
        $func3 = new WindowFunction('COUNT', 'order_count', '*', $spec);

        $this->assertEquals('sales_amount', $func1->getArgs());
        $this->assertEquals('price', $func2->getArgs());
        $this->assertEquals('*', $func3->getArgs());
    }

    // ==================== getSpec() Tests ====================

    public function testGetSpecReturnsWindowSpec(): void
    {
        $spec = new WindowSpec();
        $windowFunc = new WindowFunction('ROW_NUMBER', 'row_num', null, $spec);

        $this->assertInstanceOf(WindowSpec::class, $windowFunc->getSpec());
        $this->assertSame($spec, $windowFunc->getSpec());
    }

    public function testGetSpecWithPartitionBy(): void
    {
        $spec = new WindowSpec();
        $spec->addPartition('department');

        $windowFunc = new WindowFunction('ROW_NUMBER', 'row_num', null, $spec);

        $retrievedSpec = $windowFunc->getSpec();
        $this->assertEquals(['department'], $retrievedSpec->getPartitionBy());
    }

    public function testGetSpecWithOrderBy(): void
    {
        $spec = new WindowSpec();
        $spec->addOrder('salary', 'DESC');

        $windowFunc = new WindowFunction('RANK', 'salary_rank', null, $spec);

        $retrievedSpec = $windowFunc->getSpec();
        $this->assertEquals(['salary DESC'], $retrievedSpec->getOrderBy());
    }

    public function testGetSpecWithCompleteSpecification(): void
    {
        $spec = new WindowSpec();
        $spec->addPartition('department');
        $spec->addPartition('location');
        $spec->addOrder('salary', 'DESC');
        $spec->addOrder('hire_date', 'ASC');
        $spec->setFrame('ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');

        $windowFunc = new WindowFunction('SUM', 'running_total', 'salary', $spec);

        $retrievedSpec = $windowFunc->getSpec();
        $this->assertEquals(['department', 'location'], $retrievedSpec->getPartitionBy());
        $this->assertEquals(['salary DESC', 'hire_date ASC'], $retrievedSpec->getOrderBy());
        $this->assertEquals('ROWS', $retrievedSpec->getFrameType());
        $this->assertEquals('UNBOUNDED PRECEDING', $retrievedSpec->getFrameStart());
        $this->assertEquals('CURRENT ROW', $retrievedSpec->getFrameEnd());
    }

    // ==================== Common Window Functions Tests ====================

    public function testRowNumberFunction(): void
    {
        $spec = new WindowSpec();
        $spec->addPartition('category');
        $spec->addOrder('price', 'DESC');

        $windowFunc = new WindowFunction('ROW_NUMBER', 'row_num', null, $spec);

        $this->assertEquals('ROW_NUMBER', $windowFunc->getFunction());
        $this->assertEquals('row_num', $windowFunc->getAlias());
        $this->assertNull($windowFunc->getArgs());
    }

    public function testRankFunction(): void
    {
        $spec = new WindowSpec();
        $spec->addOrder('score', 'DESC');

        $windowFunc = new WindowFunction('RANK', 'rank', null, $spec);

        $this->assertEquals('RANK', $windowFunc->getFunction());
        $this->assertEquals('rank', $windowFunc->getAlias());
        $this->assertNull($windowFunc->getArgs());
    }

    public function testDenseRankFunction(): void
    {
        $spec = new WindowSpec();
        $spec->addOrder('score', 'DESC');

        $windowFunc = new WindowFunction('DENSE_RANK', 'dense_rank', null, $spec);

        $this->assertEquals('DENSE_RANK', $windowFunc->getFunction());
        $this->assertEquals('dense_rank', $windowFunc->getAlias());
    }

    public function testSumFunction(): void
    {
        $spec = new WindowSpec();
        $spec->addPartition('account_id');
        $spec->addOrder('transaction_date', 'ASC');

        $windowFunc = new WindowFunction('SUM', 'running_balance', 'amount', $spec);

        $this->assertEquals('SUM', $windowFunc->getFunction());
        $this->assertEquals('running_balance', $windowFunc->getAlias());
        $this->assertEquals('amount', $windowFunc->getArgs());
    }

    public function testAvgFunction(): void
    {
        $spec = new WindowSpec();
        $spec->addPartition('department');

        $windowFunc = new WindowFunction('AVG', 'avg_salary', 'salary', $spec);

        $this->assertEquals('AVG', $windowFunc->getFunction());
        $this->assertEquals('avg_salary', $windowFunc->getAlias());
        $this->assertEquals('salary', $windowFunc->getArgs());
    }

    public function testCountFunction(): void
    {
        $spec = new WindowSpec();
        $spec->addPartition('category');

        $windowFunc = new WindowFunction('COUNT', 'count', '*', $spec);

        $this->assertEquals('COUNT', $windowFunc->getFunction());
        $this->assertEquals('count', $windowFunc->getAlias());
        $this->assertEquals('*', $windowFunc->getArgs());
    }

    // ==================== Multiple Instances Tests ====================

    public function testMultipleInstancesAreIndependent(): void
    {
        $spec1 = new WindowSpec();
        $spec1->addPartition('department');

        $spec2 = new WindowSpec();
        $spec2->addPartition('location');

        $func1 = new WindowFunction('ROW_NUMBER', 'row1', null, $spec1);
        $func2 = new WindowFunction('RANK', 'rank1', null, $spec2);

        $this->assertEquals('ROW_NUMBER', $func1->getFunction());
        $this->assertEquals('RANK', $func2->getFunction());
        $this->assertEquals('row1', $func1->getAlias());
        $this->assertEquals('rank1', $func2->getAlias());
        $this->assertNotSame($func1->getSpec(), $func2->getSpec());
    }

    // ==================== Immutability Tests ====================

    public function testWindowFunctionIsImmutable(): void
    {
        $spec = new WindowSpec();
        $spec->addPartition('department');

        $windowFunc = new WindowFunction('ROW_NUMBER', 'row_num', null, $spec);

        // Multiple calls should return the same values
        $this->assertEquals('ROW_NUMBER', $windowFunc->getFunction());
        $this->assertEquals('ROW_NUMBER', $windowFunc->getFunction());

        $this->assertEquals('row_num', $windowFunc->getAlias());
        $this->assertEquals('row_num', $windowFunc->getAlias());

        $this->assertNull($windowFunc->getArgs());
        $this->assertNull($windowFunc->getArgs());
    }

    // ==================== Edge Cases Tests ====================

    public function testEmptySpec(): void
    {
        $spec = new WindowSpec();
        $windowFunc = new WindowFunction('ROW_NUMBER', 'row_num', null, $spec);

        $retrievedSpec = $windowFunc->getSpec();
        $this->assertTrue($retrievedSpec->isEmpty());
    }

    public function testFunctionNameCaseSensitivity(): void
    {
        $spec = new WindowSpec();

        $func1 = new WindowFunction('row_number', 'row_num', null, $spec);
        $func2 = new WindowFunction('ROW_NUMBER', 'row_num2', null, $spec);

        $this->assertEquals('row_number', $func1->getFunction());
        $this->assertEquals('ROW_NUMBER', $func2->getFunction());
    }
}
