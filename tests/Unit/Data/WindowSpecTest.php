<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Data;

use JardisSupport\DbQuery\Data\WindowSpec;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for WindowSpec
 *
 * Tests the window specification for SQL window functions.
 */
class WindowSpecTest extends TestCase
{
    // ==================== Constructor Tests ====================

    public function testConstructorCreatesEmptySpec(): void
    {
        $spec = new WindowSpec();

        $this->assertInstanceOf(WindowSpec::class, $spec);
        $this->assertTrue($spec->isEmpty());
    }

    // ==================== addPartition() Tests ====================

    public function testAddPartitionWithSingleField(): void
    {
        $spec = new WindowSpec();
        $spec->addPartition('department');

        $this->assertEquals(['department'], $spec->getPartitionBy());
        $this->assertFalse($spec->isEmpty());
    }

    public function testAddPartitionWithMultipleFields(): void
    {
        $spec = new WindowSpec();
        $spec->addPartition('department');
        $spec->addPartition('location');
        $spec->addPartition('category');

        $this->assertEquals(['department', 'location', 'category'], $spec->getPartitionBy());
    }

    public function testAddPartitionWithQualifiedColumnName(): void
    {
        $spec = new WindowSpec();
        $spec->addPartition('users.department');
        $spec->addPartition('orders.status');

        $this->assertEquals(['users.department', 'orders.status'], $spec->getPartitionBy());
    }

    // ==================== addOrder() Tests ====================

    public function testAddOrderWithSingleField(): void
    {
        $spec = new WindowSpec();
        $spec->addOrder('salary', 'DESC');

        $this->assertEquals(['salary DESC'], $spec->getOrderBy());
        $this->assertFalse($spec->isEmpty());
    }

    public function testAddOrderWithMultipleFields(): void
    {
        $spec = new WindowSpec();
        $spec->addOrder('salary', 'DESC');
        $spec->addOrder('hire_date', 'ASC');
        $spec->addOrder('name', 'ASC');

        $this->assertEquals(['salary DESC', 'hire_date ASC', 'name ASC'], $spec->getOrderBy());
    }

    public function testAddOrderNormalizesDirectionToUppercase(): void
    {
        $spec = new WindowSpec();
        $spec->addOrder('price', 'desc');
        $spec->addOrder('name', 'asc');

        $this->assertEquals(['price DESC', 'name ASC'], $spec->getOrderBy());
    }

    public function testAddOrderWithQualifiedColumnName(): void
    {
        $spec = new WindowSpec();
        $spec->addOrder('users.created_at', 'DESC');
        $spec->addOrder('orders.total', 'ASC');

        $this->assertEquals(['users.created_at DESC', 'orders.total ASC'], $spec->getOrderBy());
    }

    // ==================== setFrame() Tests ====================

    public function testSetFrameWithBasicParameters(): void
    {
        $spec = new WindowSpec();
        $spec->setFrame('ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');

        $this->assertEquals('ROWS', $spec->getFrameType());
        $this->assertEquals('UNBOUNDED PRECEDING', $spec->getFrameStart());
        $this->assertEquals('CURRENT ROW', $spec->getFrameEnd());
        $this->assertFalse($spec->isEmpty());
    }

    public function testSetFrameNormalizesToUppercase(): void
    {
        $spec = new WindowSpec();
        $spec->setFrame('rows', 'unbounded preceding', 'current row');

        $this->assertEquals('ROWS', $spec->getFrameType());
        $this->assertEquals('UNBOUNDED PRECEDING', $spec->getFrameStart());
        $this->assertEquals('CURRENT ROW', $spec->getFrameEnd());
    }

    public function testSetFrameWithRangeType(): void
    {
        $spec = new WindowSpec();
        $spec->setFrame('RANGE', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING');

        $this->assertEquals('RANGE', $spec->getFrameType());
        $this->assertEquals('UNBOUNDED PRECEDING', $spec->getFrameStart());
        $this->assertEquals('UNBOUNDED FOLLOWING', $spec->getFrameEnd());
    }

    public function testSetFrameWithGroupsType(): void
    {
        $spec = new WindowSpec();
        $spec->setFrame('GROUPS', '1 PRECEDING', '1 FOLLOWING');

        $this->assertEquals('GROUPS', $spec->getFrameType());
        $this->assertEquals('1 PRECEDING', $spec->getFrameStart());
        $this->assertEquals('1 FOLLOWING', $spec->getFrameEnd());
    }

    public function testSetFrameWithNumericBoundaries(): void
    {
        $spec = new WindowSpec();
        $spec->setFrame('ROWS', '2 PRECEDING', '2 FOLLOWING');

        $this->assertEquals('ROWS', $spec->getFrameType());
        $this->assertEquals('2 PRECEDING', $spec->getFrameStart());
        $this->assertEquals('2 FOLLOWING', $spec->getFrameEnd());
    }

    // ==================== Getter Tests ====================

    public function testGetPartitionByReturnsEmptyArrayByDefault(): void
    {
        $spec = new WindowSpec();

        $this->assertIsArray($spec->getPartitionBy());
        $this->assertEmpty($spec->getPartitionBy());
    }

    public function testGetOrderByReturnsEmptyArrayByDefault(): void
    {
        $spec = new WindowSpec();

        $this->assertIsArray($spec->getOrderBy());
        $this->assertEmpty($spec->getOrderBy());
    }

    public function testGetFrameTypeReturnsNullByDefault(): void
    {
        $spec = new WindowSpec();

        $this->assertNull($spec->getFrameType());
    }

    public function testGetFrameStartReturnsNullByDefault(): void
    {
        $spec = new WindowSpec();

        $this->assertNull($spec->getFrameStart());
    }

    public function testGetFrameEndReturnsNullByDefault(): void
    {
        $spec = new WindowSpec();

        $this->assertNull($spec->getFrameEnd());
    }

    // ==================== isEmpty() Tests ====================

    public function testIsEmptyReturnsTrueForNewSpec(): void
    {
        $spec = new WindowSpec();

        $this->assertTrue($spec->isEmpty());
    }

    public function testIsEmptyReturnsFalseWithPartition(): void
    {
        $spec = new WindowSpec();
        $spec->addPartition('department');

        $this->assertFalse($spec->isEmpty());
    }

    public function testIsEmptyReturnsFalseWithOrderBy(): void
    {
        $spec = new WindowSpec();
        $spec->addOrder('salary', 'DESC');

        $this->assertFalse($spec->isEmpty());
    }

    public function testIsEmptyReturnsFalseWithFrame(): void
    {
        $spec = new WindowSpec();
        $spec->setFrame('ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');

        $this->assertFalse($spec->isEmpty());
    }

    // ==================== toSql() Tests ====================

    public function testToSqlReturnsEmptyStringForEmptySpec(): void
    {
        $spec = new WindowSpec();

        $this->assertEquals('', $spec->toSql());
    }

    public function testToSqlWithOnlyPartition(): void
    {
        $spec = new WindowSpec();
        $spec->addPartition('department');

        $this->assertEquals('PARTITION BY department', $spec->toSql());
    }

    public function testToSqlWithMultiplePartitions(): void
    {
        $spec = new WindowSpec();
        $spec->addPartition('department');
        $spec->addPartition('location');

        $this->assertEquals('PARTITION BY department, location', $spec->toSql());
    }

    public function testToSqlWithOnlyOrderBy(): void
    {
        $spec = new WindowSpec();
        $spec->addOrder('salary', 'DESC');

        $this->assertEquals('ORDER BY salary DESC', $spec->toSql());
    }

    public function testToSqlWithMultipleOrderBy(): void
    {
        $spec = new WindowSpec();
        $spec->addOrder('salary', 'DESC');
        $spec->addOrder('hire_date', 'ASC');

        $this->assertEquals('ORDER BY salary DESC, hire_date ASC', $spec->toSql());
    }

    public function testToSqlWithOnlyFrame(): void
    {
        $spec = new WindowSpec();
        $spec->setFrame('ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');

        $this->assertEquals('ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW', $spec->toSql());
    }

    public function testToSqlWithPartitionAndOrderBy(): void
    {
        $spec = new WindowSpec();
        $spec->addPartition('department');
        $spec->addOrder('salary', 'DESC');

        $this->assertEquals('PARTITION BY department ORDER BY salary DESC', $spec->toSql());
    }

    public function testToSqlWithPartitionAndFrame(): void
    {
        $spec = new WindowSpec();
        $spec->addPartition('department');
        $spec->setFrame('ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');

        $this->assertEquals('PARTITION BY department ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW', $spec->toSql());
    }

    public function testToSqlWithOrderByAndFrame(): void
    {
        $spec = new WindowSpec();
        $spec->addOrder('salary', 'DESC');
        $spec->setFrame('ROWS', '2 PRECEDING', '2 FOLLOWING');

        $this->assertEquals('ORDER BY salary DESC ROWS BETWEEN 2 PRECEDING AND 2 FOLLOWING', $spec->toSql());
    }

    public function testToSqlWithAllComponents(): void
    {
        $spec = new WindowSpec();
        $spec->addPartition('department');
        $spec->addPartition('location');
        $spec->addOrder('salary', 'DESC');
        $spec->addOrder('hire_date', 'ASC');
        $spec->setFrame('ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');

        $expected = 'PARTITION BY department, location ORDER BY salary DESC, hire_date ASC '
            . 'ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW';

        $this->assertEquals($expected, $spec->toSql());
    }

    // ==================== Complex Frame Specifications Tests ====================

    public function testToSqlWithRangeFrame(): void
    {
        $spec = new WindowSpec();
        $spec->addOrder('date', 'ASC');
        $spec->setFrame('RANGE', 'INTERVAL 1 DAY PRECEDING', 'CURRENT ROW');

        $this->assertEquals('ORDER BY date ASC RANGE BETWEEN INTERVAL 1 DAY PRECEDING AND CURRENT ROW', $spec->toSql());
    }

    public function testToSqlWithGroupsFrame(): void
    {
        $spec = new WindowSpec();
        $spec->addPartition('category');
        $spec->addOrder('price', 'DESC');
        $spec->setFrame('GROUPS', '1 PRECEDING', '1 FOLLOWING');

        $this->assertEquals(
            'PARTITION BY category ORDER BY price DESC GROUPS BETWEEN 1 PRECEDING AND 1 FOLLOWING',
            $spec->toSql()
        );
    }

    public function testToSqlWithUnboundedFollowing(): void
    {
        $spec = new WindowSpec();
        $spec->addOrder('id', 'ASC');
        $spec->setFrame('ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING');

        $this->assertEquals('ORDER BY id ASC ROWS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING', $spec->toSql());
    }

    // ==================== Multiple Instances Tests ====================

    public function testMultipleInstancesAreIndependent(): void
    {
        $spec1 = new WindowSpec();
        $spec1->addPartition('department');
        $spec1->addOrder('salary', 'DESC');

        $spec2 = new WindowSpec();
        $spec2->addPartition('location');
        $spec2->addOrder('hire_date', 'ASC');

        $this->assertNotEquals($spec1->toSql(), $spec2->toSql());
        $this->assertEquals('PARTITION BY department ORDER BY salary DESC', $spec1->toSql());
        $this->assertEquals('PARTITION BY location ORDER BY hire_date ASC', $spec2->toSql());
    }

    // ==================== Mutable State Tests ====================

    public function testAddPartitionModifiesState(): void
    {
        $spec = new WindowSpec();

        $this->assertTrue($spec->isEmpty());
        $this->assertEmpty($spec->getPartitionBy());

        $spec->addPartition('department');

        $this->assertFalse($spec->isEmpty());
        $this->assertEquals(['department'], $spec->getPartitionBy());
    }

    public function testAddOrderModifiesState(): void
    {
        $spec = new WindowSpec();

        $this->assertTrue($spec->isEmpty());
        $this->assertEmpty($spec->getOrderBy());

        $spec->addOrder('salary', 'DESC');

        $this->assertFalse($spec->isEmpty());
        $this->assertEquals(['salary DESC'], $spec->getOrderBy());
    }

    public function testSetFrameModifiesState(): void
    {
        $spec = new WindowSpec();

        $this->assertTrue($spec->isEmpty());
        $this->assertNull($spec->getFrameType());

        $spec->setFrame('ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');

        $this->assertFalse($spec->isEmpty());
        $this->assertEquals('ROWS', $spec->getFrameType());
    }
}
