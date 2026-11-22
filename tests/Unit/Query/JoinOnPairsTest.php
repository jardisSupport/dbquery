<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query;

use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\DbQuery\DbQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * JOIN ON condition built from column pairs (Expression::joinOn) in SELECT.
 *
 * Class under test: an identifier inside a JOIN condition went past the dialect quoting.
 * Invariant: a condition made of column pairs carries both sides quoted like FROM and alias
 * (reserved word `group`/`user`, camelCase); raw text stays byte-identical.
 */
class JoinOnPairsTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function dialects(): array
    {
        return [
            'mysql' => ['mysql', '`'],
            'mariadb' => ['mariadb', '`'],
            'postgres' => ['postgres', '"'],
            'sqlite' => ['sqlite', '`'],
        ];
    }

    #[DataProvider('dialects')]
    public function testPairsAreQuotedOnBothSides(string $dialect, string $q): void
    {
        $sql = (new DbQuery())
            ->select('group.id')
            ->from('groups', 'group')
            ->innerJoin(
                'users',
                Expression::joinOn([['group.tenantId', 'user.tenantId'], ['group.id', 'user.group_id']]),
                'user'
            )
            ->sql($dialect, false);

        $expected = "SELECT {$q}group{$q}.{$q}id{$q} FROM {$q}groups{$q} {$q}group{$q} "
            . "INNER JOIN {$q}users{$q} {$q}user{$q} ON "
            . "{$q}group{$q}.{$q}tenantId{$q} = {$q}user{$q}.{$q}tenantId{$q} AND "
            . "{$q}group{$q}.{$q}id{$q} = {$q}user{$q}.{$q}group_id{$q}";
        $this->assertSame($expected, $sql);
    }

    public function testPairsAreQuotedInPreparedMode(): void
    {
        $prepared = (new DbQuery())
            ->select('*')
            ->from('groups', 'group')
            ->leftJoin('users', Expression::joinOn([['group.id', 'user.groupId']]), 'user')
            ->where('user.status')->equals('active')
            ->sql('postgres', true);

        $this->assertSame(
            'SELECT * FROM "groups" "group" LEFT JOIN "users" "user" ON "group"."id" = "user"."groupId" '
            . 'WHERE "user"."status" = ?',
            $prepared->sql()
        );
        $this->assertSame(['active'], $prepared->bindings());
    }

    public function testEveryJoinTypeQuotesPairs(): void
    {
        $on = Expression::joinOn([['a.x', 'b.y']]);

        $sql = (new DbQuery())->select('*')->from('t', 'a')
            ->innerJoin('u', $on, 'b')
            ->leftJoin('v', $on, 'c')
            ->rightJoin('w', $on, 'd')
            ->sql('mysql', false);

        $this->assertSame(
            'SELECT * FROM `t` `a` INNER JOIN `u` `b` ON `a`.`x` = `b`.`y` '
            . 'LEFT JOIN `v` `c` ON `a`.`x` = `b`.`y` RIGHT JOIN `w` `d` ON `a`.`x` = `b`.`y`',
            $sql
        );
    }

    public function testFullJoinQuotesPairsOnPostgres(): void
    {
        $sql = (new DbQuery())->select('*')->from('t', 'a')
            ->fullJoin('u', Expression::joinOn([['a.x', 'b.y']]), 'b')
            ->sql('postgres', false);

        $this->assertSame('SELECT * FROM "t" "a" FULL OUTER JOIN "u" "b" ON "a"."x" = "b"."y"', $sql);
    }

    public function testSubqueryJoinQuotesPairs(): void
    {
        $sub = (new DbQuery())->select('user_id')->from('orders');

        $sql = (new DbQuery())->select('*')->from('users', 'user')
            ->innerJoin($sub, Expression::joinOn([['user.id', 'sq.user_id']]), 'sq')
            ->sql('postgres', false);

        $this->assertSame(
            'SELECT * FROM "users" "user" INNER JOIN (SELECT "user_id" FROM "orders") "sq" '
            . 'ON "user"."id" = "sq"."user_id"',
            $sql
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rawConstraints(): array
    {
        return [
            'plain pair' => ['users.id = p.user_id'],
            'function' => ['YEAR(a.created) = b.year'],
            'string literal with dot' => ["a.x = 'a.b'"],
            'already quoted' => ['`group`.id = `user`.groupId'],
            'number' => ['a.x = 1.5'],
        ];
    }

    #[DataProvider('rawConstraints')]
    public function testRawTextStaysByteIdentical(string $constraint): void
    {
        foreach (['mysql', 'postgres', 'sqlite'] as $dialect) {
            $sql = (new DbQuery())->select('*')->from('users')
                ->innerJoin('posts', $constraint, 'p')
                ->sql($dialect, false);

            $this->assertStringEndsWith(' ON ' . $constraint, $sql);
        }
    }
}
