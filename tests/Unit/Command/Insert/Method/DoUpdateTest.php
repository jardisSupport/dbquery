<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Command\Insert\Method;

use JardisSupport\DbQuery\command\insert\Method\DoUpdate;
use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\DbQuery\Data\InsertState;
use JardisSupport\DbQuery\DbInsert;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for DoUpdate
 *
 * Tests the DO UPDATE action for ON CONFLICT clause.
 */
class DoUpdateTest extends TestCase
{
    // ==================== Basic Functionality Tests ====================

    public function testInvokeSetsDoUpdateFields(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new DoUpdate();

        $fields = ['name' => 'John', 'status' => 'active'];
        $method->__invoke($state, $builder, $fields);

        $this->assertEquals($fields, $state->getDoUpdateFields());
    }

    public function testInvokeReturnsBuilder(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new DoUpdate();

        $result = $method->__invoke($state, $builder, ['name' => 'John']);

        $this->assertSame($builder, $result);
    }

    // ==================== Different Data Types Tests ====================

    public function testInvokeWithStringValue(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new DoUpdate();

        $method->__invoke($state, $builder, ['name' => 'John Doe']);

        $fields = $state->getDoUpdateFields();
        $this->assertEquals('John Doe', $fields['name']);
    }

    public function testInvokeWithIntegerValue(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new DoUpdate();

        $method->__invoke($state, $builder, ['age' => 30]);

        $fields = $state->getDoUpdateFields();
        $this->assertEquals(30, $fields['age']);
    }

    public function testInvokeWithBooleanValue(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new DoUpdate();

        $method->__invoke($state, $builder, ['active' => true]);

        $fields = $state->getDoUpdateFields();
        $this->assertTrue($fields['active']);
    }

    public function testInvokeWithNullValue(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new DoUpdate();

        $method->__invoke($state, $builder, ['deleted_at' => null]);

        $fields = $state->getDoUpdateFields();
        $this->assertNull($fields['deleted_at']);
    }

    public function testInvokeWithExpression(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new DoUpdate();

        $expr = new Expression('NOW()');
        $method->__invoke($state, $builder, ['updated_at' => $expr]);

        $fields = $state->getDoUpdateFields();
        $this->assertInstanceOf(Expression::class, $fields['updated_at']);
    }

    // ==================== Multiple Fields Tests ====================

    public function testInvokeWithMultipleFields(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new DoUpdate();

        $fields = [
            'name' => 'John',
            'email' => 'john@example.com',
            'status' => 'active',
            'updated_at' => new Expression('NOW()'),
        ];

        $method->__invoke($state, $builder, $fields);

        $storedFields = $state->getDoUpdateFields();
        $this->assertCount(4, $storedFields);
        $this->assertEquals('John', $storedFields['name']);
        $this->assertEquals('john@example.com', $storedFields['email']);
        $this->assertEquals('active', $storedFields['status']);
        $this->assertInstanceOf(Expression::class, $storedFields['updated_at']);
    }

    // ==================== Empty Fields Tests ====================

    public function testInvokeWithEmptyArray(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new DoUpdate();

        $method->__invoke($state, $builder, []);

        $this->assertEmpty($state->getDoUpdateFields());
    }

    // ==================== State Modification Tests ====================

    public function testDoesNotModifyOtherStateProperties(): void
    {
        $state = new InsertState();
        $state->setTable('users');
        $state->setFields(['name', 'email']);
        $state->addValueRow(['John', 'john@example.com']);

        $builder = new DbInsert();
        $method = new DoUpdate();

        $method->__invoke($state, $builder, ['name' => 'Updated']);

        $this->assertEquals('users', $state->getTable());
        $this->assertEquals(['name', 'email'], $state->getFields());
        $this->assertCount(1, $state->getValueRows());
        $this->assertNotEmpty($state->getDoUpdateFields());
    }
}
