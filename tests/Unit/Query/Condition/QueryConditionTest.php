<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Condition;

use JardisSupport\DbQuery\Data\QueryConditionCollector;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use JardisSupport\DbQuery\Query\Condition\QueryCondition;
use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for QueryCondition
 *
 * Tests all SQL comparison operators and condition building.
 */
class QueryConditionTest extends TestCase
{
    private QueryConditionCollector $collector;
    private QueryCondition $condition;
    private DbQueryBuilderInterface $queryBuilder;

    protected function setUp(): void
    {
        $this->collector = new QueryConditionCollector();
        $this->queryBuilder = new DbQuery();
        $this->condition = new QueryCondition($this->queryBuilder, $this->collector, new BuilderRegistry());
    }

    // ==================== equals() Tests ====================

    public function testEqualsAddsConditionWithValue(): void
    {
        $this->condition->initCondition(' WHERE ', 'id');
        $result = $this->condition->equals(123);

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertCount(1, $conditions);
        $this->assertStringContainsString(' WHERE id', $conditions[0]);
        $this->assertStringContainsString(' = ?', $conditions[0]);
        $this->assertCount(1, $bindings);
        $this->assertEquals(123, $bindings[0]);
    }

    public function testEqualsWithCloseBracket(): void
    {
        $this->condition->initCondition(' WHERE ', 'id');
        $this->condition->equals(123, ')');

        $conditions = $this->collector->whereConditions();

        $this->assertStringEndsWith(')', $conditions[0]);
    }

    public function testEqualsWithExpression(): void
    {
        $this->condition->initCondition(' WHERE ', 'price');
        $this->condition->equals(Expression::raw('max_price'));

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertStringContainsString(' WHERE price', $conditions[0]);
        $this->assertStringContainsString(' = max_price', $conditions[0]);
        $this->assertCount(0, $bindings); // No binding for expression
    }

    public function testEqualsWithExpressionFunction(): void
    {
        $this->condition->initCondition(' WHERE ', 'created_at');
        $this->condition->equals(Expression::raw('NOW()'));

        $conditions = $this->collector->whereConditions();

        $this->assertStringContainsString(' = NOW()', $conditions[0]);
    }

    // ==================== notEquals() Tests ====================

    public function testNotEqualsAddsConditionWithValue(): void
    {
        $this->condition->initCondition(' WHERE ', 'status');
        $result = $this->condition->notEquals('inactive');

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertStringContainsString(' != ?', $conditions[0]);
        $this->assertEquals('inactive', $bindings[0]);
    }

    public function testNotEqualsWithCloseBracket(): void
    {
        $this->condition->initCondition(' WHERE ', 'status');
        $this->condition->notEquals('inactive', ')');

        $conditions = $this->collector->whereConditions();

        $this->assertStringEndsWith(')', $conditions[0]);
    }

    // ==================== greater() Tests ====================

    public function testGreaterAddsConditionWithValue(): void
    {
        $this->condition->initCondition(' WHERE ', 'age');
        $result = $this->condition->greater(18);

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertStringContainsString(' > ?', $conditions[0]);
        $this->assertEquals(18, $bindings[0]);
    }

    public function testGreaterWithExpression(): void
    {
        $this->condition->initCondition(' WHERE ', 'stock');
        $this->condition->greater(Expression::raw('min_stock'));

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertStringContainsString(' > min_stock', $conditions[0]);
        $this->assertCount(0, $bindings);
    }

    // ==================== greaterEquals() Tests ====================

    public function testGreaterEqualsAddsConditionWithValue(): void
    {
        $this->condition->initCondition(' WHERE ', 'price');
        $result = $this->condition->greaterEquals(100.50);

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertStringContainsString(' >= ?', $conditions[0]);
        $this->assertEquals(100.50, $bindings[0]);
    }

    // ==================== lower() Tests ====================

    public function testLowerAddsConditionWithValue(): void
    {
        $this->condition->initCondition(' WHERE ', 'quantity');
        $result = $this->condition->lower(10);

        $conditions = $this->collector->whereConditions();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertStringContainsString(' < ?', $conditions[0]);
    }

    // ==================== lowerEquals() Tests ====================

    public function testLowerEqualsAddsConditionWithValue(): void
    {
        $this->condition->initCondition(' WHERE ', 'discount');
        $result = $this->condition->lowerEquals(50);

        $conditions = $this->collector->whereConditions();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertStringContainsString(' <= ?', $conditions[0]);
    }

    // ==================== between() Tests ====================

    public function testBetweenAddsConditionWithTwoValues(): void
    {
        $this->condition->initCondition(' WHERE ', 'age');
        $result = $this->condition->between(18, 65);

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertStringContainsString(' BETWEEN ?', $conditions[0]);
        $this->assertStringContainsString(' AND ?', $conditions[0]);
        $this->assertCount(2, $bindings);
        $this->assertEquals(18, $bindings[0]);
        $this->assertEquals(65, $bindings[1]);
    }

    public function testBetweenWithExpressions(): void
    {
        $this->condition->initCondition(' WHERE ', 'value');
        $this->condition->between(Expression::raw('min_value'), Expression::raw('max_value'));

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertStringContainsString(' BETWEEN min_value', $conditions[0]);
        $this->assertStringContainsString(' AND max_value', $conditions[0]);
        $this->assertCount(0, $bindings);
    }

    public function testBetweenWithCloseBracket(): void
    {
        $this->condition->initCondition(' WHERE ', 'age');
        $this->condition->between(18, 65, ')');

        $conditions = $this->collector->whereConditions();

        $this->assertStringEndsWith(')', $conditions[0]);
    }

    // ==================== notBetween() Tests ====================

    public function testNotBetweenAddsConditionWithTwoValues(): void
    {
        $this->condition->initCondition(' WHERE ', 'age');
        $result = $this->condition->notBetween(18, 65);

        $conditions = $this->collector->whereConditions();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertStringContainsString(' NOT BETWEEN ?', $conditions[0]);
        $this->assertStringContainsString(' AND ?', $conditions[0]);
    }

    // ==================== in() Tests ====================

    public function testInAddsConditionWithArray(): void
    {
        $this->condition->initCondition(' WHERE ', 'status');
        $result = $this->condition->in(['active', 'pending', 'verified']);

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertStringContainsString(' IN (?', $conditions[0]);
        $this->assertCount(3, $bindings);
        $this->assertEquals('active', $bindings[0]);
        $this->assertEquals('pending', $bindings[1]);
        $this->assertEquals('verified', $bindings[2]);
    }

    public function testInWithCloseBracket(): void
    {
        $this->condition->initCondition(' WHERE ', 'id');
        $this->condition->in([1, 2, 3], ')');

        $conditions = $this->collector->whereConditions();

        $this->assertStringEndsWith(')', $conditions[0]);
    }

    public function testInWithEmptyArrayProducesFalseCondition(): void
    {
        $this->condition->initCondition(' WHERE ', 'id');
        $result = $this->condition->in([]);

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertCount(1, $conditions);
        $this->assertEquals(' WHERE 1=0', $conditions[0]);
        $this->assertEmpty($bindings);
    }

    public function testInWithEmptyArrayAndCloseBracket(): void
    {
        $this->condition->initCondition(' WHERE (', 'id');
        $result = $this->condition->in([], ')');

        $conditions = $this->collector->whereConditions();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertEquals(' WHERE (1=0)', $conditions[0]);
    }

    public function testInWithEmptyArrayInAndContext(): void
    {
        $this->condition->initCondition(' WHERE ', 'status');
        $this->condition->equals('active');

        $this->condition->initCondition(' AND ', 'id');
        $this->condition->in([]);

        $conditions = $this->collector->whereConditions();

        $this->assertCount(2, $conditions);
        $this->assertEquals(' AND 1=0', $conditions[1]);
    }

    public function testInWithEmptyArrayInOrContext(): void
    {
        $this->condition->initCondition(' WHERE ', 'status');
        $this->condition->equals('active');

        $this->condition->initCondition(' OR ', 'id');
        $this->condition->in([]);

        $conditions = $this->collector->whereConditions();

        $this->assertCount(2, $conditions);
        $this->assertEquals(' OR 1=0', $conditions[1]);
    }

    // ==================== notIn() Tests ====================

    public function testNotInAddsConditionWithArray(): void
    {
        $this->condition->initCondition(' WHERE ', 'status');
        $result = $this->condition->notIn(['deleted', 'banned']);

        $conditions = $this->collector->whereConditions();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertStringContainsString(' NOT IN (?', $conditions[0]);
    }

    public function testNotInWithEmptyArrayProducesTrueCondition(): void
    {
        $this->condition->initCondition(' WHERE ', 'id');
        $result = $this->condition->notIn([]);

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertCount(1, $conditions);
        $this->assertEquals(' WHERE 1=1', $conditions[0]);
        $this->assertEmpty($bindings);
    }

    public function testNotInWithEmptyArrayAndCloseBracket(): void
    {
        $this->condition->initCondition(' WHERE (', 'id');
        $result = $this->condition->notIn([], ')');

        $conditions = $this->collector->whereConditions();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertEquals(' WHERE (1=1)', $conditions[0]);
    }

    // ==================== like() Tests ====================

    public function testLikeAddsConditionWithPattern(): void
    {
        $this->condition->initCondition(' WHERE ', 'name');
        $result = $this->condition->like('%John%');

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertStringContainsString(' LIKE ?', $conditions[0]);
        $this->assertEquals('%John%', $bindings[0]);
    }

    // ==================== notLike() Tests ====================

    public function testNotLikeAddsConditionWithPattern(): void
    {
        $this->condition->initCondition(' WHERE ', 'email');
        $result = $this->condition->notLike('%@spam.com');

        $conditions = $this->collector->whereConditions();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertStringContainsString(' NOT LIKE ?', $conditions[0]);
    }

    // ==================== isNull() Tests ====================

    public function testIsNullAddsCondition(): void
    {
        $this->condition->initCondition(' WHERE ', 'deleted_at');
        $result = $this->condition->isNull();

            $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertStringContainsString(' IS NULL', $conditions[0]);
        $this->assertEmpty($bindings); // No bindings for NULL check
    }

    public function testIsNullWithCloseBracket(): void
    {
        $this->condition->initCondition(' WHERE ', 'deleted_at');
        $this->condition->isNull(')');

        $conditions = $this->collector->whereConditions();

        $this->assertStringEndsWith(')', $conditions[0]);
    }

    // ==================== isNotNull() Tests ====================

    public function testIsNotNullAddsCondition(): void
    {
        $this->condition->initCondition(' WHERE ', 'email');
        $result = $this->condition->isNotNull();

        $conditions = $this->collector->whereConditions();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertStringContainsString(' IS NOT NULL', $conditions[0]);
    }

    // ==================== exists() Tests ====================

    public function testExistsAddsConditionWithSubquery(): void
    {
        $this->condition->initCondition(' WHERE ', '');
        $subquery = $this->queryBuilder;

        $result = $this->condition->exists($subquery);

        $conditions = $this->collector->whereConditions();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertStringContainsString(' EXISTS ', $conditions[0]);
    }

    // ==================== notExists() Tests ====================

    public function testNotExistsAddsConditionWithSubquery(): void
    {
        $this->condition->initCondition(' WHERE ', '');
        $subquery = $this->queryBuilder;

        $result = $this->condition->notExists($subquery);

        $conditions = $this->collector->whereConditions();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertStringContainsString(' NOT EXISTS ', $conditions[0]);
    }

    // ==================== Binding Tests ====================

    public function testMultipleConditionsCreateIndexedBindings(): void
    {
        $this->condition->initCondition(' WHERE ', 'id');
        $this->condition->equals(1);

        $this->condition->initCondition(' AND ', 'name');
        $this->condition->equals('John');

        $bindings = $this->collector->bindings();

        $this->assertCount(2, $bindings);
        $this->assertEquals(1, $bindings[0]);
        $this->assertEquals('John', $bindings[1]);
    }

    public function testBindingsAreIndexedArray(): void
    {
        $this->condition->initCondition(' WHERE ', 'id');
        $this->condition->equals(123);

        $bindings = $this->collector->bindings();
        $keys = array_keys($bindings);

        // Keys should be 0, 1, 2, ...
        $this->assertEquals([0], $keys);
        $this->assertEquals(123, $bindings[0]);
    }

    // ==================== HAVING Condition Tests ====================

    public function testConditionCanBeHavingCondition(): void
    {
        $this->condition->initCondition(' HAVING ', 'COUNT(*)', true);
        $this->condition->greater(10);

        $havingConditions = $this->collector->havingConditions();
        $whereConditions = $this->collector->whereConditions();

        $this->assertCount(1, $havingConditions);
        $this->assertCount(0, $whereConditions);
        $this->assertStringContainsString(' HAVING COUNT(*)', $havingConditions[0]);
    }

    // ==================== Integration Tests ====================

    public function testConditionWithDifferentDataTypes(): void
    {
        $this->condition->initCondition(' WHERE ', 'string_col');
        $this->condition->equals('text');

        $this->condition->initCondition(' AND ', 'int_col');
        $this->condition->equals(123);

        $this->condition->initCondition(' AND ', 'float_col');
        $this->condition->equals(45.67);

        $this->condition->initCondition(' AND ', 'bool_col');
        $this->condition->equals(true);

        $bindings = $this->collector->bindings();

        $this->assertCount(4, $bindings);
        $this->assertEquals('text', $bindings[0]);
        $this->assertEquals(123, $bindings[1]);
        $this->assertEquals(45.67, $bindings[2]);
        $this->assertEquals(true, $bindings[3]);
    }

    // ==================== $isExpression Tests ====================

    public function testExpressionWithColumnName(): void
    {
        $this->condition->initCondition(' WHERE ', 'price');
        $this->condition->equals(Expression::raw('list_price'));

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertStringContainsString(' = list_price', $conditions[0]);
        $this->assertEmpty($bindings);
    }

    public function testExpressionWithFunction(): void
    {
        $this->condition->initCondition(' WHERE ', 'created_at');
        $this->condition->lower(Expression::raw('NOW()'));

        $conditions = $this->collector->whereConditions();

        $this->assertStringContainsString(' < NOW()', $conditions[0]);
    }

    public function testExpressionWithComplexFunction(): void
    {
        $this->condition->initCondition(' WHERE ', 'total');
        $this->condition->equals(Expression::raw('SUM(quantity * price)'));

        $conditions = $this->collector->whereConditions();

        $this->assertStringContainsString(' = SUM(quantity * price)', $conditions[0]);
    }

    public function testExpressionWithColumnInBetween(): void
    {
        $this->condition->initCondition(' WHERE ', 'value');
        $this->condition->between(Expression::raw('min_threshold'), Expression::raw('max_threshold'));

        $conditions = $this->collector->whereConditions();

        $this->assertStringContainsString(' BETWEEN min_threshold AND max_threshold', $conditions[0]);
    }

    public function testExpressionMixedWithLiteralValues(): void
    {
        $this->condition->initCondition(' WHERE ', 'price');
        $this->condition->greater(Expression::raw('base_price'));

        $this->condition->initCondition(' AND ', 'quantity');
        $this->condition->lower(100);

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertStringContainsString(' > base_price', $conditions[0]);
        $this->assertStringContainsString(' < ', $conditions[1]);
        $this->assertCount(1, $bindings); // Only quantity is bound
    }

    // ==================== Validation Tests ====================

    public function testExpressionWithSqlCommentThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL line comment (--) not allowed');

        $this->condition->initCondition(' WHERE ', 'id');
        $this->condition->equals(Expression::raw('1 OR 1=1-- '));
    }

    public function testExpressionWithBlockCommentThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL block comment');

        $this->condition->initCondition(' WHERE ', 'id');
        $this->condition->equals(Expression::raw('1 /* comment */ OR 1=1'));
    }

    public function testExpressionWithFileOperationThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File operations not allowed');

        $this->condition->initCondition(' WHERE ', 'data');
        $this->condition->equals(Expression::raw('LOAD_FILE("/etc/passwd")'));
    }

    public function testExpressionWithMultipleStatementsThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple SQL statements not allowed');

        $this->condition->initCondition(' WHERE ', 'id');
        $this->condition->equals(Expression::raw('1; DROP TABLE users'));
    }

    public function testExpressionAllowsLegitimateSQL(): void
    {
        // Should NOT throw exceptions for legitimate SQL
        $this->condition->initCondition(' WHERE ', 'a');
        $this->condition->equals(Expression::raw('column_name'));

        $this->condition->initCondition(' AND ', 'b');
        $this->condition->equals(Expression::raw('table.column'));

        $this->condition->initCondition(' AND ', 'c');
        $this->condition->equals(Expression::raw('NOW()'));

        $this->condition->initCondition(' AND ', 'd');
        $this->condition->equals(Expression::raw('COUNT(*)'));

        $this->condition->initCondition(' AND ', 'e');
        $this->condition->equals(Expression::raw('CASE WHEN x > 0 THEN 1 ELSE 0 END'));

        $conditions = $this->collector->whereConditions();
        $this->assertCount(5, $conditions);
    }
}
