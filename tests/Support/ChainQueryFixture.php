<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Support;

use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\DbQuery\DbQuery;

/**
 * The "chain" query form: restrict the rows of a root table to the nodes a recursive chain reaches.
 *
 * Form (the one a caller of dbquery composes for "a relation of unknown depth"):
 *
 *   WITH RECURSIVE <cte> AS (
 *     anchor                                              -- start nodes, own bindings
 *     UNION                                               -- never UNION ALL
 *     step JOIN <cte> ON link.member = <alias>.node       -- depth limit and step condition bound
 *     WHERE <alias>.depth < ? AND (link.ends_at IS NULL OR link.ends_at > ?)
 *   )
 *   SELECT ... FROM chain_groups g INNER JOIN chain_memberships m ON ...
 *    WHERE m.user_id = ? AND m.status = ? AND g.id IN (SELECT node FROM <cte>)
 *      AND EXISTS (correlated view subquery with two bindings)
 *    GROUP BY g.id
 *
 * The caller builds this from "node" and "depth" columns and raw conditions only; every
 * name in the text below belongs to the test model, not to a product.
 */
final class ChainQueryFixture
{
    public const USER_ANCHOR = 10;
    public const TENANT = 1;
    public const KIND = 'person';
    public const MAX_DEPTH = 3;
    public const NOW = 100;
    public const USER_OUTER = 20;
    public const STATUS = 'active';
    public const SCOPE = 'read';
    public const MIN_LEVEL = 2;

    /** The bindings of baseQuery() with the grant subquery, in placeholder order. */
    public const BINDINGS_WITH_GRANT = [10, 1, 'person', 3, 100, 20, 'active', 'read', 2];

    /** The bindings of baseQuery() without the grant subquery, in placeholder order. */
    public const BINDINGS_WITHOUT_GRANT = [10, 1, 'person', 3, 100, 20, 'active'];

    /**
     * The outer query without SELECT list, ORDER BY and limit: the shared base of list, count and probe.
     *
     * @param string $cte   name of the recursive table
     * @param string $alias alias of the recursive table in the step JOIN (raw conditions use the alias)
     */
    public static function baseQuery(
        string $cte = 'chain',
        string $alias = 'chain',
        bool $withGrant = true,
        int $maxDepth = self::MAX_DEPTH
    ): DbQueryBuilderInterface {
        $anchor = (new DbQuery())
            ->select('grp.id AS node, 0 AS depth')
            ->from('chain_groups', 'grp')
            ->where('grp.user_id')->equals(self::USER_ANCHOR)
            ->and('grp.tenant_id')->equals(self::TENANT)
            ->and('grp.kind')->equals(self::KIND);

        $step = (new DbQuery())
            ->select("link.group_id AS node, {$alias}.depth + 1 AS depth")
            ->from('chain_links', 'link')
            ->innerJoin($cte, "link.member_group_id = {$alias}.node", $alias)
            ->where("{$alias}.depth")->lower($maxDepth)
            ->and('link.ends_at', '(')->isNull()
            ->or('link.ends_at')->greater(self::NOW, ')');

        $reached = (new DbQuery())->select('node')->from($cte);

        $query = (new DbQuery())
            ->withRecursive($cte, $anchor->union($step))
            ->from('chain_groups', 'g')
            ->innerJoin('chain_memberships', 'm.tenant_id = g.tenant_id', 'm')
            ->where('m.user_id')->equals(self::USER_OUTER)
            ->and('m.status')->equals(self::STATUS)
            ->and('g.id')->in($reached);

        if ($withGrant) {
            $granted = (new DbQuery())
                ->select('1')
                ->from('chain_grants', 'gr')
                ->where('gr.group_id')->equals(Expression::column('g.id'))
                ->and('gr.scope')->equals(self::SCOPE)
                ->and('gr.level')->greater(self::MIN_LEVEL);
            $query = $query->and()->exists($granted);
        }

        return $query->groupBy('g.id');
    }

    /** The list form: one row per reached group with the number of its matching memberships. */
    public static function listQuery(
        string $cte = 'chain',
        string $alias = 'chain',
        bool $withGrant = true,
        int $maxDepth = self::MAX_DEPTH
    ): DbQueryBuilderInterface {
        return self::baseQuery($cte, $alias, $withGrant, $maxDepth)
            ->select('g.id AS id, COUNT(m.id) AS memberships')
            ->orderBy('g.id', 'ASC');
    }

    /** The count shell over the whole base query: the same set as the list. */
    public static function countQuery(
        string $cte = 'chain',
        string $alias = 'chain',
        bool $withGrant = true,
        int $maxDepth = self::MAX_DEPTH
    ): DbQueryBuilderInterface {
        return (new DbQuery())
            ->select('COUNT(*) AS total')
            ->from(self::baseQuery($cte, $alias, $withGrant, $maxDepth)->select('g.id'), 'matching');
    }

    /** The capped count: the base query reduced to "1" and limited, counted from outside. */
    public static function cappedCountQuery(
        int $cap,
        string $cte = 'chain',
        string $alias = 'chain',
        bool $withGrant = true,
        int $maxDepth = self::MAX_DEPTH
    ): DbQueryBuilderInterface {
        return (new DbQuery())
            ->select('COUNT(*) AS total')
            ->from(self::baseQuery($cte, $alias, $withGrant, $maxDepth)->select('1')->limit($cap), 'matching');
    }
}
