<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Command\Insert\Method;

use JardisSupport\DbQuery\command\insert\Method\OnConflict;
use JardisSupport\DbQuery\Data\InsertState;
use JardisSupport\DbQuery\DbInsert;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for OnConflict
 *
 * Tests the ON CONFLICT clause configuration for INSERT statements.
 */
class OnConflictTest extends TestCase
{
    public function testInvokeSetsOnConflictColumns(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new OnConflict();

        $method->__invoke($state, $builder, 'email');

        $this->assertEquals(['email'], $state->getOnConflictColumns());
    }

    public function testInvokeWithMultipleColumns(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new OnConflict();

        $method->__invoke($state, $builder, 'email', 'username');

        $this->assertEquals(['email', 'username'], $state->getOnConflictColumns());
    }

    public function testInvokeReturnsBuilder(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new OnConflict();

        $result = $method->__invoke($state, $builder, 'email');

        $this->assertSame($builder, $result);
    }

    public function testInvokeWithConstraintName(): void
    {
        $state = new InsertState();
        $builder = new DbInsert();
        $method = new OnConflict();

        $method->__invoke($state, $builder, 'users_email_unique');

        $this->assertEquals(['users_email_unique'], $state->getOnConflictColumns());
    }
}
