<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Command\Insert\Method;

use JardisSupport\DbQuery\command\insert\Method\OnDuplicateKeyUpdate;
use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\DbQuery\Data\InsertState;
use JardisSupport\DbQuery\DbInsert;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for OnDuplicateKeyUpdate
 *
 * Tests the ON DUPLICATE KEY UPDATE clause for MySQL INSERT statements.
 */
class OnDuplicateKeyUpdateTest extends TestCase
{
    public function testInvokeAddsUpdateField(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new OnDuplicateKeyUpdate();

        $method->__invoke($state, $builder, 'name', 'John');

        $updates = $state->getOnDuplicateKeyUpdate();
        $this->assertCount(1, $updates);
        $this->assertEquals('John', $updates['name']);
    }

    public function testInvokeReturnsBuilder(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new OnDuplicateKeyUpdate();

        $result = $method->__invoke($state, $builder, 'name', 'John');

        $this->assertSame($builder, $result);
    }

    public function testInvokeWithIntegerValue(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new OnDuplicateKeyUpdate();

        $method->__invoke($state, $builder, 'count', 42);

        $updates = $state->getOnDuplicateKeyUpdate();
        $this->assertEquals(42, $updates['count']);
    }

    public function testInvokeWithBooleanValue(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new OnDuplicateKeyUpdate();

        $method->__invoke($state, $builder, 'active', false);

        $updates = $state->getOnDuplicateKeyUpdate();
        $this->assertFalse($updates['active']);
    }

    public function testInvokeWithNullValue(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new OnDuplicateKeyUpdate();

        $method->__invoke($state, $builder, 'deleted_at', null);

        $updates = $state->getOnDuplicateKeyUpdate();
        $this->assertNull($updates['deleted_at']);
    }

    public function testInvokeWithExpression(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new OnDuplicateKeyUpdate();

        $expr = new Expression('VALUES(name)');
        $method->__invoke($state, $builder, 'name', $expr);

        $updates = $state->getOnDuplicateKeyUpdate();
        $this->assertInstanceOf(Expression::class, $updates['name']);
    }

    public function testMultipleInvocationsAddMultipleFields(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new OnDuplicateKeyUpdate();

        $method->__invoke($state, $builder, 'name', 'John');
        $method->__invoke($state, $builder, 'status', 'updated');
        $method->__invoke($state, $builder, 'updated_at', new Expression('NOW()'));

        $updates = $state->getOnDuplicateKeyUpdate();
        $this->assertCount(3, $updates);
        $this->assertEquals('John', $updates['name']);
        $this->assertEquals('updated', $updates['status']);
        $this->assertInstanceOf(Expression::class, $updates['updated_at']);
    }
}
