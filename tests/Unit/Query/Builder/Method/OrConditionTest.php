<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Builder\Method;

use JardisSupport\DbQuery\Data\QueryConditionCollector;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use JardisSupport\DbQuery\Query\builder\Method\OrCondition;
use JardisSupport\DbQuery\Query\Condition\QueryCondition;
use JardisSupport\Contract\DbQuery\DbQueryConditionBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for OrCondition
 *
 * Tests the stateless builder for OR clause initialization in both WHERE and HAVING contexts.
 */
class OrConditionTest extends TestCase
{
    private OrCondition $orCondition;
    private QueryConditionCollector $collector;
    private QueryCondition $queryCondition;
    private DbQuery $queryBuilder;

    protected function setUp(): void
    {
        $this->orCondition = new OrCondition();
        $this->collector = new QueryConditionCollector();
        $this->queryBuilder = new DbQuery();
        $this->queryCondition = new QueryCondition($this->queryBuilder, $this->collector, new BuilderRegistry());
    }

    // ==================== WHERE Context Tests ====================

    public function testInitializesOrInWhereContextWhenNoConditionsExist(): void
    {
        $result = ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'status',
            null,
            false
        );

        $this->assertInstanceOf(DbQueryConditionBuilderInterface::class, $result);
        $this->assertSame($this->queryCondition, $result);
    }

    public function testInitializesOrInWhereContextWhenConditionsExist(): void
    {
        // Add a WHERE condition first
        $this->collector->addWhereCondition('name = ?');

        $result = ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'status',
            null,
            false
        );

        $this->assertInstanceOf(DbQueryConditionBuilderInterface::class, $result);
    }

    public function testAddsOrPrefixWhenWhereConditionsExist(): void
    {
        // Add existing WHERE condition
        $this->collector->addWhereCondition('age > 18');

        ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'status',
            null,
            false
        );

        // Should use ' OR ' prefix
        $this->assertCount(1, $this->collector->whereConditions());
    }

    public function testAddsWherePrefixWhenNoWhereConditionsExist(): void
    {
        ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'status',
            null,
            false
        );

        // Should use ' WHERE ' prefix when first condition
        $this->assertCount(0, $this->collector->whereConditions()); // Not added yet, just initialized
    }

    public function testHandlesNullFieldInWhereContext(): void
    {
        $result = ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            null,
            null,
            false
        );

        $this->assertInstanceOf(DbQueryConditionBuilderInterface::class, $result);
    }

    public function testHandlesOpenBracketInWhereContext(): void
    {
        $result = ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'status',
            '(',
            false
        );

        $this->assertInstanceOf(DbQueryConditionBuilderInterface::class, $result);
    }

    public function testHandlesMultipleOpenBracketsInWhereContext(): void
    {
        $result = ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'status',
            '((',
            false
        );

        $this->assertInstanceOf(DbQueryConditionBuilderInterface::class, $result);
    }

    public function testHandlesBothNullFieldAndOpenBracket(): void
    {
        $result = ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            null,
            '(',
            false
        );

        $this->assertInstanceOf(DbQueryConditionBuilderInterface::class, $result);
    }

    // ==================== HAVING Context Tests ====================

    public function testInitializesOrInHavingContextWhenSupported(): void
    {
        // Add a HAVING condition first
        $this->collector->addHavingCondition('COUNT(*) > 5');

        $result = ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'SUM(amount)',
            null,
            true  // supportsHaving = true
        );

        $this->assertInstanceOf(DbQueryConditionBuilderInterface::class, $result);
    }

    public function testUsesHavingContextWhenHavingConditionsExist(): void
    {
        // Add existing HAVING condition
        $this->collector->addHavingCondition('COUNT(*) > 5');

        ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'SUM(amount)',
            null,
            true
        );

        // Should stay in HAVING context
        $this->assertCount(1, $this->collector->havingConditions());
    }

    public function testFallbackToWhereContextWhenNoHavingConditions(): void
    {
        // No HAVING conditions, even though supportsHaving=true
        $result = ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'status',
            null,
            true
        );

        // Should fall back to WHERE context
        $this->assertInstanceOf(DbQueryConditionBuilderInterface::class, $result);
    }

    public function testHandlesOpenBracketInHavingContext(): void
    {
        $this->collector->addHavingCondition('COUNT(*) > 5');

        $result = ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'SUM(amount)',
            '(',
            true
        );

        $this->assertInstanceOf(DbQueryConditionBuilderInterface::class, $result);
    }

    public function testHandlesMultipleOpenBracketsInHavingContext(): void
    {
        $this->collector->addHavingCondition('COUNT(*) > 5');

        $result = ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'SUM(amount)',
            '((',
            true
        );

        $this->assertInstanceOf(DbQueryConditionBuilderInterface::class, $result);
    }

    public function testHandlesNullFieldInHavingContext(): void
    {
        $this->collector->addHavingCondition('COUNT(*) > 5');

        $result = ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            null,
            null,
            true
        );

        $this->assertInstanceOf(DbQueryConditionBuilderInterface::class, $result);
    }

    // ==================== supportsHaving Parameter Tests ====================

    public function testSupportsHavingFalseNeverUsesHavingContext(): void
    {
        // Even with HAVING conditions, should not use HAVING context
        $this->collector->addHavingCondition('COUNT(*) > 5');

        ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'status',
            null,
            false  // supportsHaving = false (DbUpdate, DbDelete)
        );

        // Should use WHERE context
        $this->assertCount(1, $this->collector->havingConditions());
    }

    public function testSupportsHavingTrueCanUseHavingContext(): void
    {
        $this->collector->addHavingCondition('COUNT(*) > 5');

        ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'SUM(amount)',
            null,
            true  // supportsHaving = true (DbQuery only)
        );

        // Should use HAVING context
        $this->assertCount(1, $this->collector->havingConditions());
    }

    // ==================== Complex Scenario Tests ====================

    public function testSequenceOfWhereOrConditions(): void
    {
        // First OR (becomes WHERE)
        ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'status',
            null,
            false
        );

        // Add condition manually to simulate complete flow
        $this->collector->addWhereCondition('status = ?');

        // Second OR (becomes OR)
        ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'type',
            null,
            false
        );

        $this->assertCount(1, $this->collector->whereConditions());
    }

    public function testSequenceOfHavingOrConditions(): void
    {
        // First HAVING condition
        $this->collector->addHavingCondition('COUNT(*) > 5');

        // First OR in HAVING
        ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'SUM(amount)',
            null,
            true
        );

        $this->assertCount(1, $this->collector->havingConditions());
    }

    public function testMixedWhereAndHavingWithOrConditions(): void
    {
        // WHERE condition first
        $this->collector->addWhereCondition('status = ?');

        // OR in WHERE
        ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'type',
            null,
            true
        );

        // Now add HAVING
        $this->collector->addHavingCondition('COUNT(*) > 5');

        // OR in HAVING
        ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'SUM(amount)',
            null,
            true
        );

        $this->assertCount(1, $this->collector->whereConditions());
        $this->assertCount(1, $this->collector->havingConditions());
    }

    // ==================== Return Value Tests ====================

    public function testAlwaysReturnsQueryConditionInstance(): void
    {
        $result = ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'status',
            null,
            false
        );

        $this->assertSame($this->queryCondition, $result);
    }

    public function testReturnsDbQueryConditionBuilderInterface(): void
    {
        $result = ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'status',
            null,
            false
        );

        $this->assertInstanceOf(DbQueryConditionBuilderInterface::class, $result);
    }

    // ==================== Stateless Reusability Tests ====================

    public function testIsStatelessAndReusable(): void
    {
        $collector1 = new QueryConditionCollector();
        $condition1 = new QueryCondition($this->queryBuilder, $collector1, new BuilderRegistry());

        $collector2 = new QueryConditionCollector();
        $condition2 = new QueryCondition($this->queryBuilder, $collector2, new BuilderRegistry());

        // Use the same OrCondition instance for different contexts
        $result1 = ($this->orCondition)(
            $collector1,
            $condition1,
            'field1',
            null,
            false
        );

        $result2 = ($this->orCondition)(
            $collector2,
            $condition2,
            'field2',
            null,
            false
        );

        $this->assertNotSame($result1, $result2);
        $this->assertInstanceOf(DbQueryConditionBuilderInterface::class, $result1);
        $this->assertInstanceOf(DbQueryConditionBuilderInterface::class, $result2);
    }

    public function testCanBeCalledMultipleTimesWithSameCollector(): void
    {
        ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'field1',
            null,
            false
        );

        $this->collector->addWhereCondition('field1 = ?');

        ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'field2',
            null,
            false
        );

        $this->collector->addWhereCondition('field2 = ?');

        ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'field3',
            null,
            false
        );

        $this->assertCount(2, $this->collector->whereConditions());
    }

    // ==================== Edge Cases ====================

    public function testEmptyFieldNameIsHandled(): void
    {
        $result = ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            '',
            null,
            false
        );

        $this->assertInstanceOf(DbQueryConditionBuilderInterface::class, $result);
    }

    public function testFieldNameWithWhitespace(): void
    {
        $result = ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'user name',
            null,
            false
        );

        $this->assertInstanceOf(DbQueryConditionBuilderInterface::class, $result);
    }

    public function testFieldNameWithSpecialCharacters(): void
    {
        $result = ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'users.created_at',
            null,
            false
        );

        $this->assertInstanceOf(DbQueryConditionBuilderInterface::class, $result);
    }

    public function testOpenBracketWithWhitespace(): void
    {
        $result = ($this->orCondition)(
            $this->collector,
            $this->queryCondition,
            'status',
            '( ',
            false
        );

        $this->assertInstanceOf(DbQueryConditionBuilderInterface::class, $result);
    }
}
