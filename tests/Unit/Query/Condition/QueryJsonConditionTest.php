<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Condition;

use JardisSupport\DbQuery\Data\QueryConditionCollector;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use JardisSupport\DbQuery\Query\Condition\QueryJsonCondition;
use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for QueryJsonCondition
 *
 * Tests all JSON-specific operations and comparisons.
 */
class QueryJsonConditionTest extends TestCase
{
    private QueryConditionCollector $collector;
    private QueryJsonCondition $jsonCondition;
    private DbQueryBuilderInterface $queryBuilder;

    protected function setUp(): void
    {
        $this->collector = new QueryConditionCollector();
        $this->queryBuilder = new DbQuery();
        $this->jsonCondition = new QueryJsonCondition($this->queryBuilder, $this->collector, new BuilderRegistry());
    }

    // ==================== extract() Tests ====================

    public function testExtractCreatesPlaceholder(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $result = $this->jsonCondition->extract('$.age');

        $this->assertInstanceOf(QueryJsonCondition::class, $result);

        $conditions = $this->collector->whereConditions();
        $this->assertCount(0, $conditions); // Not added yet, needs comparison operator

        $bindings = $this->collector->bindings();
        $this->assertCount(0, $bindings); // Path ist KEIN Binding mehr!
    }

    public function testExtractWithNestedPath(): void
    {
        $this->jsonCondition->initCondition('profile', ' WHERE ');
        $this->jsonCondition->extract('$.address.city')->equals('Berlin');

        $bindings = $this->collector->bindings();
        $this->assertCount(1, $bindings); // Nur der Wert, nicht der Path
        $this->assertEquals('Berlin', $bindings[0]);
    }

    public function testExtractWithArrayIndex(): void
    {
        $this->jsonCondition->initCondition('tags', ' WHERE ');
        $this->jsonCondition->extract('$[0]')->equals('important');

        $bindings = $this->collector->bindings();
        $this->assertCount(1, $bindings); // Nur der Wert
        $this->assertEquals('important', $bindings[0]);
    }

    // ==================== contains() Tests ====================

    public function testContainsWithValueOnly(): void
    {
        $this->jsonCondition->initCondition('tags', ' WHERE ');
        $result = $this->jsonCondition->contains('admin');

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);

        $conditions = $this->collector->whereConditions();
        $this->assertCount(1, $conditions);
        $this->assertStringContainsString('{{JSON_CONTAINS::', $conditions[0]);

        $bindings = $this->collector->bindings();
        $this->assertCount(1, $bindings);
        $this->assertEquals('admin', $bindings[0]);
    }

    public function testContainsWithValueAndPath(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->contains('active', '$.status');

        $conditions = $this->collector->whereConditions();
        $this->assertStringContainsString('{{JSON_CONTAINS::', $conditions[0]);

        $bindings = $this->collector->bindings();
        $this->assertCount(1, $bindings); // Nur der Wert, Path ist direkt eingebettet
        $this->assertEquals('active', $bindings[0]);
    }

    public function testContainsWithCloseBracket(): void
    {
        $this->jsonCondition->initCondition('tags', ' WHERE ');
        $this->jsonCondition->contains('admin', null, ')');

        $conditions = $this->collector->whereConditions();
        $this->assertStringEndsWith(')', $conditions[0]);
    }

    // ==================== notContains() Tests ====================

    public function testNotContainsWithValueOnly(): void
    {
        $this->jsonCondition->initCondition('tags', ' WHERE ');
        $result = $this->jsonCondition->notContains('banned');

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);

        $conditions = $this->collector->whereConditions();
        $this->assertStringContainsString('{{JSON_NOT_CONTAINS::', $conditions[0]);

        $bindings = $this->collector->bindings();
        $this->assertCount(1, $bindings);
        $this->assertEquals('banned', $bindings[0]);
    }

    public function testNotContainsWithValueAndPath(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->notContains('deleted', '$.status');

        $conditions = $this->collector->whereConditions();
        $this->assertStringContainsString('{{JSON_NOT_CONTAINS::', $conditions[0]);

        $bindings = $this->collector->bindings();
        $this->assertCount(1, $bindings); // Nur der Wert
        $this->assertEquals('deleted', $bindings[0]);
    }

    public function testNotContainsWithCloseBracket(): void
    {
        $this->jsonCondition->initCondition('tags', ' WHERE ');
        $this->jsonCondition->notContains('spam', null, '))');

        $conditions = $this->collector->whereConditions();
        $this->assertStringEndsWith('))', $conditions[0]);
    }

    // ==================== length() Tests ====================

    public function testLengthWithoutPath(): void
    {
        $this->jsonCondition->initCondition('tags', ' WHERE ');
        $result = $this->jsonCondition->length();

        $this->assertInstanceOf(QueryJsonCondition::class, $result);

        // Length returns self for chaining, condition not added yet
        $conditions = $this->collector->whereConditions();
        $this->assertEmpty($conditions);
    }

    public function testLengthWithPath(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->length('$.items')->equals(5);

        $bindings = $this->collector->bindings();
        $this->assertCount(1, $bindings); // Nur der Wert
        $this->assertEquals(5, $bindings[0]);
    }

    public function testLengthChainedWithComparison(): void
    {
        $this->jsonCondition->initCondition('tags', ' WHERE ');
        $this->jsonCondition->length()->greater(3);

        $conditions = $this->collector->whereConditions();
        $this->assertCount(1, $conditions);
        $this->assertStringContainsString('{{JSON_LENGTH}}', $conditions[0]);
    }

    // ==================== Comparison Operators after extract() ====================

    public function testExtractFollowedByEquals(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.age')->equals(25);

        $conditions = $this->collector->whereConditions();
        $this->assertCount(1, $conditions);
        $this->assertStringContainsString('{{JSON_EXTRACT::', $conditions[0]);
        $this->assertStringContainsString(' = ?', $conditions[0]);
    }

    public function testExtractFollowedByNotEquals(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.status')->notEquals('deleted');

        $conditions = $this->collector->whereConditions();
        $this->assertStringContainsString('{{JSON_EXTRACT::', $conditions[0]);
        $this->assertStringContainsString(' != ?', $conditions[0]);
    }

    public function testExtractFollowedByGreater(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.price')->greater(100);

        $conditions = $this->collector->whereConditions();
        $this->assertStringContainsString('{{JSON_EXTRACT::', $conditions[0]);
        $this->assertStringContainsString(' > ?', $conditions[0]);
    }

    public function testExtractFollowedByGreaterEquals(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.age')->greaterEquals(18);

        $conditions = $this->collector->whereConditions();
        $this->assertStringContainsString('{{JSON_EXTRACT::', $conditions[0]);
        $this->assertStringContainsString(' >= ?', $conditions[0]);
    }

    public function testExtractFollowedByLower(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.quantity')->lower(10);

        $conditions = $this->collector->whereConditions();
        $this->assertStringContainsString('{{JSON_EXTRACT::', $conditions[0]);
        $this->assertStringContainsString(' < ?', $conditions[0]);
    }

    public function testExtractFollowedByLowerEquals(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.discount')->lowerEquals(50);

        $conditions = $this->collector->whereConditions();
        $this->assertStringContainsString('{{JSON_EXTRACT::', $conditions[0]);
        $this->assertStringContainsString(' <= ?', $conditions[0]);
    }

    // ==================== Comparison Operators after length() ====================

    public function testLengthFollowedByEquals(): void
    {
        $this->jsonCondition->initCondition('tags', ' WHERE ');
        $this->jsonCondition->length()->equals(5);

        $conditions = $this->collector->whereConditions();
        $this->assertStringContainsString('{{JSON_LENGTH}}', $conditions[0]);
        $this->assertStringContainsString(' = ?', $conditions[0]);
    }

    public function testLengthFollowedByGreater(): void
    {
        $this->jsonCondition->initCondition('items', ' WHERE ');
        $this->jsonCondition->length('$.products')->greater(10);

        $conditions = $this->collector->whereConditions();
        $this->assertStringContainsString('{{JSON_LENGTH::', $conditions[0]);
        $this->assertStringContainsString(' > ?', $conditions[0]);
    }

    public function testLengthFollowedByLower(): void
    {
        $this->jsonCondition->initCondition('tags', ' WHERE ');
        $this->jsonCondition->length()->lower(3);

        $conditions = $this->collector->whereConditions();
        $this->assertStringContainsString('{{JSON_LENGTH}}', $conditions[0]);
        $this->assertStringContainsString(' < ?', $conditions[0]);
    }

    // ==================== IN / NOT IN Tests ====================

    public function testExtractFollowedByIn(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.status')->in(['active', 'pending', 'verified']);

        $conditions = $this->collector->whereConditions();
        $this->assertStringContainsString('{{JSON_EXTRACT::', $conditions[0]);
        $this->assertStringContainsString(' IN (?', $conditions[0]);

        $bindings = $this->collector->bindings();
        $this->assertCount(3, $bindings);
        $this->assertEquals('active', $bindings[0]);
        $this->assertEquals('pending', $bindings[1]);
        $this->assertEquals('verified', $bindings[2]);
    }

    public function testExtractFollowedByNotIn(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.role')->notIn(['guest', 'banned']);

        $conditions = $this->collector->whereConditions();
        $this->assertStringContainsString('{{JSON_EXTRACT::', $conditions[0]);
        $this->assertStringContainsString(' NOT IN (?', $conditions[0]);
    }

    public function testInWithEmptyArrayProducesFalseCondition(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.status')->in([]);

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertCount(1, $conditions);
        $this->assertEquals(' WHERE 1=0', $conditions[0]);
        $this->assertEmpty($bindings);
    }

    public function testNotInWithEmptyArrayProducesTrueCondition(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.role')->notIn([]);

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertCount(1, $conditions);
        $this->assertEquals(' WHERE 1=1', $conditions[0]);
        $this->assertEmpty($bindings);
    }

    public function testInWithEmptyArrayWithoutExtract(): void
    {
        $this->jsonCondition->initCondition('tags', ' WHERE ');
        $result = $this->jsonCondition->in([]);

        $conditions = $this->collector->whereConditions();

        $this->assertInstanceOf(DbQueryBuilderInterface::class, $result);
        $this->assertEquals(' WHERE 1=0', $conditions[0]);
    }

    // ==================== LIKE / NOT LIKE Tests ====================

    public function testExtractFollowedByLike(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.name')->like('%John%');

        $conditions = $this->collector->whereConditions();
        $this->assertStringContainsString('{{JSON_EXTRACT::', $conditions[0]);
        $this->assertStringContainsString(' LIKE ?', $conditions[0]);
    }

    public function testExtractFollowedByNotLike(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.email')->notLike('%@spam.com');

        $conditions = $this->collector->whereConditions();
        $this->assertStringContainsString('{{JSON_EXTRACT::', $conditions[0]);
        $this->assertStringContainsString(' NOT LIKE ?', $conditions[0]);
    }

    // ==================== IS NULL / IS NOT NULL Tests ====================

    public function testExtractFollowedByIsNull(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.deleted_at')->isNull();

        $conditions = $this->collector->whereConditions();
        $this->assertStringContainsString('{{JSON_EXTRACT::', $conditions[0]);
        $this->assertStringContainsString(' IS NULL', $conditions[0]);
    }

    public function testExtractFollowedByIsNotNull(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.verified_at')->isNotNull();

        $conditions = $this->collector->whereConditions();
        $this->assertStringContainsString('{{JSON_EXTRACT::', $conditions[0]);
        $this->assertStringContainsString(' IS NOT NULL', $conditions[0]);
    }

    // ==================== Close Bracket Tests ====================

    public function testExtractWithCloseBracket(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE (');
        $this->jsonCondition->extract('$.age')->equals(25, ')');

        $conditions = $this->collector->whereConditions();
        $this->assertStringEndsWith(')', $conditions[0]);
    }

    public function testLengthWithCloseBracket(): void
    {
        $this->jsonCondition->initCondition('tags', ' WHERE (');
        $this->jsonCondition->length()->greater(5, '))');

        $conditions = $this->collector->whereConditions();
        $this->assertStringEndsWith('))', $conditions[0]);
    }

    // ==================== HAVING Tests ====================

    public function testJsonConditionCanBeHavingCondition(): void
    {
        $this->jsonCondition->initCondition('data', ' HAVING ', true);
        $this->jsonCondition->extract('$.count')->greater(10);

        $havingConditions = $this->collector->havingConditions();
        $whereConditions = $this->collector->whereConditions();

        $this->assertCount(1, $havingConditions);
        $this->assertCount(0, $whereConditions);
        $this->assertStringContainsString(' HAVING ', $havingConditions[0]);
    }

    // ==================== Binding Tests ====================

    public function testBindingsAreIndexedWithoutPaths(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.age')->equals(25);

        $this->jsonCondition->initCondition('profile', ' AND ');
        $this->jsonCondition->extract('$.city')->equals('Berlin');

        $bindings = $this->collector->bindings();

        $this->assertCount(2, $bindings); // Nur 2 Values, keine Paths!
        $this->assertEquals(25, $bindings[0]);
        $this->assertEquals('Berlin', $bindings[1]);
    }

    // ==================== Integration Tests ====================

    public function testComplexJsonConditionWithMultipleOperations(): void
    {
        // data.age >= 18 AND tags contains 'verified' AND items.length > 5
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.age')->greaterEquals(18);

        $this->jsonCondition->initCondition('tags', ' AND ');
        $this->jsonCondition->contains('verified');

        $this->jsonCondition->initCondition('items', ' AND ');
        $this->jsonCondition->length('$.products')->greater(5);

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertCount(3, $conditions);
        $this->assertCount(3, $bindings); // Nur Values, keine Paths
        $this->assertEquals(18, $bindings[0]);
        $this->assertEquals('verified', $bindings[1]);
        $this->assertEquals(5, $bindings[2]);
    }

    public function testJsonConditionWithDifferentDataTypes(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.string')->equals('text');

        $this->jsonCondition->initCondition('data', ' AND ');
        $this->jsonCondition->extract('$.number')->equals(123);

        $this->jsonCondition->initCondition('data', ' AND ');
        $this->jsonCondition->extract('$.bool')->equals(true);

        $bindings = $this->collector->bindings();

        $this->assertCount(3, $bindings);
        $this->assertEquals('text', $bindings[0]);
        $this->assertEquals(123, $bindings[1]);
        $this->assertEquals(true, $bindings[2]);
    }

    // ==================== $isExpression Tests ====================

    public function testExtractWithExpressionComparison(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.price')->equals(Expression::raw('base_price'));

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertStringContainsString('{{JSON_EXTRACT::', $conditions[0]);
        $this->assertStringContainsString(' = base_price', $conditions[0]);
        $this->assertCount(0, $bindings);
    }

    public function testExtractWithFunctionExpression(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.created_at')->lower(Expression::raw('NOW()'));

        $conditions = $this->collector->whereConditions();

        $this->assertStringContainsString('{{JSON_EXTRACT::', $conditions[0]);
        $this->assertStringContainsString(' < NOW()', $conditions[0]);
    }

    public function testLengthWithExpressionComparison(): void
    {
        $this->jsonCondition->initCondition('items', ' WHERE ');
        $this->jsonCondition->length()->greater(Expression::raw('max_items'));

        $conditions = $this->collector->whereConditions();

        $this->assertStringContainsString('{{JSON_LENGTH}}', $conditions[0]);
        $this->assertStringContainsString(' > max_items', $conditions[0]);
    }

    public function testExtractMixedLiteralAndExpression(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.price')->greater(Expression::raw('min_price'));

        $this->jsonCondition->initCondition('data', ' AND ');
        $this->jsonCondition->extract('$.status')->equals('active');

        $conditions = $this->collector->whereConditions();
        $bindings = $this->collector->bindings();

        $this->assertStringContainsString(' > min_price', $conditions[0]);
        $this->assertStringContainsString(' = ?', $conditions[1]);
        $this->assertCount(1, $bindings); // Nur 1 literal value (active), keine Paths, kein Expression
        $this->assertEquals('active', $bindings[0]);
    }

    public function testExtractWithComplexExpression(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.total')->equals(Expression::raw('quantity * unit_price'));

        $conditions = $this->collector->whereConditions();

        $this->assertStringContainsString(' = quantity * unit_price', $conditions[0]);
    }

    // ==================== Validation Tests ====================

    public function testExpressionWithSqlCommentThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL line comment (--) not allowed');

        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.value')->equals(Expression::raw('1 OR 1=1-- '));
    }

    public function testExpressionWithBlockCommentThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SQL block comment');

        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.value')->equals(Expression::raw('1 /* test */ OR 1=1'));
    }

    public function testExpressionWithFileOperationThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File operations not allowed');

        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.file')->equals(Expression::raw('LOAD_FILE("/etc/passwd")'));
    }

    public function testExpressionWithMultipleStatementsThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple SQL statements not allowed');

        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.id')->equals(Expression::raw('1; DROP TABLE users'));
    }

    public function testExpressionAllowsLegitimateSQL(): void
    {
        // Should NOT throw exceptions for legitimate SQL
        $this->jsonCondition->initCondition('data', ' WHERE ');
        $this->jsonCondition->extract('$.a')->equals(Expression::raw('column_name'));

        $this->jsonCondition->initCondition('data', ' AND ');
        $this->jsonCondition->extract('$.b')->equals(Expression::raw('table.column'));

        $this->jsonCondition->initCondition('data', ' AND ');
        $this->jsonCondition->extract('$.c')->equals(Expression::raw('NOW()'));

        $this->jsonCondition->initCondition('data', ' AND ');
        $this->jsonCondition->extract('$.d')->greater(Expression::raw('COUNT(*)'));

        $this->jsonCondition->initCondition('items', ' AND ');
        $this->jsonCondition->length()->lower(Expression::raw('max_count'));

        $conditions = $this->collector->whereConditions();
        $this->assertCount(5, $conditions);
    }

    public function testExtractWithExpressionAndCloseBracket(): void
    {
        $this->jsonCondition->initCondition('data', ' WHERE (');
        $this->jsonCondition->extract('$.value')->equals(Expression::raw('threshold'), ')');

        $conditions = $this->collector->whereConditions();

        $this->assertStringContainsString(' = threshold)', $conditions[0]);
        $this->assertStringEndsWith(')', $conditions[0]);
    }
}
