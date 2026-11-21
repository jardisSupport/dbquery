<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Support;

use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;

/**
 * SQL text and binding order of the "chain" query form (see ChainQueryFixture), per dialect.
 *
 * Used by the three dialect CTE tests; the using class names its dialect. Expectations are
 * written with backtick quoting and converted to the dialect's identifier quoting.
 */
trait ChainQueryUnitTests
{
    abstract protected function chainDialect(): string;

    private function chainQuoted(string $sql): string
    {
        return $this->chainDialect() === 'postgres' ? str_replace('`', '"', $sql) : $sql;
    }

    /**
     * @param list<mixed> $bindings
     */
    private function assertChainQuery(
        DbQueryBuilderInterface $query,
        string $notPrepared,
        string $prepared,
        array $bindings
    ): void {
        $dialect = $this->chainDialect();

        $this->assertSame($this->chainQuoted($notPrepared), $query->sql($dialect, false));

        $result = $query->sql($dialect, true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertSame($this->chainQuoted($prepared), $result->sql());
        $this->assertSame($bindings, $result->bindings());
    }

    private const CHAIN_CTE_NOT_PREPARED = 'WITH RECURSIVE `chain` AS ('
        . "SELECT `grp`.`id` AS `node`, 0 AS `depth` FROM `chain_groups` `grp` "
        . "WHERE `grp`.`user_id` = 10 AND `grp`.`tenant_id` = 1 AND `grp`.`kind` = 'person' "
        . 'UNION SELECT `link`.`group_id` AS `node`, chain.depth + 1 AS `depth` FROM `chain_links` `link` '
        . 'INNER JOIN `chain` `chain` ON link.member_group_id = chain.node '
        . 'WHERE `chain`.`depth` < 3 AND (`link`.`ends_at` IS NULL OR `link`.`ends_at` > 100)) ';

    private const CHAIN_CTE_PREPARED = 'WITH RECURSIVE `chain` AS ('
        . 'SELECT `grp`.`id` AS `node`, 0 AS `depth` FROM `chain_groups` `grp` '
        . 'WHERE `grp`.`user_id` = ? AND `grp`.`tenant_id` = ? AND `grp`.`kind` = ? '
        . 'UNION SELECT `link`.`group_id` AS `node`, chain.depth + 1 AS `depth` FROM `chain_links` `link` '
        . 'INNER JOIN `chain` `chain` ON link.member_group_id = chain.node '
        . 'WHERE `chain`.`depth` < ? AND (`link`.`ends_at` IS NULL OR `link`.`ends_at` > ?)) ';

    private const CHAIN_OUTER_NOT_PREPARED = 'FROM `chain_groups` `g` '
        . 'INNER JOIN `chain_memberships` `m` ON m.tenant_id = g.tenant_id '
        . "WHERE `m`.`user_id` = 20 AND `m`.`status` = 'active' "
        . 'AND `g`.`id` IN (SELECT `node` FROM `chain`) '
        . "AND EXISTS (SELECT 1 FROM `chain_grants` `gr` WHERE `gr`.`group_id` = `g`.`id` "
        . "AND `gr`.`scope` = 'read' AND `gr`.`level` > 2) GROUP BY `g`.`id`";

    private const CHAIN_OUTER_PREPARED = 'FROM `chain_groups` `g` '
        . 'INNER JOIN `chain_memberships` `m` ON m.tenant_id = g.tenant_id '
        . 'WHERE `m`.`user_id` = ? AND `m`.`status` = ? '
        . 'AND `g`.`id` IN (SELECT `node` FROM `chain`) '
        . 'AND EXISTS (SELECT 1 FROM `chain_grants` `gr` WHERE `gr`.`group_id` = `g`.`id` '
        . 'AND `gr`.`scope` = ? AND `gr`.`level` > ?) GROUP BY `g`.`id`';

    /**
     * Bindings stand in placeholder order: anchor, step (depth limit, then step condition), outer
     * condition, then the subquery behind it - with a join on a real table and GROUP BY around the IN.
     */
    public function testChainQueryBindsAnchorStepOuterConditionAndExistsInPlaceholderOrder(): void
    {
        $this->assertChainQuery(
            ChainQueryFixture::listQuery(),
            self::CHAIN_CTE_NOT_PREPARED
            . 'SELECT `g`.`id` AS `id`, COUNT(m.id) AS `memberships` '
            . self::CHAIN_OUTER_NOT_PREPARED . ' ORDER BY `g`.`id` ASC',
            self::CHAIN_CTE_PREPARED
            . 'SELECT `g`.`id` AS `id`, COUNT(m.id) AS `memberships` '
            . self::CHAIN_OUTER_PREPARED . ' ORDER BY `g`.`id` ASC',
            ChainQueryFixture::BINDINGS_WITH_GRANT
        );
    }

    public function testChainQueryWithoutTheExistsSubqueryKeepsTheRemainingBindingOrder(): void
    {
        $result = ChainQueryFixture::listQuery('chain', 'chain', false)->sql($this->chainDialect(), true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertSame(ChainQueryFixture::BINDINGS_WITHOUT_GRANT, $result->bindings());
        $this->assertStringNotContainsString('EXISTS', $result->sql());
    }

    /**
     * The whole query as FROM subquery of a COUNT(*): WITH RECURSIVE stands first inside the
     * derived table and the bindings move with it, unchanged and in order.
     */
    public function testChainQueryAsCountShellKeepsTheCteInsideTheDerivedTable(): void
    {
        $this->assertChainQuery(
            ChainQueryFixture::countQuery(),
            'SELECT COUNT(*) AS `total` FROM (' . self::CHAIN_CTE_NOT_PREPARED
            . 'SELECT `g`.`id` ' . self::CHAIN_OUTER_NOT_PREPARED . ') `matching`',
            'SELECT COUNT(*) AS `total` FROM (' . self::CHAIN_CTE_PREPARED
            . 'SELECT `g`.`id` ' . self::CHAIN_OUTER_PREPARED . ') `matching`',
            ChainQueryFixture::BINDINGS_WITH_GRANT
        );
    }

    public function testChainQueryAsCappedCountProbeReducesTheBaseToOneAndLimits(): void
    {
        $this->assertChainQuery(
            ChainQueryFixture::cappedCountQuery(3),
            'SELECT COUNT(*) AS `total` FROM (' . self::CHAIN_CTE_NOT_PREPARED
            . 'SELECT 1 ' . self::CHAIN_OUTER_NOT_PREPARED . ' LIMIT 3) `matching`',
            'SELECT COUNT(*) AS `total` FROM (' . self::CHAIN_CTE_PREPARED
            . 'SELECT 1 ' . self::CHAIN_OUTER_PREPARED . ' LIMIT 3) `matching`',
            ChainQueryFixture::BINDINGS_WITH_GRANT
        );
    }

    public function testChainStepIsJoinedWithUnionAndNeverUnionAll(): void
    {
        $result = ChainQueryFixture::listQuery()->sql($this->chainDialect(), true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertSame(1, substr_count($result->sql(), ' UNION '));
        $this->assertStringNotContainsString('UNION ALL', $result->sql());
    }

    /**
     * One name, written the same way in WITH, in the JOIN target of the step and in FROM of the
     * IN subquery. A mixed-case name would be folded by PostgreSQL if any of the three were unquoted.
     */
    public function testChainNameIsQuotedTheSameWayInWithJoinAndFrom(): void
    {
        $name = $this->chainQuoted('`ReachableSet`');

        $this->assertChainQuery(
            ChainQueryFixture::listQuery('ReachableSet', 'prev', false),
            'WITH RECURSIVE `ReachableSet` AS ('
            . "SELECT `grp`.`id` AS `node`, 0 AS `depth` FROM `chain_groups` `grp` "
            . "WHERE `grp`.`user_id` = 10 AND `grp`.`tenant_id` = 1 AND `grp`.`kind` = 'person' "
            . 'UNION SELECT `link`.`group_id` AS `node`, prev.depth + 1 AS `depth` FROM `chain_links` `link` '
            . 'INNER JOIN `ReachableSet` `prev` ON link.member_group_id = prev.node '
            . 'WHERE `prev`.`depth` < 3 AND (`link`.`ends_at` IS NULL OR `link`.`ends_at` > 100)) '
            . 'SELECT `g`.`id` AS `id`, COUNT(m.id) AS `memberships` FROM `chain_groups` `g` '
            . 'INNER JOIN `chain_memberships` `m` ON m.tenant_id = g.tenant_id '
            . "WHERE `m`.`user_id` = 20 AND `m`.`status` = 'active' "
            . 'AND `g`.`id` IN (SELECT `node` FROM `ReachableSet`) GROUP BY `g`.`id` ORDER BY `g`.`id` ASC',
            'WITH RECURSIVE `ReachableSet` AS ('
            . 'SELECT `grp`.`id` AS `node`, 0 AS `depth` FROM `chain_groups` `grp` '
            . 'WHERE `grp`.`user_id` = ? AND `grp`.`tenant_id` = ? AND `grp`.`kind` = ? '
            . 'UNION SELECT `link`.`group_id` AS `node`, prev.depth + 1 AS `depth` FROM `chain_links` `link` '
            . 'INNER JOIN `ReachableSet` `prev` ON link.member_group_id = prev.node '
            . 'WHERE `prev`.`depth` < ? AND (`link`.`ends_at` IS NULL OR `link`.`ends_at` > ?)) '
            . 'SELECT `g`.`id` AS `id`, COUNT(m.id) AS `memberships` FROM `chain_groups` `g` '
            . 'INNER JOIN `chain_memberships` `m` ON m.tenant_id = g.tenant_id '
            . 'WHERE `m`.`user_id` = ? AND `m`.`status` = ? '
            . 'AND `g`.`id` IN (SELECT `node` FROM `ReachableSet`) GROUP BY `g`.`id` ORDER BY `g`.`id` ASC',
            ChainQueryFixture::BINDINGS_WITHOUT_GRANT
        );

        $result = ChainQueryFixture::listQuery('ReachableSet', 'prev', false)->sql($this->chainDialect(), true);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $this->assertSame(3, substr_count($result->sql(), $name));
    }
}
