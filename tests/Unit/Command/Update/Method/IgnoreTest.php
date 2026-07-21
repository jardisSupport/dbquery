<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Command\Update\Method;

use JardisSupport\DbQuery\command\update\Method\Ignore;
use JardisSupport\DbQuery\Data\UpdateState;
use JardisSupport\DbQuery\DbUpdate;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for Ignore
 *
 * Tests the IGNORE modifier for UPDATE statements.
 */
class IgnoreTest extends TestCase
{
    public function testInvokeSetsIgnoreFlag(): void
    {
        $state = new UpdateState();
        $builder = new DbUpdate();
        $method = new Ignore();

        $this->assertFalse($state->isIgnore());

        $method->__invoke($state, $builder);

        $this->assertTrue($state->isIgnore());
    }

    public function testInvokeReturnsBuilder(): void
    {
        $state = new UpdateState();
        $builder = new DbUpdate();
        $method = new Ignore();

        $result = $method->__invoke($state, $builder);

        $this->assertSame($builder, $result);
    }

    public function testDoesNotModifyOtherStateProperties(): void
    {
        $state = new UpdateState();
        $state->setTable('users');
        $state->setColumn('name', 'John');

        $builder = new DbUpdate();
        $method = new Ignore();

        $method->__invoke($state, $builder);

        $this->assertEquals('users', $state->getTable());
        $this->assertCount(1, $state->getSetData());
        $this->assertTrue($state->isIgnore());
    }

    public function testMultipleInvocationsKeepFlagTrue(): void
    {
        $state = new UpdateState();
        $builder = new DbUpdate();
        $method = new Ignore();

        $method->__invoke($state, $builder);
        $this->assertTrue($state->isIgnore());

        $method->__invoke($state, $builder);
        $this->assertTrue($state->isIgnore());
    }
}
