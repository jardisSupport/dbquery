<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Command\Update;

use InvalidArgumentException;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\DbUpdate;
use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbUpdateBuilderInterface;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * MySQL UPDATE Tests
 *
 * Tests: UPDATE, SET, WHERE, JOIN, ORDER BY, LIMIT
 */
class DbUpdateMySqlTest extends TestCase
{
    public function testConstructor(): void
    {
        $update = new DbUpdate();
        $this->assertInstanceOf(DbUpdateBuilderInterface::class, $update);
    }

    public function testSimpleUpdateWithSet(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('name', 'John Doe')
            ->where('id')->equals(1)
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `name` = 'John Doe' WHERE id = 1";
        $this->assertEquals($expected, $sql);
    }

    public function testSimpleUpdateWithSetPrepared(): void
    {
        $update = new DbUpdate();
        $result = $update
            ->table('users')
            ->set('name', 'John Doe')
            ->where('id')->equals(1)
            ->sql('mysql', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals("UPDATE `users` SET `name` = ? WHERE id = ?", $result->sql());
        $this->assertEquals(['John Doe', 1], $result->bindings());
    }

    public function testUpdateWithSetMultiple(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->setMultiple([
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'status' => 'active'
            ])
            ->where('id')->equals(5)
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `name` = 'Jane Smith', `email` = 'jane@example.com', `status` = 'active' WHERE id = 5";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithSetMultiplePrepared(): void
    {
        $update = new DbUpdate();
        $result = $update
            ->table('users')
            ->setMultiple([
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'status' => 'active'
            ])
            ->where('id')->equals(5)
            ->sql('mysql', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals("UPDATE `users` SET `name` = ?, `email` = ?, `status` = ? WHERE id = ?", $result->sql());
        $this->assertEquals(['Jane Smith', 'jane@example.com', 'active', 5], $result->bindings());
    }

    public function testUpdateWithMultipleSetCalls(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('age', 30)
            ->where('id')->equals(1)
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `name` = 'John Doe', `email` = 'john@example.com', `age` = 30 WHERE id = 1";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithNullValue(): void
    {
        $update = new DbUpdate();
        $result = $update
            ->table('users')
            ->setMultiple([
                'name' => 'John Doe',
                'email' => null,
                'deleted_at' => null
            ])
            ->where('id')->equals(1)
            ->sql('mysql', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals("UPDATE `users` SET `name` = ?, `email` = ?, `deleted_at` = ? WHERE id = ?", $result->sql());
        $this->assertEquals(['John Doe', null, null, 1], $result->bindings());
    }

    public function testUpdateWithBooleanValues(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('settings')
            ->setMultiple([
                'is_active' => true,
                'is_deleted' => false
            ])
            ->where('id')->equals(1)
            ->sql('mysql', false);

        $expected = "UPDATE `settings` SET `is_active` = 1, `is_deleted` = 0 WHERE id = 1";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithNumericValues(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('products')
            ->setMultiple([
                'price' => 19.99,
                'quantity' => 100,
                'discount' => 0
            ])
            ->where('id')->equals(10)
            ->sql('mysql', false);

        $expected = "UPDATE `products` SET `price` = 19.99, `quantity` = 100, `discount` = 0 WHERE id = 10";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithSubqueryValue(): void
    {
        $subquery = (new DbQuery())
            ->select('MAX(salary)')
            ->from('employees')
            ->where('department_id')->equals(5);

        $update = new DbUpdate();
        $sql = $update
            ->table('departments')
            ->set('max_salary', $subquery)
            ->where('id')->equals(5)
            ->sql('mysql', false);

        $expected = "UPDATE `departments` SET `max_salary` = (SELECT MAX(salary) FROM `employees` WHERE department_id = 5) WHERE id = 5";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithSubqueryValuePrepared(): void
    {
        $subquery = (new DbQuery())
            ->select('MAX(salary)')
            ->from('employees')
            ->where('department_id')->equals(5);

        $update = new DbUpdate();
        $result = $update
            ->table('departments')
            ->set('max_salary', $subquery)
            ->where('id')->equals(5)
            ->sql('mysql', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals("UPDATE `departments` SET `max_salary` = (SELECT MAX(salary) FROM `employees` WHERE department_id = ?) WHERE id = ?", $result->sql());
        $this->assertEquals([5, 5], $result->bindings());
    }

    public function testUpdateWithWhereEquals(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('status', 'active')
            ->where('email')->equals('john@example.com')
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `status` = 'active' WHERE email = 'john@example.com'";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithWhereNotEquals(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('active', 1)
            ->where('status')->notEquals('deleted')
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `active` = 1 WHERE status != 'deleted'";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithWhereLike(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('newsletter', 1)
            ->where('email')->like('%@example.com')
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `newsletter` = 1 WHERE email LIKE '%@example.com'";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithWhereIn(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('status', 'inactive')
            ->where('id')->in([1, 2, 3, 4, 5])
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `status` = 'inactive' WHERE id IN (1, 2, 3, 4, 5)";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithEmptyInCondition(): void
    {
        $update = new DbUpdate();
        $result = $update
            ->table('users')
            ->set('status', 'inactive')
            ->where('id')->in([])
            ->sql('mysql', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals('UPDATE `users` SET `status` = ? WHERE 1=0', $result->sql());
        $this->assertEquals(['inactive'], $result->bindings());
    }

    public function testUpdateWithEmptyNotInCondition(): void
    {
        $update = new DbUpdate();
        $result = $update
            ->table('users')
            ->set('status', 'inactive')
            ->where('id')->notIn([])
            ->sql('mysql', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals('UPDATE `users` SET `status` = ? WHERE 1=1', $result->sql());
        $this->assertEquals(['inactive'], $result->bindings());
    }

    public function testUpdateWithEmptyInAndOtherCondition(): void
    {
        $update = new DbUpdate();
        $result = $update
            ->table('users')
            ->set('status', 'inactive')
            ->where('status')->equals('active')
            ->and('id')->in([])
            ->sql('mysql', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals('UPDATE `users` SET `status` = ? WHERE status = ? AND 1=0', $result->sql());
        $this->assertEquals(['inactive', 'active'], $result->bindings());
    }

    public function testUpdateWithWhereBetween(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('products')
            ->set('discount', 10)
            ->where('price')->between(100, 500)
            ->sql('mysql', false);

        $expected = "UPDATE `products` SET `discount` = 10 WHERE price BETWEEN 100 AND 500";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithWhereIsNull(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('verified', 0)
            ->where('verified_at')->isNull()
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `verified` = 0 WHERE verified_at IS NULL";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithWhereIsNotNull(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('active', 1)
            ->where('email')->isNotNull()
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `active` = 1 WHERE email IS NOT NULL";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithAndCondition(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('status', 'archived')
            ->where('active')->equals(0)
            ->and('created_at')->lower('2020-01-01')
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `status` = 'archived' WHERE active = 0 AND created_at < '2020-01-01'";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithOrCondition(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('notification', 1)
            ->where('role')->equals('admin')
            ->or('role')->equals('moderator')
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `notification` = 1 WHERE role = 'admin' OR role = 'moderator'";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithComplexWhereConditions(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('priority', 'high')
            ->where('status', '(')->equals('active')
            ->and('role')->equals('premium', ')')
            ->or('score', '(')->greater(1000)
            ->and('verified')->equals(1, ')')
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `priority` = 'high' WHERE (status = 'active' AND role = 'premium') OR (score > 1000 AND verified = 1)";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithWhereJsonExtract(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('vip', 1)
            ->whereJson('metadata')->extract('$.country')->equals('US')
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `vip` = 1 WHERE JSON_EXTRACT(`metadata`, '$.country') = 'US'";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithAndJsonCondition(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('featured', 1)
            ->where('status')->equals('active')
            ->andJson('settings')->extract('$.premium')->equals(true)
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `featured` = 1 WHERE status = 'active' AND JSON_EXTRACT(`settings`, '$.premium') = 1";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithOrJsonCondition(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('products')
            ->set('visible', 0)
            ->where('stock')->equals(0)
            ->orJson('attributes')->extract('$.discontinued')->equals(true)
            ->sql('mysql', false);

        $expected = "UPDATE `products` SET `visible` = 0 WHERE stock = 0 OR JSON_EXTRACT(`attributes`, '$.discontinued') = 1";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithExists(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('orders')
            ->where('orders.user_id')->equals(Expression::raw('users.id'))
            ->and('orders.status')->equals('pending');

        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('has_pending_orders', 1)
            ->where()->exists($subquery)
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `has_pending_orders` = 1 WHERE EXISTS (SELECT 1 FROM `orders` WHERE orders.user_id = users.id AND orders.status = 'pending')";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithNotExists(): void
    {
        $subquery = (new DbQuery())
            ->select('1')
            ->from('orders')
            ->where('orders.user_id')->equals(Expression::raw('users.id'));

        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('no_orders', 1)
            ->where()->notExists($subquery)
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `no_orders` = 1 WHERE NOT EXISTS (SELECT 1 FROM `orders` WHERE orders.user_id = users.id)";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithInnerJoin(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users', 'u')
            ->set('u.total_orders', 0)
            ->innerJoin('orders', 'orders.user_id = u.id', 'o')
            ->where('o.status')->equals('cancelled')
            ->sql('mysql', false);

        $expected = "UPDATE `users` `u` INNER JOIN `orders` `o` ON orders.user_id = u.id SET `u.total_orders` = 0 WHERE o.status = 'cancelled'";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithLeftJoin(): void
    {
        $update = new DbUpdate();
        $result = $update
            ->table('users', 'u')
            ->set('u.last_order_date', null)
            ->leftJoin('orders', 'orders.user_id = u.id', 'o')
            ->where('o.id')->isNull()
            ->sql('mysql', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals("UPDATE `users` `u` LEFT JOIN `orders` `o` ON orders.user_id = u.id SET `u.last_order_date` = ? WHERE o.id IS NULL", $result->sql());
        $this->assertEquals([null], $result->bindings());
    }

    public function testUpdateWithMultipleJoins(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('orders', 'o')
            ->set('o.discount', 15)
            ->innerJoin('users', 'users.id = o.user_id', 'u')
            ->leftJoin('discounts', 'discounts.user_id = u.id', 'd')
            ->where('u.vip')->equals(1)
            ->sql('mysql', false);

        $expected = "UPDATE `orders` `o` INNER JOIN `users` `u` ON users.id = o.user_id LEFT JOIN `discounts` `d` ON discounts.user_id = u.id SET `o.discount` = 15 WHERE u.vip = 1";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithOrderBy(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('priority', 1)
            ->where('status')->equals('active')
            ->orderBy('created_at', 'ASC')
            ->limit(10)
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `priority` = 1 WHERE status = 'active' ORDER BY created_at ASC LIMIT 10";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithOrderByDesc(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('posts')
            ->set('featured', 0)
            ->where('views')->lower(100)
            ->orderBy('views', 'DESC')
            ->limit(5)
            ->sql('mysql', false);

        $expected = "UPDATE `posts` SET `featured` = 0 WHERE views < 100 ORDER BY views DESC LIMIT 5";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithMultipleOrderBy(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('status', 'archived')
            ->where('active')->equals(0)
            ->orderBy('last_login', 'ASC')
            ->orderBy('created_at', 'DESC')
            ->limit(100)
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `status` = 'archived' WHERE active = 0 ORDER BY last_login ASC, created_at DESC LIMIT 100";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithLimit(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('logs')
            ->set('processed', 1)
            ->where('processed')->equals(0)
            ->limit(1000)
            ->sql('mysql', false);

        $expected = "UPDATE `logs` SET `processed` = 1 WHERE processed = 0 LIMIT 1000";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithJoinWhereOrderByLimit(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users', 'u')
            ->set('u.bonus', 100)
            ->innerJoin('orders', 'orders.user_id = u.id', 'o')
            ->where('o.total')->greater(1000)
            ->and('u.status')->equals('active')
            ->orderBy('o.total', 'DESC')
            ->limit(50)
            ->sql('mysql', false);

        $expected = "UPDATE `users` `u` INNER JOIN `orders` `o` ON orders.user_id = u.id SET `u.bonus` = 100 WHERE o.total > 1000 AND u.status = 'active' ORDER BY o.total DESC LIMIT 50";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithTableAlias(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users', 'u')
            ->set('u.updated_at', '2024-01-01')
            ->where('u.id')->equals(1)
            ->sql('mysql', false);

        $expected = "UPDATE `users` `u` SET `u.updated_at` = '2024-01-01' WHERE u.id = 1";
        $this->assertEquals($expected, $sql);
    }

    public function testThrowsExceptionWhenTableNotSpecified(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Table name must be specified with table()');

        $update = new DbUpdate();
        $update
            ->set('name', 'John')
            ->sql('mysql');
    }

    public function testThrowsExceptionWhenNoSetClause(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one column must be set with set() or setMultiple()');

        $update = new DbUpdate();
        $update
            ->table('users')
            ->where('id')->equals(1)
            ->sql('mysql');
    }

    public function testThrowsExceptionWithInvalidBrackets(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid brackets in query');

        $update = new DbUpdate();
        $update
            ->table('users')
            ->set('status', 'active')
            ->where('id', '(')->equals(1)
            // Missing closing bracket
            ->sql('mysql');
    }

    public function testFluentInterface(): void
    {
        $update = new DbUpdate();
        $result = $update->table('users');
        $this->assertSame($update, $result);

        $result = $update->set('name', 'John');
        $this->assertSame($update, $result);

        $result = $update->setMultiple(['email' => 'john@example.com']);
        $this->assertSame($update, $result);

        $result = $update->innerJoin('orders', 'orders.user_id = users.id');
        $this->assertSame($update, $result);

        $result = $update->orderBy('id', 'ASC');
        $this->assertSame($update, $result);

        $result = $update->limit(10);
        $this->assertSame($update, $result);
    }

    public function testUpdateWithExpressionInSet(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('updated_at', Expression::raw('NOW()'))
            ->where('id')->equals(1)
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `updated_at` = NOW() WHERE id = 1";
        $this->assertEquals($expected, $sql);
    }

    public function testUpdateWithExpressionInSetPrepared(): void
    {
        $update = new DbUpdate();
        $result = $update
            ->table('users')
            ->set('updated_at', Expression::raw('NOW()'))
            ->set('name', 'John')
            ->where('id')->equals(1)
            ->sql('mysql', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertEquals("UPDATE `users` SET `updated_at` = NOW(), `name` = ? WHERE id = ?", $result->sql());
        $this->assertEquals(['John', 1], $result->bindings());
    }

    public function testAndJsonAsFirstCondition(): void
    {
        $update = new DbUpdate();
        $sql = $update
            ->table('users')
            ->set('vip', 1)
            ->andJson('metadata')->extract('$.country')->equals('US')
            ->sql('mysql', false);

        $expected = "UPDATE `users` SET `vip` = 1 WHERE JSON_EXTRACT(`metadata`, '$.country') = 'US'";
        $this->assertEquals($expected, $sql);
    }

    public function testIgnore(): void
    {
        $sql = (new DbUpdate())
            ->table('users')
            ->set('name', 'John')
            ->where('id')->equals(1)
            ->ignore()
            ->sql('mysql', false);

        $expected = "UPDATE IGNORE `users` SET `name` = 'John' WHERE id = 1";
        $this->assertEquals($expected, $sql);
    }
}
