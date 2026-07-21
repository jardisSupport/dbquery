<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Data;

use JardisSupport\DbQuery\Data\QueryConditionCollector;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for QueryConditionCollector
 *
 * Tests the central collector for query conditions and bindings.
 */
class QueryConditionCollectorTest extends TestCase
{
    private QueryConditionCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new QueryConditionCollector();
    }

    // ==================== Initialization Tests ====================

    public function testInitialStateHasNoConditions(): void
    {
        $this->assertEmpty($this->collector->whereConditions());
        $this->assertEmpty($this->collector->havingConditions());
    }

    public function testInitialStateHasNoBindings(): void
    {
        $this->assertEmpty($this->collector->bindings());
    }

    // ==================== WHERE Condition Tests ====================

    public function testAddWhereConditionAddsCondition(): void
    {
        $this->collector->addWhereCondition(' WHERE id = :param1');

        $conditions = $this->collector->whereConditions();

        $this->assertCount(1, $conditions);
        $this->assertEquals(' WHERE id = :param1', $conditions[0]);
    }

    public function testAddWhereConditionAddsMultipleConditions(): void
    {
        $this->collector->addWhereCondition(' WHERE id = :param1');
        $this->collector->addWhereCondition(' AND name = :param2');
        $this->collector->addWhereCondition(' OR status = :param3');

        $conditions = $this->collector->whereConditions();

        $this->assertCount(3, $conditions);
        $this->assertEquals(' WHERE id = :param1', $conditions[0]);
        $this->assertEquals(' AND name = :param2', $conditions[1]);
        $this->assertEquals(' OR status = :param3', $conditions[2]);
    }

    public function testAddWhereConditionIgnoresEmptyString(): void
    {
        $this->collector->addWhereCondition('');

        $this->assertEmpty($this->collector->whereConditions());
    }

    // ==================== HAVING Condition Tests ====================

    public function testAddHavingConditionAddsCondition(): void
    {
        $this->collector->addHavingCondition(' HAVING COUNT(*) > :param1');

        $havingConditions = $this->collector->havingConditions();

        $this->assertCount(1, $havingConditions);
        $this->assertEquals(' HAVING COUNT(*) > :param1', $havingConditions[0]);
    }

    public function testAddHavingConditionAddsMultipleConditions(): void
    {
        $this->collector->addHavingCondition(' HAVING COUNT(*) > :param1');
        $this->collector->addHavingCondition(' AND SUM(amount) < :param2');

        $havingConditions = $this->collector->havingConditions();

        $this->assertCount(2, $havingConditions);
    }

    public function testAddHavingConditionIgnoresEmptyString(): void
    {
        $this->collector->addHavingCondition('');

        $this->assertEmpty($this->collector->havingConditions());
    }

    public function testWhereAndHavingConditionsAreKeptSeparate(): void
    {
        $this->collector->addWhereCondition(' WHERE id = :param1');
        $this->collector->addHavingCondition(' HAVING COUNT(*) > :param2');
        $this->collector->addWhereCondition(' AND name = :param3');

        $whereConditions = $this->collector->whereConditions();
        $havingConditions = $this->collector->havingConditions();

        $this->assertCount(2, $whereConditions);
        $this->assertCount(1, $havingConditions);
    }

    // ==================== addBinding() Tests ====================

    public function testAddBindingStoresBinding(): void
    {
        $this->collector->addBinding('value1');

        $bindings = $this->collector->bindings();

        $this->assertCount(1, $bindings);
        $this->assertEquals('value1', $bindings[0]);
    }

    public function testAddBindingStoresMultipleBindings(): void
    {
        $this->collector->addBinding('value1');
        $this->collector->addBinding(42);
        $this->collector->addBinding(true);

        $bindings = $this->collector->bindings();

        $this->assertCount(3, $bindings);
        $this->assertEquals('value1', $bindings[0]);
        $this->assertEquals(42, $bindings[1]);
        $this->assertTrue($bindings[2]);
    }

    public function testAddBindingAppendsToArray(): void
    {
        $this->collector->addBinding('first');
        $this->collector->addBinding('second');

        $bindings = $this->collector->bindings();

        $this->assertCount(2, $bindings);
        $this->assertEquals('first', $bindings[0]);
        $this->assertEquals('second', $bindings[1]);
    }

    public function testAddBindingHandlesDifferentTypes(): void
    {
        $this->collector->addBinding('text');
        $this->collector->addBinding(123);
        $this->collector->addBinding(45.67);
        $this->collector->addBinding(false);
        $this->collector->addBinding(null);
        $this->collector->addBinding(['a', 'b']);

        $bindings = $this->collector->bindings();

        $this->assertIsString($bindings[0]);
        $this->assertIsInt($bindings[1]);
        $this->assertIsFloat($bindings[2]);
        $this->assertIsBool($bindings[3]);
        $this->assertNull($bindings[4]);
        $this->assertIsArray($bindings[5]);
    }

    // ==================== EXISTS Condition Tests ====================

    public function testAddExistsConditionAddsCondition(): void
    {
        $subquery = new \JardisSupport\DbQuery\DbQuery();
        $this->collector->addExistsCondition('EXISTS', $subquery, null);

        $whereConditions = $this->collector->whereConditions();

        $this->assertCount(1, $whereConditions);
        $this->assertEquals('EXISTS', $whereConditions[0]['type']);
        $this->assertSame($subquery, $whereConditions[0]['container']);
        $this->assertNull($whereConditions[0]['closeBracket']);
    }

    public function testAddExistsConditionWithCloseBracket(): void
    {
        $subquery = new \JardisSupport\DbQuery\DbQuery();
        $this->collector->addExistsCondition('NOT EXISTS', $subquery, ')');

        $whereConditions = $this->collector->whereConditions();

        $this->assertCount(1, $whereConditions);
        $this->assertEquals('NOT EXISTS', $whereConditions[0]['type']);
        $this->assertEquals(')', $whereConditions[0]['closeBracket']);
    }

    public function testAddExistsConditionAddsMultipleConditions(): void
    {
        $subquery1 = new \JardisSupport\DbQuery\DbQuery();
        $subquery2 = new \JardisSupport\DbQuery\DbQuery();

        $this->collector->addExistsCondition('EXISTS', $subquery1);
        $this->collector->addExistsCondition('NOT EXISTS', $subquery2);

        $whereConditions = $this->collector->whereConditions();

        $this->assertCount(2, $whereConditions);
        $this->assertEquals('EXISTS', $whereConditions[0]['type']);
        $this->assertEquals('NOT EXISTS', $whereConditions[1]['type']);
    }

    public function testExistsConditionsReturnsEmptyArrayInitially(): void
    {
        $this->assertEmpty($this->collector->whereConditions());
        $this->assertIsArray($this->collector->whereConditions());
    }

    // ==================== generateParamName() Tests ====================

    public function testGenerateParamNameReturnsQuestionMark(): void
    {
        $param1 = $this->collector->generateParamName();

        $this->assertEquals('?', $param1);
    }

    public function testGenerateParamNameAlwaysReturnsSame(): void
    {
        $param1 = $this->collector->generateParamName();
        $param2 = $this->collector->generateParamName();
        $param3 = $this->collector->generateParamName();

        $this->assertEquals('?', $param1);
        $this->assertEquals('?', $param2);
        $this->assertEquals('?', $param3);
    }

    public function testGenerateParamNameForPositionalParameters(): void
    {
        $names = [];
        for ($i = 0; $i < 100; $i++) {
            $names[] = $this->collector->generateParamName();
        }

        // Alle sollten '?' sein
        foreach ($names as $name) {
            $this->assertEquals('?', $name);
        }
    }

    // ==================== Getter Tests ====================

    public function testWhereConditionsReturnsOnlyWhereConditions(): void
    {
        $this->collector->addWhereCondition(' WHERE id = :param1');
        $this->collector->addHavingCondition(' HAVING COUNT(*) > :param2');

        $conditions = $this->collector->whereConditions();

        $this->assertCount(1, $conditions);
        $this->assertStringContainsString('WHERE', $conditions[0]);
    }

    public function testHavingConditionsReturnsOnlyHavingConditions(): void
    {
        $this->collector->addWhereCondition(' WHERE id = :param1');
        $this->collector->addHavingCondition(' HAVING COUNT(*) > :param2');

        $conditions = $this->collector->havingConditions();

        $this->assertCount(1, $conditions);
        $this->assertStringContainsString('HAVING', $conditions[0]);
    }

    public function testGettersReturnEmptyArraysWhenNoConditions(): void
    {
        $this->assertEmpty($this->collector->whereConditions());
        $this->assertEmpty($this->collector->havingConditions());
    }

    // ==================== bindings() Getter Tests ====================

    public function testBindingsReturnsAllBindings(): void
    {
        $this->collector->addBinding('value1');
        $this->collector->addBinding('value2');

        $bindings = $this->collector->bindings();

        $this->assertCount(2, $bindings);
        $this->assertEquals('value1', $bindings[0]);
        $this->assertEquals('value2', $bindings[1]);
    }

    public function testBindingsReturnsEmptyArrayWhenNoBindings(): void
    {
        $bindings = $this->collector->bindings();

        $this->assertEmpty($bindings);
        $this->assertIsArray($bindings);
    }

    public function testBindingsAreIndexedArray(): void
    {
        $this->collector->addBinding('first');
        $this->collector->addBinding('second');
        $this->collector->addBinding('third');

        $bindings = $this->collector->bindings();

        // Test that keys are 0, 1, 2
        $this->assertArrayHasKey(0, $bindings);
        $this->assertArrayHasKey(1, $bindings);
        $this->assertArrayHasKey(2, $bindings);
    }

    // ==================== Integration Tests ====================

    public function testFullWorkflowWithAllConditionTypes(): void
    {
        // Add WHERE conditions
        $this->collector->addBinding('John');
        $param1 = $this->collector->generateParamName();
        $this->collector->addWhereCondition(" WHERE name = $param1");

        $this->collector->addBinding(18);
        $param2 = $this->collector->generateParamName();
        $this->collector->addWhereCondition(" AND age > $param2");

        // Add HAVING condition
        $this->collector->addBinding(10);
        $param3 = $this->collector->generateParamName();
        $this->collector->addHavingCondition(" HAVING COUNT(*) > $param3");

        // Add EXISTS condition
        $subquery = new \JardisSupport\DbQuery\DbQuery();
        $this->collector->addExistsCondition('EXISTS', $subquery, ')');

        // Verify
        $whereConditions = $this->collector->whereConditions();
        $havingConditions = $this->collector->havingConditions();
        $bindings = $this->collector->bindings();

        $this->assertCount(3, $whereConditions);
        $this->assertCount(1, $havingConditions);
        $this->assertCount(3, $bindings);

        // Verify indexed bindings
        $this->assertEquals('John', $bindings[0]);
        $this->assertEquals(18, $bindings[1]);
        $this->assertEquals(10, $bindings[2]);
    }
}
