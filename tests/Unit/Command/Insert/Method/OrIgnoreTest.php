<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Command\Insert\Method;

use JardisSupport\DbQuery\command\insert\Method\OrIgnore;
use JardisSupport\DbQuery\Data\InsertState;
use JardisSupport\DbQuery\DbInsert;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for OrIgnore
 *
 * Tests the OR IGNORE modifier for INSERT statements.
 */
class OrIgnoreTest extends TestCase
{
    public function testInvokeSetsOrIgnoreFlag(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new OrIgnore();

        $this->assertFalse($state->isOrIgnore());

        $method->__invoke($state, $builder);

        $this->assertTrue($state->isOrIgnore());
    }

    public function testInvokeReturnsBuilder(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new OrIgnore();

        $result = $method->__invoke($state, $builder);

        $this->assertSame($builder, $result);
    }

    public function testDoesNotModifyOtherStateProperties(): void
    {
        $state = new InsertState();
        $state->setTable('users');
        $state->setFields(['name', 'email']);

        $builder = new DbInsert();
        $method = new OrIgnore();

        $method->__invoke($state, $builder);

        $this->assertEquals('users', $state->getTable());
        $this->assertEquals(['name', 'email'], $state->getFields());
        $this->assertTrue($state->isOrIgnore());
    }
}
