<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Validator;

use JardisSupport\DbQuery\Query\Validator\QueryBracketValidator;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for QueryBracketValidator
 *
 * Tests the validation of bracket balance in SQL query conditions.
 */
class QueryBracketValidatorTest extends TestCase
{
    private QueryBracketValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new QueryBracketValidator();
    }

    // ==================== Basic Tests ====================

    public function testEmptyArraysAreValid(): void
    {
        $this->assertTrue($this->validator->hasValidBrackets([], []));
    }

    public function testConditionsWithoutBracketsAreValid(): void
    {
        $this->assertTrue($this->validator->hasValidBrackets([' WHERE id = 1'], []));
        $this->assertTrue($this->validator->hasValidBrackets([], [' HAVING COUNT(*) > 5']));
    }

    // ==================== WHERE Tests ====================

    public function testWhereWithBalancedBrackets(): void
    {
        $this->assertTrue($this->validator->hasValidBrackets([' WHERE (id = 1)'], []));
        $this->assertTrue($this->validator->hasValidBrackets([' WHERE (id IN (1, 2, 3))'], []));
    }

    public function testWhereWithUnbalancedBrackets(): void
    {
        $this->assertFalse($this->validator->hasValidBrackets([' WHERE (id = 1'], []));
        $this->assertFalse($this->validator->hasValidBrackets([' WHERE id = 1)'], []));
        $this->assertFalse($this->validator->hasValidBrackets([' WHERE ((id = 1)'], []));
    }

    public function testMultipleWhereConditions(): void
    {
        $this->assertTrue($this->validator->hasValidBrackets([' WHERE (id = 1', ' AND name = "x")'], []));
        $this->assertFalse($this->validator->hasValidBrackets([' WHERE (id = 1', ' AND name = "x"'], []));
    }

    // ==================== HAVING Tests ====================

    public function testHavingWithBalancedBrackets(): void
    {
        $this->assertTrue($this->validator->hasValidBrackets([], [' HAVING (COUNT(*) > 5)']));
        $this->assertTrue($this->validator->hasValidBrackets([], [' HAVING (SUM(amt) > 100)']));
    }

    public function testHavingWithUnbalancedBrackets(): void
    {
        $this->assertFalse($this->validator->hasValidBrackets([], [' HAVING (COUNT(*) > 5']));
        $this->assertFalse($this->validator->hasValidBrackets([], [' HAVING COUNT(*) > 5)']));
    }

    // ==================== EXISTS Tests ====================

    public function testExistsWithNullCloseBracket(): void
    {
        $exists = [['type' => 'EXISTS', 'container' => new \JardisSupport\DbQuery\DbQuery(), 'closeBracket' => null]];
        $this->assertTrue($this->validator->hasValidBrackets($exists, []));
    }

    public function testExistsWithEmptyCloseBracket(): void
    {
        $exists = [['type' => 'EXISTS', 'container' => new \JardisSupport\DbQuery\DbQuery(), 'closeBracket' => '']];
        $this->assertTrue($this->validator->hasValidBrackets($exists, []));
    }

    public function testExistsWithBalancedCloseBracket(): void
    {
        $exists = [['type' => 'EXISTS', 'container' => new \JardisSupport\DbQuery\DbQuery(), 'closeBracket' => '()']];
        $this->assertTrue($this->validator->hasValidBrackets($exists, []));
    }

    public function testExistsWithUnbalancedCloseBracket(): void
    {
        $exists = [['type' => 'EXISTS', 'container' => new \JardisSupport\DbQuery\DbQuery(), 'closeBracket' => ')']];
        $this->assertFalse($this->validator->hasValidBrackets($exists, []));

        $exists = [['type' => 'EXISTS', 'container' => new \JardisSupport\DbQuery\DbQuery(), 'closeBracket' => '(']];
        $this->assertFalse($this->validator->hasValidBrackets($exists, []));
    }

    // ==================== Combined Tests ====================

    public function testWhereOpeningExistsClosing(): void
    {
        $where = [' WHERE (id = 1'];
        $where[] = ['type' => 'EXISTS', 'container' => new \JardisSupport\DbQuery\DbQuery(), 'closeBracket' => ')'];
        $this->assertTrue($this->validator->hasValidBrackets($where, []));
    }

    public function testBothWhereAndExistsBalanced(): void
    {
        $where = [' WHERE (id = 1)'];
        $where[] = ['type' => 'EXISTS', 'container' => new \JardisSupport\DbQuery\DbQuery(), 'closeBracket' => null];
        $this->assertTrue($this->validator->hasValidBrackets($where, []));
    }

    public function testBothWhereAndExistsUnbalanced(): void
    {
        $where = [' WHERE (id = 1)'];
        $where[] = ['type' => 'EXISTS', 'container' => new \JardisSupport\DbQuery\DbQuery(), 'closeBracket' => ')'];
        $this->assertFalse($this->validator->hasValidBrackets($where, []));
    }

    public function testAllThreeTypesBalanced(): void
    {
        $where = [' WHERE ((id = 1'];
        $having = [' HAVING COUNT(*) > 5'];
        $where[] = ['type' => 'EXISTS', 'container' => new \JardisSupport\DbQuery\DbQuery(), 'closeBracket' => '))'];
        $this->assertTrue($this->validator->hasValidBrackets($where, $having));
    }

    public function testAllThreeTypesUnbalanced(): void
    {
        $where = [' WHERE (id = 1)'];
        $having = [' HAVING (COUNT(*) > 5)'];
        $where[] = ['type' => 'EXISTS', 'container' => new \JardisSupport\DbQuery\DbQuery(), 'closeBracket' => ')'];
        $this->assertFalse($this->validator->hasValidBrackets($where, $having));
    }

    // ==================== Edge Cases ====================

    public function testNestedBrackets(): void
    {
        $this->assertTrue($this->validator->hasValidBrackets([' WHERE ((((id = 1))))'], []));
        $this->assertFalse($this->validator->hasValidBrackets([' WHERE ((((id = 1)))'], []));
    }

    public function testMultipleExistsConditions(): void
    {
        $where = [
            ['type' => 'EXISTS', 'container' => new \JardisSupport\DbQuery\DbQuery(), 'closeBracket' => '()'],
            ['type' => 'EXISTS', 'container' => new \JardisSupport\DbQuery\DbQuery(), 'closeBracket' => null],
        ];
        $this->assertTrue($this->validator->hasValidBrackets($where, []));
    }

    public function testManyBalancedConditions(): void
    {
        $where = array_fill(0, 50, ' WHERE (id = 1)');
        $this->assertTrue($this->validator->hasValidBrackets($where, []));
    }
}
