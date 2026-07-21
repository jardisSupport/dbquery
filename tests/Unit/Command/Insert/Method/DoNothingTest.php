<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Command\Insert\Method;

use JardisSupport\DbQuery\command\insert\Method\DoNothing;
use JardisSupport\DbQuery\Data\InsertState;
use JardisSupport\DbQuery\DbInsert;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for DoNothing
 *
 * Tests the DO NOTHING action for ON CONFLICT clause.
 */
class DoNothingTest extends TestCase
{
    // ==================== Basic Functionality Tests ====================

    public function testInvokeSetsDoNothingFlag(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new DoNothing();

        $this->assertFalse($state->isDoNothing());

        $method->__invoke($state, $builder);

        $this->assertTrue($state->isDoNothing());
    }

    public function testInvokeReturnsBuilder(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new DoNothing();

        $result = $method->__invoke($state, $builder);

        $this->assertSame($builder, $result);
    }

    // ==================== State Modification Tests ====================

    public function testDoesNotModifyOtherStateProperties(): void
    {
        $state = new InsertState();
        $state->setTable('users');
        $state->setFields(['name', 'email']);
        $state->addValueRow(['John', 'john@example.com']);

        $builder = new DbInsert();
        $method = new DoNothing();

        $method->__invoke($state, $builder);

        $this->assertEquals('users', $state->getTable());
        $this->assertEquals(['name', 'email'], $state->getFields());
        $this->assertCount(1, $state->getValueRows());
        $this->assertTrue($state->isDoNothing());
    }

    // ==================== Multiple Invocations Tests ====================

    public function testMultipleInvocationsKeepFlagTrue(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new DoNothing();

        $method->__invoke($state, $builder);
        $this->assertTrue($state->isDoNothing());

        $method->__invoke($state, $builder);
        $this->assertTrue($state->isDoNothing());
    }
}
