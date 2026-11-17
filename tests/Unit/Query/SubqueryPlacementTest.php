<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query;

use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\DbQuery\DbDelete;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\DbUpdate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A subquery is rendered and bound exactly where it stands - whatever stands before it.
 *
 * Class: "a DbQuery used as a value or operand is only inlined in the first condition of a
 * statement". Invariant: every subquery sits at its own place, its bindings sit between the
 * bindings of the outer statement in placeholder order - independent of position (first or later
 * condition, and/or, bracket group, having, expression on the left, in/exists), number
 * (0, 1, 2+) and mode (prepared / not prepared), on every dialect.
 *
 * Every subquery carries values of its own, so a swapped binding order cannot pass unnoticed.
 */
class SubqueryPlacementTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function dialects(): array
    {
        return [
            'mysql' => ['mysql'],
            'mariadb' => ['mariadb'],
            'postgres' => ['postgres'],
            'sqlite' => ['sqlite'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function mysqlFamily(): array
    {
        return ['mysql' => ['mysql'], 'mariadb' => ['mariadb']];
    }

    /** Backtick-quoted expectation -> the dialect's identifier quoting */
    private function q(string $dialect, string $sql): string
    {
        return $dialect === 'postgres' ? str_replace('`', '"', $sql) : $sql;
    }

    private function grants(string $table, string $scope, int $minRank): DbQuery
    {
        return (new DbQuery())->select('user_id')->from($table)
            ->where('scope')->equals($scope)
            ->and('rank')->greater($minRank);
    }

    /**
     * @param list<mixed> $bindings
     */
    private function assertQuery(
        string $dialect,
        DbQuery|DbUpdate|DbDelete $query,
        string $notPrepared,
        string $prepared,
        array $bindings
    ): void {
        $this->assertSame($this->q($dialect, $notPrepared), $query->sql($dialect, false));

        $result = $query->sql($dialect, true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertSame($this->q($dialect, $prepared), $result->sql());
        $this->assertSame($bindings, $result->bindings());
    }

    #[DataProvider('dialects')]
    public function testSubqueryInALaterCondition(string $dialect): void
    {
        $query = (new DbQuery())->select('*')->from('t')
            ->where('tenant')->equals('t1')
            ->and('id')->in($this->grants('g', 'read', 3))
            ->and('state')->equals('open');

        $this->assertQuery(
            $dialect,
            $query,
            "SELECT * FROM `t` WHERE `tenant` = 't1' AND `id` IN "
            . "(SELECT `user_id` FROM `g` WHERE `scope` = 'read' AND `rank` > 3) AND `state` = 'open'",
            'SELECT * FROM `t` WHERE `tenant` = ? AND `id` IN '
            . '(SELECT `user_id` FROM `g` WHERE `scope` = ? AND `rank` > ?) AND `state` = ?',
            ['t1', 'read', 3, 'open']
        );
    }

    #[DataProvider('dialects')]
    public function testSubqueryAsRightOperandOfAnExpressionOnTheLeft(string $dialect): void
    {
        $count = (new DbQuery())->select('COUNT(*)')->from('g')->where('scope')->equals('write');
        $query = (new DbQuery())->select('*')->from('t')
            ->where('tenant')->equals('t1')
            ->and(Expression::integer(1))->equals($count);

        $this->assertQuery(
            $dialect,
            $query,
            "SELECT * FROM `t` WHERE `tenant` = 't1' AND 1 = (SELECT COUNT(*) FROM `g` WHERE `scope` = 'write')",
            'SELECT * FROM `t` WHERE `tenant` = ? AND 1 = (SELECT COUNT(*) FROM `g` WHERE `scope` = ?)',
            ['t1', 'write']
        );
    }

    #[DataProvider('dialects')]
    public function testSubqueryInAnOrBranch(string $dialect): void
    {
        $query = (new DbQuery())->select('*')->from('t')
            ->where('tenant')->equals('t1')
            ->or('id')->in($this->grants('g', 'read', 3))
            ->and('state')->equals('open');

        $this->assertQuery(
            $dialect,
            $query,
            "SELECT * FROM `t` WHERE `tenant` = 't1' OR `id` IN "
            . "(SELECT `user_id` FROM `g` WHERE `scope` = 'read' AND `rank` > 3) AND `state` = 'open'",
            'SELECT * FROM `t` WHERE `tenant` = ? OR `id` IN '
            . '(SELECT `user_id` FROM `g` WHERE `scope` = ? AND `rank` > ?) AND `state` = ?',
            ['t1', 'read', 3, 'open']
        );
    }

    #[DataProvider('dialects')]
    public function testSubqueriesInABracketGroup(string $dialect): void
    {
        $query = (new DbQuery())->select('*')->from('t')
            ->where('tenant')->equals('t1')
            ->and('(id')->in($this->grants('g1', 'read', 1))
            ->or('owner')->in($this->grants('g2', 'own', 2), ')')
            ->and('state')->equals('open');

        $this->assertQuery(
            $dialect,
            $query,
            "SELECT * FROM `t` WHERE `tenant` = 't1' AND (id IN "
            . "(SELECT `user_id` FROM `g1` WHERE `scope` = 'read' AND `rank` > 1) OR `owner` IN "
            . "(SELECT `user_id` FROM `g2` WHERE `scope` = 'own' AND `rank` > 2)) AND `state` = 'open'",
            'SELECT * FROM `t` WHERE `tenant` = ? AND (id IN '
            . '(SELECT `user_id` FROM `g1` WHERE `scope` = ? AND `rank` > ?) OR `owner` IN '
            . '(SELECT `user_id` FROM `g2` WHERE `scope` = ? AND `rank` > ?)) AND `state` = ?',
            ['t1', 'read', 1, 'own', 2, 'open']
        );
    }

    #[DataProvider('dialects')]
    public function testTwoSubqueriesWithOwnBindingsAroundOuterValues(string $dialect): void
    {
        $query = (new DbQuery())->select('*')->from('t')
            ->where('a')->equals(1)
            ->and('id')->in($this->grants('g1', 'read', 11))
            ->and('b')->between(2, 3)
            ->and('owner')->equals($this->grants('g2', 'own', 22))
            ->and('c')->in([4, 5])
            ->and('d')->equals(6);

        $this->assertQuery(
            $dialect,
            $query,
            'SELECT * FROM `t` WHERE `a` = 1 AND `id` IN '
            . "(SELECT `user_id` FROM `g1` WHERE `scope` = 'read' AND `rank` > 11) AND `b` BETWEEN 2 AND 3 "
            . "AND `owner` = (SELECT `user_id` FROM `g2` WHERE `scope` = 'own' AND `rank` > 22) "
            . 'AND `c` IN (4, 5) AND `d` = 6',
            'SELECT * FROM `t` WHERE `a` = ? AND `id` IN '
            . '(SELECT `user_id` FROM `g1` WHERE `scope` = ? AND `rank` > ?) AND `b` BETWEEN ? AND ? '
            . 'AND `owner` = (SELECT `user_id` FROM `g2` WHERE `scope` = ? AND `rank` > ?) '
            . 'AND `c` IN (?, ?) AND `d` = ?',
            [1, 'read', 11, 2, 3, 'own', 22, 4, 5, 6]
        );
    }

    #[DataProvider('dialects')]
    public function testSubqueryNestedInASubquery(string $dialect): void
    {
        $inner = $this->grants('g2', 'own', 2);
        $middle = (new DbQuery())->select('user_id')->from('g1')
            ->where('scope')->equals('read')
            ->and('user_id')->in($inner)
            ->and('rank')->greater(1);
        $query = (new DbQuery())->select('*')->from('t')
            ->where('tenant')->equals('t1')
            ->and('id')->in($middle)
            ->and('state')->equals('open');

        $this->assertQuery(
            $dialect,
            $query,
            "SELECT * FROM `t` WHERE `tenant` = 't1' AND `id` IN "
            . "(SELECT `user_id` FROM `g1` WHERE `scope` = 'read' AND `user_id` IN "
            . "(SELECT `user_id` FROM `g2` WHERE `scope` = 'own' AND `rank` > 2) AND `rank` > 1) "
            . "AND `state` = 'open'",
            'SELECT * FROM `t` WHERE `tenant` = ? AND `id` IN '
            . '(SELECT `user_id` FROM `g1` WHERE `scope` = ? AND `user_id` IN '
            . '(SELECT `user_id` FROM `g2` WHERE `scope` = ? AND `rank` > ?) AND `rank` > ?) '
            . 'AND `state` = ?',
            ['t1', 'read', 'own', 2, 1, 'open']
        );
    }

    #[DataProvider('dialects')]
    public function testExistsAndNotExistsBetweenOuterConditions(string $dialect): void
    {
        $query = (new DbQuery())->select('*')->from('t')
            ->where('tenant')->equals('t1')
            ->exists($this->grants('g1', 'read', 1))
            ->and('state')->equals('open')
            ->notExists($this->grants('g2', 'ban', 2))
            ->and('kind')->in($this->grants('g3', 'kind', 3));

        $this->assertQuery(
            $dialect,
            $query,
            "SELECT * FROM `t` WHERE `tenant` = 't1' AND EXISTS "
            . "(SELECT `user_id` FROM `g1` WHERE `scope` = 'read' AND `rank` > 1) AND `state` = 'open' AND NOT EXISTS "
            . "(SELECT `user_id` FROM `g2` WHERE `scope` = 'ban' AND `rank` > 2) AND `kind` IN "
            . "(SELECT `user_id` FROM `g3` WHERE `scope` = 'kind' AND `rank` > 3)",
            'SELECT * FROM `t` WHERE `tenant` = ? AND EXISTS '
            . '(SELECT `user_id` FROM `g1` WHERE `scope` = ? AND `rank` > ?) AND `state` = ? AND NOT EXISTS '
            . '(SELECT `user_id` FROM `g2` WHERE `scope` = ? AND `rank` > ?) AND `kind` IN '
            . '(SELECT `user_id` FROM `g3` WHERE `scope` = ? AND `rank` > ?)',
            ['t1', 'read', 1, 'open', 'ban', 2, 'kind', 3]
        );
    }

    #[DataProvider('dialects')]
    public function testExistsOfTheConditionBuilderBetweenOuterConditions(string $dialect): void
    {
        // exists() on the condition builder (EXISTS ?) instead of the query's exists() shortcut
        $query = (new DbQuery())->select('*')->from('t')
            ->where('tenant')->equals('t1')
            ->and('')->exists($this->grants('g1', 'read', 1))
            ->and('state')->equals('open');

        $this->assertQuery(
            $dialect,
            $query,
            "SELECT * FROM `t` WHERE `tenant` = 't1' AND EXISTS "
            . "(SELECT `user_id` FROM `g1` WHERE `scope` = 'read' AND `rank` > 1) AND `state` = 'open'",
            'SELECT * FROM `t` WHERE `tenant` = ? AND EXISTS '
            . '(SELECT `user_id` FROM `g1` WHERE `scope` = ? AND `rank` > ?) AND `state` = ?',
            ['t1', 'read', 1, 'open']
        );
    }

    #[DataProvider('dialects')]
    public function testSubqueriesInWhereAndHaving(string $dialect): void
    {
        $query = (new DbQuery())->select('status, COUNT(*) AS n')->from('t')
            ->where('a')->equals(1)
            ->and('id')->in($this->grants('g1', 'read', 11))
            ->groupBy('status')
            ->having('COUNT(*)')->greater(2)
            ->and('MAX(owner)')->equals($this->grants('g2', 'own', 22))
            ->and('MIN(id)')->greater(3);

        $this->assertQuery(
            $dialect,
            $query,
            "SELECT `status`, COUNT(*) AS `n` FROM `t` WHERE `a` = 1 AND `id` IN "
            . "(SELECT `user_id` FROM `g1` WHERE `scope` = 'read' AND `rank` > 11) GROUP BY `status` "
            . "HAVING COUNT(*) > 2 AND MAX(owner) = "
            . "(SELECT `user_id` FROM `g2` WHERE `scope` = 'own' AND `rank` > 22) AND MIN(id) > 3",
            'SELECT `status`, COUNT(*) AS `n` FROM `t` WHERE `a` = ? AND `id` IN '
            . '(SELECT `user_id` FROM `g1` WHERE `scope` = ? AND `rank` > ?) GROUP BY `status` '
            . 'HAVING COUNT(*) > ? AND MAX(owner) = '
            . '(SELECT `user_id` FROM `g2` WHERE `scope` = ? AND `rank` > ?) AND MIN(id) > ?',
            [1, 'read', 11, 2, 'own', 22, 3]
        );
    }

    #[DataProvider('dialects')]
    public function testBindingsFollowTheSqlThroughCteSelectFromJoinWhereAndUnion(string $dialect): void
    {
        $query = (new DbQuery())
            ->with('c', $this->grants('gc', 'cte', 1))
            ->select('a')
            ->selectSubquery($this->grants('gs', 'sel', 2), 'sub')
            ->from($this->grants('gf', 'from', 3), 'f')
            ->innerJoin($this->grants('gj', 'join', 4), 'j.id = f.id', 'j')
            ->where('tenant')->equals('t1')
            ->and('id')->in($this->grants('gw', 'where', 5))
            ->union($this->grants('gu', 'union', 6));

        $this->assertQuery(
            $dialect,
            $query,
            "WITH `c` AS (SELECT `user_id` FROM `gc` WHERE `scope` = 'cte' AND `rank` > 1) "
            . "SELECT `a`, (SELECT `user_id` FROM `gs` WHERE `scope` = 'sel' AND `rank` > 2) AS `sub` "
            . "FROM (SELECT `user_id` FROM `gf` WHERE `scope` = 'from' AND `rank` > 3) `f` "
            . "INNER JOIN (SELECT `user_id` FROM `gj` WHERE `scope` = 'join' AND `rank` > 4) `j` ON j.id = f.id "
            . "WHERE `tenant` = 't1' AND `id` IN "
            . "(SELECT `user_id` FROM `gw` WHERE `scope` = 'where' AND `rank` > 5) "
            . "UNION SELECT `user_id` FROM `gu` WHERE `scope` = 'union' AND `rank` > 6",
            'WITH `c` AS (SELECT `user_id` FROM `gc` WHERE `scope` = ? AND `rank` > ?) '
            . 'SELECT `a`, (SELECT `user_id` FROM `gs` WHERE `scope` = ? AND `rank` > ?) AS `sub` '
            . 'FROM (SELECT `user_id` FROM `gf` WHERE `scope` = ? AND `rank` > ?) `f` '
            . 'INNER JOIN (SELECT `user_id` FROM `gj` WHERE `scope` = ? AND `rank` > ?) `j` ON j.id = f.id '
            . 'WHERE `tenant` = ? AND `id` IN '
            . '(SELECT `user_id` FROM `gw` WHERE `scope` = ? AND `rank` > ?) '
            . 'UNION SELECT `user_id` FROM `gu` WHERE `scope` = ? AND `rank` > ?',
            ['cte', 1, 'sel', 2, 'from', 3, 'join', 4, 't1', 'where', 5, 'union', 6]
        );
    }

    #[DataProvider('dialects')]
    public function testDeleteWithSubqueriesAfterAnEarlierCondition(string $dialect): void
    {
        $query = (new DbDelete())->from('t')
            ->where('tenant')->equals('t1')
            ->and('id')->in($this->grants('g1', 'read', 1))
            ->exists($this->grants('g2', 'own', 2))
            ->and('state')->equals('open');

        $this->assertQuery(
            $dialect,
            $query,
            "DELETE FROM `t` WHERE `tenant` = 't1' AND `id` IN "
            . "(SELECT `user_id` FROM `g1` WHERE `scope` = 'read' AND `rank` > 1) AND EXISTS "
            . "(SELECT `user_id` FROM `g2` WHERE `scope` = 'own' AND `rank` > 2) AND `state` = 'open'",
            'DELETE FROM `t` WHERE `tenant` = ? AND `id` IN '
            . '(SELECT `user_id` FROM `g1` WHERE `scope` = ? AND `rank` > ?) AND EXISTS '
            . '(SELECT `user_id` FROM `g2` WHERE `scope` = ? AND `rank` > ?) AND `state` = ?',
            ['t1', 'read', 1, 'own', 2, 'open']
        );
    }

    #[DataProvider('mysqlFamily')]
    public function testDeleteJoinSubqueryBindsBeforeTheWhereConditions(string $dialect): void
    {
        $query = (new DbDelete())->from('t')
            ->innerJoin($this->grants('gj', 'join', 4), 'j.id = t.id', 'j')
            ->where('tenant')->equals('t1')
            ->and('id')->in($this->grants('gw', 'where', 5));

        $result = $query->sql($dialect, true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertSame(['join', 4, 't1', 'where', 5], $result->bindings());
        $this->assertStringContainsString(
            "INNER JOIN (SELECT `user_id` FROM `gj` WHERE `scope` = 'join' AND `rank` > 4) `j` ON j.id = t.id "
            . "WHERE `tenant` = 't1' AND `id` IN "
            . "(SELECT `user_id` FROM `gw` WHERE `scope` = 'where' AND `rank` > 5)",
            (string) $query->sql($dialect, false)
        );
    }

    #[DataProvider('dialects')]
    public function testUpdateWithSubqueriesInSetAndAfterAnEarlierCondition(string $dialect): void
    {
        $query = (new DbUpdate())->table('t')
            ->set('owner', $this->grants('g0', 'set', 9))
            ->set('state', 'closed')
            ->where('tenant')->equals('t1')
            ->and('id')->in($this->grants('g1', 'read', 1))
            ->and('kind')->equals($this->grants('g2', 'kind', 2))
            ->and('level')->greater(7);

        $this->assertQuery(
            $dialect,
            $query,
            "UPDATE `t` SET `owner` = (SELECT `user_id` FROM `g0` WHERE `scope` = 'set' AND `rank` > 9), "
            . "`state` = 'closed' WHERE `tenant` = 't1' AND `id` IN "
            . "(SELECT `user_id` FROM `g1` WHERE `scope` = 'read' AND `rank` > 1) AND `kind` = "
            . "(SELECT `user_id` FROM `g2` WHERE `scope` = 'kind' AND `rank` > 2) AND `level` > 7",
            'UPDATE `t` SET `owner` = (SELECT `user_id` FROM `g0` WHERE `scope` = ? AND `rank` > ?), '
            . '`state` = ? WHERE `tenant` = ? AND `id` IN '
            . '(SELECT `user_id` FROM `g1` WHERE `scope` = ? AND `rank` > ?) AND `kind` = '
            . '(SELECT `user_id` FROM `g2` WHERE `scope` = ? AND `rank` > ?) AND `level` > ?',
            ['set', 9, 'closed', 't1', 'read', 1, 'kind', 2, 7]
        );
    }

    #[DataProvider('dialects')]
    public function testQueryCanBeRenderedRepeatedlyWithTheSameResult(string $dialect): void
    {
        $query = (new DbQuery())->select('*')->from('t')
            ->where('tenant')->equals('t1')
            ->and('id')->in($this->grants('g', 'read', 3));

        $first = $query->sql($dialect, true);
        $second = $query->sql($dialect, true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $first);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $second);
        $this->assertSame($first->sql(), $second->sql());
        $this->assertSame(['t1', 'read', 3], $first->bindings());
        $this->assertSame($first->bindings(), $second->bindings());
    }
}
