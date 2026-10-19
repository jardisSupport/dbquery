<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Command\Insert;

use InvalidArgumentException;
use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\DbQuery\DbInsert;
use PHPUnit\Framework\TestCase;

/**
 * onDuplicateKeyUpdate() renders valid, equivalent SQL in every dialect.
 */
class UpsertDialectTest extends TestCase
{
    private function upsert(): DbInsert
    {
        return (new DbInsert())->into('users')
            ->set(['email' => 'a@x.de', 'name' => 'A'])
            ->onConflict('email')
            ->onDuplicateKeyUpdate('name', 'B');
    }

    public function testMySqlAndMariaDbRenderOnDuplicateKeyUpdate(): void
    {
        foreach (['mysql', 'mariadb'] as $dialect) {
            $prepared = $this->upsert()->sql($dialect);
            $this->assertStringEndsWith('ON DUPLICATE KEY UPDATE `name` = ?', $prepared->sql());
            $this->assertSame(['a@x.de', 'A', 'B'], $prepared->bindings());
        }
    }

    public function testPostgresRendersOnConflictDoUpdate(): void
    {
        $prepared = $this->upsert()->sql('postgres');
        $this->assertStringEndsWith('ON CONFLICT ("email") DO UPDATE SET "name" = ?', $prepared->sql());
        $this->assertSame(['a@x.de', 'A', 'B'], $prepared->bindings());
    }

    public function testSqliteRendersOnConflictDoUpdate(): void
    {
        $prepared = $this->upsert()->sql('sqlite');
        $this->assertStringEndsWith('ON CONFLICT (`email`) DO UPDATE SET `name` = ?', $prepared->sql());
    }

    public function testInlineModeAndExpressionValues(): void
    {
        $sql = (new DbInsert())->into('t')->set(['k' => 1, 'n' => 1])
            ->onConflict('k')
            ->onDuplicateKeyUpdate('n', Expression::raw('NOW()'))
            ->sql('postgres', false);

        $this->assertStringEndsWith('DO UPDATE SET "n" = NOW()', $sql);
    }

    public function testBothEntryPointsMerge(): void
    {
        $sql = (new DbInsert())->into('t')->set(['k' => 1])
            ->onConflict('k')
            ->doUpdate(['a' => 1])
            ->onDuplicateKeyUpdate('b', 2)
            ->sql('postgres', false);

        $this->assertStringEndsWith('DO UPDATE SET "a" = 1, "b" = 2', $sql);
    }

    public function testPostgresAndSqliteWithoutTargetFailLoudly(): void
    {
        foreach (['postgres', 'sqlite'] as $dialect) {
            try {
                (new DbInsert())->into('t')->set(['k' => 1])->onDuplicateKeyUpdate('n', 2)->sql($dialect);
                $this->fail('expected InvalidArgumentException for ' . $dialect);
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('conflict target', $e->getMessage());
            }
        }
    }

    public function testMySqlDoUpdateRendersOnDuplicateKeyUpdateAndMergesWithOnDuplicateKeyUpdate(): void
    {
        foreach (['mysql', 'mariadb'] as $dialect) {
            $sql = (new DbInsert())->into('t')->set(['k' => 1])
                ->onConflict('k')
                ->doUpdate(['a' => 1, 'b' => 1])
                ->onDuplicateKeyUpdate('b', 2)
                ->sql($dialect, false);

            $this->assertStringEndsWith('ON DUPLICATE KEY UPDATE `a` = 1, `b` = 2', $sql);
        }
    }

    public function testMySqlDoNothingRendersNoOpUpsertNotInsertIgnore(): void
    {
        foreach (['mysql', 'mariadb'] as $dialect) {
            $sql = (new DbInsert())->into('t')->set(['k' => 1])->onConflict('k', 'j')->doNothing()
                ->sql($dialect, false);

            $this->assertStringStartsWith('INSERT INTO', $sql);
            $this->assertStringEndsWith('ON DUPLICATE KEY UPDATE `k` = `k`', $sql);
        }
    }

    public function testMySqlDoNothingWithoutTargetFailsLoudly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DbInsert())->into('t')->set(['k' => 1])->doNothing()->sql('mysql');
    }

    public function testNoUpsertNoClause(): void
    {
        foreach (['mysql', 'postgres', 'sqlite'] as $dialect) {
            $sql = (new DbInsert())->into('t')->set(['k' => 1])->sql($dialect, false);
            $this->assertStringNotContainsString('CONFLICT', $sql);
            $this->assertStringNotContainsString('DUPLICATE', $sql);
        }
    }
}
