<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Command\Insert\Method;

use JardisSupport\DbQuery\command\insert\Method\Replace;
use JardisSupport\DbQuery\Data\InsertState;
use JardisSupport\DbQuery\DbInsert;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for Replace
 *
 * Tests the REPLACE modifier for INSERT statements.
 */
class ReplaceTest extends TestCase
{
    public function testInvokeSetsReplaceFlag(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new Replace();

        $this->assertFalse($state->isReplace());

        $method->__invoke($state, $builder);

        $this->assertTrue($state->isReplace());
    }

    public function testInvokeReturnsBuilder(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new Replace();

        $result = $method->__invoke($state, $builder);

        $this->assertSame($builder, $result);
    }

    public function testDoesNotModifyOtherStateProperties(): void
    {
        $state = new InsertState();
        $state->setTable('users');
        $state->setFields(['name', 'email']);

        $builder = new DbInsert();
        $method = new Replace();

        $method->__invoke($state, $builder);

        $this->assertEquals('users', $state->getTable());
        $this->assertEquals(['name', 'email'], $state->getFields());
        $this->assertTrue($state->isReplace());
    }
}
