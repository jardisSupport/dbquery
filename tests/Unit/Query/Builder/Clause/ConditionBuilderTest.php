<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Builder\Clause;

use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\DbQuery\Query\Builder\Clause\ConditionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * The bindings of an EXISTS subquery are inserted where the EXISTS stands in the SQL.
 */
class ConditionBuilderTest extends TestCase
{
    private function existsCondition(string $sql, array $bindings, string $type = 'EXISTS'): array
    {
        $prepared = $this->createMock(DbPreparedQueryInterface::class);
        $prepared->method('sql')->willReturn($sql);
        $prepared->method('bindings')->willReturn($bindings);

        $container = $this->createMock(DbQueryBuilderInterface::class);
        $container->method('sql')->willReturn($prepared);

        return ['type' => $type, 'container' => $container, 'closeBracket' => null];
    }

    public function testExistsBindingsAreSplicedAtTheCursorAndMoveIt(): void
    {
        // bindings: [structural, own, later] - own belongs to the string condition before the EXISTS
        $bindings = ['structural', 'own', 'later'];
        $cursor = 1;
        $seen = [];

        $sql = (new ConditionBuilder())(
            [' WHERE a = ?', $this->existsCondition('SELECT 1 WHERE x = ? AND y = ?', ['x', 'y']), ' AND b = ?'],
            'mysql',
            true,
            fn(string $c) => $c,
            function (string $c) use (&$cursor, &$seen) {
                $seen[] = $cursor;
                $cursor++;   // the condition's single binding is consumed

                return $c;
            },
            fn(string $c) => $c,
            $bindings,
            $cursor
        );

        $this->assertSame(' WHERE a = ?  AND EXISTS (SELECT 1 WHERE x = ? AND y = ?)  AND b = ?', $sql);
        $this->assertSame(['structural', 'own', 'x', 'y', 'later'], $bindings);
        $this->assertSame([1, 4], $seen);
        $this->assertSame(5, $cursor);
    }

    public function testExistsBindingsAreAppendedWithoutACursorAsBefore(): void
    {
        $bindings = ['one'];

        $sql = (new ConditionBuilder())(
            [$this->existsCondition('SELECT 1 WHERE x = ?', ['x'], 'NOT EXISTS')],
            'mysql',
            true,
            fn(string $c) => $c,
            fn(string $c) => $c,
            fn(string $c) => $c,
            $bindings
        );

        $this->assertSame(' WHERE NOT EXISTS (SELECT 1 WHERE x = ?)', $sql);
        $this->assertSame(['one', 'x'], $bindings);
    }
}
