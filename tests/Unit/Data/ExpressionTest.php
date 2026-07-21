<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Data;

use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\Contract\DbQuery\ExpressionInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for Expression
 *
 * Tests the raw SQL expression wrapper for unescaped values.
 */
class ExpressionTest extends TestCase
{
    // ==================== Constructor Tests ====================

    public function testConstructorWithSimpleExpression(): void
    {
        $expression = new Expression('column_name');

        $this->assertInstanceOf(Expression::class, $expression);
        $this->assertInstanceOf(ExpressionInterface::class, $expression);
    }

    public function testConstructorWithSqlFunction(): void
    {
        $expression = new Expression('NOW()');

        $this->assertInstanceOf(Expression::class, $expression);
    }

    public function testConstructorWithArithmeticExpression(): void
    {
        $expression = new Expression('price * 1.19');

        $this->assertInstanceOf(Expression::class, $expression);
    }

    public function testConstructorWithComplexExpression(): void
    {
        $expression = new Expression('CASE WHEN status = "active" THEN 1 ELSE 0 END');

        $this->assertInstanceOf(Expression::class, $expression);
    }

    public function testConstructorWithEmptyString(): void
    {
        $expression = new Expression('');

        $this->assertInstanceOf(Expression::class, $expression);
    }

    // ==================== Static Factory Method Tests ====================

    public function testRawCreatesExpressionInstance(): void
    {
        $expression = Expression::raw('column_name');

        $this->assertInstanceOf(Expression::class, $expression);
        $this->assertInstanceOf(ExpressionInterface::class, $expression);
    }

    public function testRawWithSqlFunction(): void
    {
        $expression = Expression::raw('COUNT(*)');

        $this->assertInstanceOf(Expression::class, $expression);
        $this->assertEquals('COUNT(*)', $expression->toSql());
    }

    public function testRawWithColumnReference(): void
    {
        $expression = Expression::raw('users.id');

        $this->assertEquals('users.id', $expression->toSql());
    }

    public function testRawWithArithmeticOperation(): void
    {
        $expression = Expression::raw('quantity * price');

        $this->assertEquals('quantity * price', $expression->toSql());
    }

    // ==================== toSql() Method Tests ====================

    public function testToSqlReturnsOriginalString(): void
    {
        $sql = 'column_name';
        $expression = new Expression($sql);

        $this->assertEquals($sql, $expression->toSql());
    }

    public function testToSqlWithSimpleColumn(): void
    {
        $expression = new Expression('id');

        $this->assertEquals('id', $expression->toSql());
    }

    public function testToSqlWithQualifiedColumn(): void
    {
        $expression = new Expression('users.email');

        $this->assertEquals('users.email', $expression->toSql());
    }

    public function testToSqlWithSqlFunction(): void
    {
        $expression = new Expression('UPPER(name)');

        $this->assertEquals('UPPER(name)', $expression->toSql());
    }

    public function testToSqlWithNestedFunction(): void
    {
        $expression = new Expression('COALESCE(MAX(price), 0)');

        $this->assertEquals('COALESCE(MAX(price), 0)', $expression->toSql());
    }

    public function testToSqlWithArithmeticExpression(): void
    {
        $expression = new Expression('(price * quantity) + tax');

        $this->assertEquals('(price * quantity) + tax', $expression->toSql());
    }

    public function testToSqlWithCaseExpression(): void
    {
        $sql = 'CASE WHEN age >= 18 THEN "adult" ELSE "minor" END';
        $expression = new Expression($sql);

        $this->assertEquals($sql, $expression->toSql());
    }

    public function testToSqlWithSubquery(): void
    {
        $sql = '(SELECT COUNT(*) FROM orders WHERE user_id = users.id)';
        $expression = new Expression($sql);

        $this->assertEquals($sql, $expression->toSql());
    }

    public function testToSqlWithEmptyString(): void
    {
        $expression = new Expression('');

        $this->assertEquals('', $expression->toSql());
    }

    public function testToSqlWithWhitespace(): void
    {
        $expression = new Expression('   ');

        $this->assertEquals('   ', $expression->toSql());
    }

    // ==================== Multiple Instances Tests ====================

    public function testMultipleInstancesAreIndependent(): void
    {
        $expr1 = new Expression('column1');
        $expr2 = new Expression('column2');

        $this->assertNotEquals($expr1->toSql(), $expr2->toSql());
        $this->assertEquals('column1', $expr1->toSql());
        $this->assertEquals('column2', $expr2->toSql());
    }

    public function testRawAndConstructorProduceSameResult(): void
    {
        $sql = 'NOW()';
        $expr1 = new Expression($sql);
        $expr2 = Expression::raw($sql);

        $this->assertEquals($expr1->toSql(), $expr2->toSql());
    }

    // ==================== Special Characters Tests ====================

    public function testToSqlWithSpecialCharacters(): void
    {
        $expression = new Expression('column-name_123');

        $this->assertEquals('column-name_123', $expression->toSql());
    }

    public function testToSqlWithQuotes(): void
    {
        $sql = "name = 'John'";
        $expression = new Expression($sql);

        $this->assertEquals($sql, $expression->toSql());
    }

    public function testToSqlWithDoubleQuotes(): void
    {
        $sql = 'name = "John"';
        $expression = new Expression($sql);

        $this->assertEquals($sql, $expression->toSql());
    }

    public function testToSqlWithBackticks(): void
    {
        $sql = '`user`.`id`';
        $expression = new Expression($sql);

        $this->assertEquals($sql, $expression->toSql());
    }

    // ==================== Common SQL Functions Tests ====================

    public function testToSqlWithNowFunction(): void
    {
        $expression = Expression::raw('NOW()');

        $this->assertEquals('NOW()', $expression->toSql());
    }

    public function testToSqlWithCountFunction(): void
    {
        $expression = Expression::raw('COUNT(*)');

        $this->assertEquals('COUNT(*)', $expression->toSql());
    }

    public function testToSqlWithMaxFunction(): void
    {
        $expression = Expression::raw('MAX(price)');

        $this->assertEquals('MAX(price)', $expression->toSql());
    }

    public function testToSqlWithConcatFunction(): void
    {
        $expression = Expression::raw('CONCAT(first_name, " ", last_name)');

        $this->assertEquals('CONCAT(first_name, " ", last_name)', $expression->toSql());
    }

    // ==================== Immutability Tests ====================

    public function testExpressionIsImmutable(): void
    {
        $sql = 'original_value';
        $expression = new Expression($sql);

        // toSql() should always return the same value
        $this->assertEquals($sql, $expression->toSql());
        $this->assertEquals($sql, $expression->toSql());
        $this->assertEquals($sql, $expression->toSql());
    }

    // ==================== Unicode Tests ====================

    public function testToSqlWithUnicodeCharacters(): void
    {
        $expression = new Expression('name = "José"');

        $this->assertEquals('name = "José"', $expression->toSql());
    }

    public function testToSqlWithMultibyteCharacters(): void
    {
        $expression = new Expression('city = "北京"');

        $this->assertEquals('city = "北京"', $expression->toSql());
    }
}
