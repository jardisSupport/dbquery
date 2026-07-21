<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Mysql;

use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use PHPUnit\Framework\TestCase;

/**
 * MySQL WHERE Tests
 *
 * Tests: WHERE conditions, operators, AND, OR, brackets
 */
class DbQueryMySqlWhereTest extends TestCase
{
    public function testWhereEquals(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('id')->equals(123)
            ->sql('mysql', false);

        $this->assertEquals('SELECT * FROM `users` WHERE id = 123', $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `users` WHERE id = ?', $prepared->sql());
        $this->assertEquals([123], $prepared->bindings());
    }

    public function testWhereWithString(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('name')->equals('John')
            ->sql('mysql', false);

        $this->assertEquals("SELECT * FROM `users` WHERE name = 'John'", $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `users` WHERE name = ?', $prepared->sql());
        $this->assertEquals(['John'], $prepared->bindings());
    }

    public function testWhereGreater(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('age')->greater(18)
            ->sql('mysql', false);

        $this->assertEquals('SELECT * FROM `users` WHERE age > 18', $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `users` WHERE age > ?', $prepared->sql());
        $this->assertEquals([18], $prepared->bindings());
    }

    public function testWhereLower(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('age')->lower(65)
            ->sql('mysql', false);

        $this->assertEquals('SELECT * FROM `users` WHERE age < 65', $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `users` WHERE age < ?', $prepared->sql());
        $this->assertEquals([65], $prepared->bindings());
    }

    public function testWhereBetween(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('age')->between(18, 65)
            ->sql('mysql', false);

        $this->assertEquals('SELECT * FROM `users` WHERE age BETWEEN 18 AND 65', $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `users` WHERE age BETWEEN ? AND ?', $prepared->sql());
        $this->assertEquals([18, 65], $prepared->bindings());
    }

    public function testWhereIn(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('status')->in(['active', 'pending'])
            ->sql('mysql', false);

        $this->assertEquals("SELECT * FROM `users` WHERE status IN ('active', 'pending')", $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `users` WHERE status IN (?, ?)', $prepared->sql());
        $this->assertEquals(['active', 'pending'], $prepared->bindings());
    }

    public function testWhereInWithEmptyArray(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('id')->in([])
            ->sql('mysql', false);

        $this->assertEquals('SELECT * FROM `users` WHERE 1=0', $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `users` WHERE 1=0', $prepared->sql());
        $this->assertEmpty($prepared->bindings());
    }

    public function testWhereNotInWithEmptyArray(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('id')->notIn([])
            ->sql('mysql', false);

        $this->assertEquals('SELECT * FROM `users` WHERE 1=1', $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `users` WHERE 1=1', $prepared->sql());
        $this->assertEmpty($prepared->bindings());
    }

    public function testWhereInEmptyArrayWithOtherConditions(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('status')->equals('active')
            ->and('id')->in([])
            ->sql('mysql', false);

        $this->assertEquals("SELECT * FROM `users` WHERE status = 'active' AND 1=0", $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertEquals('SELECT * FROM `users` WHERE status = ? AND 1=0', $prepared->sql());
        $this->assertEquals(['active'], $prepared->bindings());
    }

    public function testWhereInEmptyArrayWithOrCondition(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('status')->equals('active')
            ->or('id')->in([])
            ->sql('mysql', false);

        $this->assertEquals("SELECT * FROM `users` WHERE status = 'active' OR 1=0", $sql);
    }

    public function testWhereInEmptyArrayWithBrackets(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('status')->equals('active')
            ->and('(id')->in([], ')')
            ->sql('mysql', false);

        $this->assertEquals("SELECT * FROM `users` WHERE status = 'active' AND (1=0)", $sql);
    }

    public function testWhereNotInEmptyArrayWithOtherConditions(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('status')->equals('active')
            ->and('id')->notIn([])
            ->sql('mysql', false);

        $this->assertEquals("SELECT * FROM `users` WHERE status = 'active' AND 1=1", $sql);
    }

    public function testWhereLike(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('name')->like('%John%')
            ->sql('mysql', false);

        $this->assertEquals("SELECT * FROM `users` WHERE name LIKE '%John%'", $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `users` WHERE name LIKE ?', $prepared->sql());
        $this->assertEquals(['%John%'], $prepared->bindings());
    }

    public function testWhereIsNull(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('deleted_at')->isNull()
            ->sql('mysql', false);

        $this->assertEquals('SELECT * FROM `users` WHERE deleted_at IS NULL', $sql);
    }

    public function testWhereIsNotNull(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('email')->isNotNull()
            ->sql('mysql', false);

        $this->assertEquals('SELECT * FROM `users` WHERE email IS NOT NULL', $sql);
    }

    public function testMultipleWhereWithAnd(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('age')->greater(18)
            ->and('status')->equals('active')
            ->sql('mysql', false);

        $this->assertEquals("SELECT * FROM `users` WHERE age > 18 AND status = 'active'", $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `users` WHERE age > ? AND status = ?', $prepared->sql());
        $this->assertEquals([18, 'active'], $prepared->bindings());
    }

    public function testWhereWithOr(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('age')->lower(18)
            ->or('age')->greater(65)
            ->sql('mysql', false);

        $this->assertEquals('SELECT * FROM `users` WHERE age < 18 OR age > 65', $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `users` WHERE age < ? OR age > ?', $prepared->sql());
        $this->assertEquals([18, 65], $prepared->bindings());
    }

    public function testWhereWithBrackets(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('(age')->greater(18)
            ->and('age')->lower(65, ')')
            ->sql('mysql', false);

        $this->assertEquals('SELECT * FROM `users` WHERE (age > 18 AND age < 65)', $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `users` WHERE (age > ? AND age < ?)', $prepared->sql());
        $this->assertEquals([18, 65], $prepared->bindings());
    }

    public function testComplexWhereConditions(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('(status')->equals('active')
            ->and('age')->greater(18, ')')
            ->or('role')->equals('admin')
            ->sql('mysql', false);

        $this->assertEquals("SELECT * FROM `users` WHERE (status = 'active' AND age > 18) OR role = 'admin'", $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `users` WHERE (status = ? AND age > ?) OR role = ?', $prepared->sql());
        $this->assertEquals(['active', 18, 'admin'], $prepared->bindings());
    }

    public function testWhereWithSubqueryInNonPreparedMode(): void
    {
        // Create subquery for WHERE IN clause
        $subQuery = (new DbQuery())
            ->select('user_id')
            ->from('orders')
            ->where('status')->equals('completed');

        // Main query with WHERE subquery in non-prepared mode
        $query = (new DbQuery())
            ->select('*')
            ->from('users')
            ->where('id')->in($subQuery);

        $sql = $query->sql('mysql', false);

        $this->assertIsString($sql);
        $this->assertStringContainsString('SELECT * FROM `users`', $sql);
        $this->assertStringContainsString('WHERE id IN (SELECT user_id FROM `orders` WHERE status = \'completed\')', $sql);
    }

    public function testWhereJsonExtract(): void
    {
        $query = (new DbQuery())
            ->select('*')
            ->from('users')
            ->whereJson('metadata')->extract('$.age')->greater(18);

        $sql = $query->sql('mysql', false);

        $this->assertStringContainsString('SELECT * FROM `users`', $sql);
        $this->assertStringContainsString('WHERE JSON_EXTRACT(`metadata`, \'$.age\') > 18', $sql);
    }

    public function testAndJsonExtract(): void
    {
        $query = (new DbQuery())
            ->select('*')
            ->from('users')
            ->where('active')->equals(1)
            ->andJson('settings')->extract('$.theme')->equals('dark');

        $sql = $query->sql('mysql', false);

        $this->assertStringContainsString('WHERE active = 1', $sql);
        $this->assertStringContainsString('AND JSON_EXTRACT(`settings`, \'$.theme\') = \'dark\'', $sql);
    }

    public function testAndJsonAsFirstCondition(): void
    {
        $query = (new DbQuery())
            ->select('*')
            ->from('users')
            ->andJson('metadata')->extract('$.country')->equals('US');

        $sql = $query->sql('mysql', false);

        $this->assertStringContainsString('WHERE JSON_EXTRACT(`metadata`, \'$.country\') = \'US\'', $sql);
        $this->assertStringNotContainsString('AND JSON_EXTRACT', $sql);
    }

    public function testOrJsonExtract(): void
    {
        $query = (new DbQuery())
            ->select('*')
            ->from('users')
            ->where('status')->equals('active')
            ->orJson('preferences')->extract('$.notifications')->equals(true);

        $sql = $query->sql('mysql', false);

        $this->assertStringContainsString('WHERE status = \'active\'', $sql);
        $this->assertStringContainsString('OR JSON_EXTRACT(`preferences`, \'$.notifications\') = 1', $sql);
    }

    public function testHavingJson(): void
    {
        $query = (new DbQuery())
            ->select('user_id, COUNT(*) as total')
            ->from('orders')
            ->groupBy('user_id')
            ->havingJson('metadata')->extract('$.priority')->equals('high');

        $sql = $query->sql('mysql', false);

        $this->assertStringContainsString('GROUP BY user_id', $sql);
        $this->assertStringContainsString('HAVING JSON_EXTRACT(`metadata`, \'$.priority\') = \'high\'', $sql);
    }

    public function testWhereJsonInPreparedMode(): void
    {
        $query = (new DbQuery())
            ->select('*')
            ->from('users')
            ->whereJson('data')->extract('$.age')->greater(21);

        $result = $query->sql('mysql', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertStringContainsString('WHERE JSON_EXTRACT(`data`, \'$.age\') > ?', $result->sql());
        $this->assertSame([21], $result->bindings());
    }

    public function testWhereWithExpressionField(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where(Expression::raw('LOWER(name)'))->equals('john')
            ->sql('mysql', false);

        $this->assertEquals("SELECT * FROM `users` WHERE LOWER(name) = 'john'", $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `users` WHERE LOWER(name) = ?', $prepared->sql());
        $this->assertEquals(['john'], $prepared->bindings());
    }

    public function testAndWithExpressionField(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('status')->equals('active')
            ->and(Expression::raw('YEAR(created_at)'))->equals(2024)
            ->sql('mysql', false);

        $this->assertEquals("SELECT * FROM `users` WHERE status = 'active' AND YEAR(created_at) = 2024", $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `users` WHERE status = ? AND YEAR(created_at) = ?', $prepared->sql());
        $this->assertEquals(['active', 2024], $prepared->bindings());
    }

    public function testOrWithExpressionField(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('users')
            ->where('name')->like('%John%')
            ->or(Expression::raw('LOWER(email)'))->like('%john%')
            ->sql('mysql', false);

        $this->assertEquals("SELECT * FROM `users` WHERE name LIKE '%John%' OR LOWER(email) LIKE '%john%'", $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `users` WHERE name LIKE ? OR LOWER(email) LIKE ?', $prepared->sql());
        $this->assertEquals(['%John%', '%john%'], $prepared->bindings());
    }

    public function testWhereExpressionWithBrackets(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('products')
            ->where(Expression::raw('price * 1.19'), '(')->greater(100, ')')
            ->sql('mysql', false);

        $this->assertEquals('SELECT * FROM `products` WHERE (price * 1.19 > 100)', $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `products` WHERE (price * 1.19 > ?)', $prepared->sql());
        $this->assertEquals([100], $prepared->bindings());
    }

    public function testWhereExpressionArithmetic(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('orders')
            ->where(Expression::raw('price * quantity'))->greater(1000)
            ->sql('mysql', false);

        $this->assertEquals('SELECT * FROM `orders` WHERE price * quantity > 1000', $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `orders` WHERE price * quantity > ?', $prepared->sql());
        $this->assertEquals([1000], $prepared->bindings());
    }

    public function testWhereExpressionDateFunction(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('events')
            ->where(Expression::raw('DATE(created_at)'))->equals('2024-01-01')
            ->sql('mysql', false);

        $this->assertEquals("SELECT * FROM `events` WHERE DATE(created_at) = '2024-01-01'", $sql);

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals('SELECT * FROM `events` WHERE DATE(created_at) = ?', $prepared->sql());
        $this->assertEquals(['2024-01-01'], $prepared->bindings());
    }

    public function testComplexExpressionConditions(): void
    {
        $query = new DbQuery();
        $sql = $query
            ->select('*')
            ->from('products')
            ->where(Expression::raw('stock_level'), '(')->greater(0)
            ->and(Expression::raw('price * 0.8'))->lower(100, ')')
            ->or('featured')->equals(1)
            ->sql('mysql', false);

        $this->assertEquals(
            'SELECT * FROM `products` WHERE (stock_level > 0 AND price * 0.8 < 100) OR featured = 1',
            $sql
        );

        $prepared = $query->sql('mysql', true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertEquals(
            'SELECT * FROM `products` WHERE (stock_level > ? AND price * 0.8 < ?) OR featured = ?',
            $prepared->sql()
        );
        $this->assertEquals([0, 100, 1], $prepared->bindings());
    }
}
