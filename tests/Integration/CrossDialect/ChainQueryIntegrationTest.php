<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Integration\CrossDialect;

use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Tests\Integration\DatabaseConnection;
use JardisSupport\DbQuery\Tests\Support\ChainQueryFixture;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The "chain" query form against the real databases (see ChainQueryFixture).
 *
 * Class under test: "a query over a relation of unknown depth" - a root table restricted to the
 * nodes a recursive CTE reaches, as `key IN (SELECT node FROM <cte>)`, with the page, the count
 * and the capped probe reading the same set. Invariants: bindings stand in placeholder order in
 * every part; the chain ends on a cycle (UNION plus depth limit); the CTE name is written the same
 * way everywhere; a chain of length 1000 with limit 1000 runs - on MySQL, MariaDB, PostgreSQL and SQLite.
 *
 * Fixture (tenant 1 unless noted; chain climbs from "member" to "group"):
 * groups 1 (user 10, kind person, the anchor), 2, 3, 4, 5, 10, 11 plus 6 (user 10, tenant 2),
 * 7 (user 10, kind team), 8, 9 (tenant 2); links group <- member: 2<-1, 5<-1 (ends 300), 3<-2,
 * 4<-3 (ended at 50), 10<-3, 11<-10, 9<-6, 8<-7. With "now" 100 and depth limit 3 the chain
 * reaches 1, 2, 3, 5, 10 - not 4 (link ended), 11 (depth 4), 6..9 (not the anchor).
 */
class ChainQueryIntegrationTest extends TestCase
{
    private DatabaseConnection $db;

    protected function setUp(): void
    {
        $this->db = new DatabaseConnection();
    }

    protected function tearDown(): void
    {
        foreach (array_keys(self::dialects()) as $dialect) {
            $pdo = $this->connection($dialect);
            foreach (['chain_grants', 'chain_memberships', 'chain_links', 'chain_groups'] as $table) {
                $this->db->dropTestTable($pdo, $table);
            }
        }
    }

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

    private function connection(string $dialect): PDO
    {
        return match ($dialect) {
            'mysql' => $this->db->getMysqlConnection(),
            'mariadb' => $this->db->getMariaDbConnection(),
            'postgres' => $this->db->getPostgresConnection(),
            'sqlite' => $this->db->getSqliteConnection(),
        };
    }

    private function createTables(PDO $pdo): void
    {
        foreach (['chain_grants', 'chain_memberships', 'chain_links', 'chain_groups'] as $table) {
            $this->db->dropTestTable($pdo, $table);
        }
        $pdo->exec('CREATE TABLE chain_groups (id INT NOT NULL PRIMARY KEY, user_id INT NULL, '
            . 'tenant_id INT NOT NULL, kind VARCHAR(20) NOT NULL)');
        $pdo->exec('CREATE TABLE chain_links (group_id INT NOT NULL, member_group_id INT NOT NULL, ends_at INT NULL)');
        $pdo->exec('CREATE TABLE chain_memberships (id INT NOT NULL PRIMARY KEY, user_id INT NOT NULL, '
            . 'tenant_id INT NOT NULL, status VARCHAR(20) NOT NULL)');
        $pdo->exec('CREATE TABLE chain_grants (group_id INT NOT NULL, scope VARCHAR(20) NOT NULL, level INT NOT NULL)');
    }

    private function seedStandard(string $dialect): PDO
    {
        $pdo = $this->connection($dialect);
        $this->createTables($pdo);

        $groups = [
            [1, 10, 1, 'person'], [2, null, 1, 'group'], [3, null, 1, 'group'], [4, null, 1, 'group'],
            [5, null, 1, 'group'], [6, 10, 2, 'person'], [7, 10, 1, 'team'], [8, null, 1, 'group'],
            [9, null, 2, 'group'], [10, null, 1, 'group'], [11, null, 1, 'group'],
        ];
        foreach ($groups as [$id, $user, $tenant, $kind]) {
            $this->insertGroup($pdo, $id, $user, $tenant, $kind);
        }
        $links = [
            [2, 1, null], [5, 1, 300], [3, 2, null], [4, 3, 50], [10, 3, null], [11, 10, null],
            [9, 6, null], [8, 7, null],
        ];
        foreach ($links as [$group, $member, $endsAt]) {
            $this->insertLink($pdo, $group, $member, $endsAt);
        }
        $memberships = [
            [1, 20, 1, 'active'], [2, 20, 1, 'active'], [3, 20, 1, 'blocked'], [4, 21, 1, 'active'],
            [5, 20, 2, 'active'],
        ];
        foreach ($memberships as [$id, $user, $tenant, $status]) {
            $this->insert($pdo, 'chain_memberships', [
                'id' => $id, 'user_id' => $user, 'tenant_id' => $tenant, 'status' => $status,
            ]);
        }
        $grants = [
            [1, 'write', 9], [2, 'read', 5], [2, 'read', 7], [3, 'read', 3], [3, 'write', 9],
            [4, 'read', 9], [5, 'read', 1], [10, 'read', 9], [11, 'read', 9],
        ];
        foreach ($grants as [$group, $scope, $level]) {
            $this->insert($pdo, 'chain_grants', ['group_id' => $group, 'scope' => $scope, 'level' => $level]);
        }

        return $pdo;
    }

    private function insertGroup(PDO $pdo, int $id, ?int $user, int $tenant, string $kind): void
    {
        $this->insert($pdo, 'chain_groups', ['id' => $id, 'user_id' => $user, 'tenant_id' => $tenant, 'kind' => $kind]);
    }

    private function insertLink(PDO $pdo, int $group, int $member, ?int $endsAt): void
    {
        $this->insert($pdo, 'chain_links', ['group_id' => $group, 'member_group_id' => $member, 'ends_at' => $endsAt]);
    }

    /** @param array<string, int|string|null> $row */
    private function insert(PDO $pdo, string $table, array $row): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO ' . $table . ' (' . implode(', ', array_keys($row)) . ') VALUES ('
            . implode(', ', array_fill(0, count($row), '?')) . ')'
        );
        $stmt->execute(array_values($row));
    }

    /** @return list<array<string, mixed>> */
    private function fetch(PDO $pdo, string $dialect, DbQueryBuilderInterface $query): array
    {
        $prepared = $query->sql($dialect);
        $stmt = $pdo->prepare($prepared->sql());
        $this->bindTyped($stmt, $prepared->bindings());
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Integers are bound as integers. The depth limit compares against a computed column (no type
     * affinity): SQLite sorts every integer below any text, so a limit bound as text would never stop
     * the chain (see testDepthLimitBoundAsTextDoesNotStopTheChainOnSqlite).
     *
     * @param list<mixed> $bindings
     */
    private function bindTyped(\PDOStatement $stmt, array $bindings): void
    {
        foreach (array_values($bindings) as $index => $value) {
            $stmt->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }

    private function scalar(PDO $pdo, string $dialect, DbQueryBuilderInterface $query): int
    {
        $rows = $this->fetch($pdo, $dialect, $query);
        $this->assertCount(1, $rows);

        return (int) array_values($rows[0])[0];
    }

    /** @return array<int, int> id => memberships */
    private function listedIds(PDO $pdo, string $dialect, bool $withGrant, int $maxDepth): array
    {
        return $this->idsWithMemberships(
            $this->fetch($pdo, $dialect, ChainQueryFixture::listQuery('chain', 'chain', $withGrant, $maxDepth))
        );
    }

    /** Count shell without the grant subquery. */
    private function total(PDO $pdo, string $dialect, int $maxDepth): int
    {
        return $this->scalar($pdo, $dialect, ChainQueryFixture::countQuery('chain', 'chain', false, $maxDepth));
    }

    /** Capped probe without the grant subquery. */
    private function cappedTotal(PDO $pdo, string $dialect, int $cap): int
    {
        return $this->scalar($pdo, $dialect, ChainQueryFixture::cappedCountQuery($cap, 'chain', 'chain', false));
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<int, int> id => memberships
     */
    private function idsWithMemberships(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['id']] = (int) $row['memberships'];
        }

        return $result;
    }

    // ---- AK 1: bindings in anchor, step (condition and depth limit), outer condition, exists ----

    #[DataProvider('dialects')]
    public function testBindingsInAnchorStepOuterConditionAndExistsFilterTheRightRows(string $dialect): void
    {
        $pdo = $this->seedStandard($dialect);

        $prepared = ChainQueryFixture::listQuery()->sql($dialect);
        $this->assertSame(ChainQueryFixture::BINDINGS_WITH_GRANT, $prepared->bindings());

        // reached 1, 2, 3, 5, 10; the grant subquery keeps read grants above level 2: 2, 3, 10 (not 5: level 1)
        $this->assertSame(
            [2 => 2, 3 => 2, 10 => 2],
            $this->idsWithMemberships($this->fetch($pdo, $dialect, ChainQueryFixture::listQuery()))
        );
    }

    #[DataProvider('dialects')]
    public function testEveryBindingIsDecisive(string $dialect): void
    {
        $pdo = $this->seedStandard($dialect);

        // depth limit 2 drops group 10 (depth 3); depth limit 0 leaves only the anchor
        $this->assertSame(
            [2 => 2, 3 => 2],
            $this->listedIds($pdo, $dialect, true, 2)
        );
        $this->assertSame(
            [],
            $this->listedIds($pdo, $dialect, true, 0)
        );
        $this->assertSame(
            [1 => 2],
            $this->listedIds($pdo, $dialect, false, 0)
        );
    }

    // ---- AK 2: IN (SELECT node FROM cte) + join on a real table + GROUP BY ----

    #[DataProvider('dialects')]
    public function testInOverTheChainWithJoinAndGroupByReturnsOneRowPerReachedGroup(string $dialect): void
    {
        $pdo = $this->seedStandard($dialect);

        $rows = $this->fetch($pdo, $dialect, ChainQueryFixture::listQuery('chain', 'chain', false));

        // the join multiplies each group by its two matching memberships; GROUP BY collapses them again
        $this->assertSame([1 => 2, 2 => 2, 3 => 2, 5 => 2, 10 => 2], $this->idsWithMemberships($rows));
        $this->assertSame([1, 2, 3, 5, 10], array_map(fn (array $row): int => (int) $row['id'], $rows));
    }

    // ---- AK 3: count shell and capped probe read the same set ----

    #[DataProvider('dialects')]
    public function testCountShellAndCappedProbeReadTheSameSetAsTheList(string $dialect): void
    {
        $pdo = $this->seedStandard($dialect);

        $this->assertSame(5, $this->scalar($pdo, $dialect, ChainQueryFixture::countQuery('chain', 'chain', false)));
        $this->assertSame(3, $this->scalar($pdo, $dialect, ChainQueryFixture::countQuery()));

        $this->assertSame(2, $this->cappedTotal($pdo, $dialect, 2));
        $this->assertSame(5, $this->cappedTotal($pdo, $dialect, 10));
        $this->assertSame(2, $this->scalar($pdo, $dialect, ChainQueryFixture::cappedCountQuery(2)));
        $this->assertSame(3, $this->scalar($pdo, $dialect, ChainQueryFixture::cappedCountQuery(10)));
    }

    // ---- AK 4: cycle - UNION plus depth limit ends and returns the reachable set ----

    /**
     * Cycle 1 -> 2/3 -> 4 -> 1 (a diamond inside the cycle: 4 is reached twice at the same depth).
     * UNION removes the duplicate, the depth limit ends the cycle, the reachable set is 1, 2, 3, 4.
     */
    #[DataProvider('dialects')]
    public function testCycleInTheDataEndsAndReturnsTheReachableSet(string $dialect): void
    {
        $pdo = $this->connection($dialect);
        $this->createTables($pdo);
        foreach ([[1, 10, 1, 'person'], [2, null, 1, 'group'], [3, null, 1, 'group'], [4, null, 1, 'group'],
                     [5, null, 1, 'group']] as [$id, $user, $tenant, $kind]) {
            $this->insertGroup($pdo, $id, $user, $tenant, $kind);
        }
        foreach ([[2, 1], [3, 1], [4, 2], [4, 3], [1, 4]] as [$group, $member]) {
            $this->insert($pdo, 'chain_links', ['group_id' => $group, 'member_group_id' => $member, 'ends_at' => null]);
        }
        $this->insert($pdo, 'chain_memberships', ['id' => 1, 'user_id' => 20, 'tenant_id' => 1, 'status' => 'active']);

        $rows = $this->fetch($pdo, $dialect, ChainQueryFixture::listQuery('chain', 'chain', false, 6));
        $this->assertSame([1 => 1, 2 => 1, 3 => 1, 4 => 1], $this->idsWithMemberships($rows));

        // the chain itself: UNION keeps one row per (node, depth): depths 0..6 give 1+2+1+1+2+1+1 rows
        // (UNION ALL would double the rows behind the diamond at every lap)
        $anchor = (new DbQuery())->select('grp.id AS node, 0 AS depth')->from('chain_groups', 'grp')
            ->where('grp.user_id')->equals(10);
        $step = (new DbQuery())->select('link.group_id AS node, chain.depth + 1 AS depth')
            ->from('chain_links', 'link')
            ->innerJoin('chain', 'link.member_group_id = chain.node', 'chain')
            ->where('chain.depth')->lower(6);
        $count = (new DbQuery())
            ->withRecursive('chain', $anchor->union($step))
            ->select('COUNT(*) AS total')->from('chain');
        $this->assertSame(9, $this->scalar($pdo, $dialect, $count));
    }

    /**
     * Pitfall of the form on SQLite (found by AK 4, which hung): bindings() returns the depth limit as
     * an int, but PDOStatement::execute($bindings) sends every value as text. The limit meets a computed
     * column, SQLite sorts integers below text, `depth < '0'` is always true and the chain runs past its
     * limit (here to group 11 at depth 4). Acyclic data, so it ends - on a cycle it would not.
     */
    public function testDepthLimitBoundAsTextDoesNotStopTheChainOnSqlite(): void
    {
        $pdo = $this->seedStandard('sqlite');
        $prepared = ChainQueryFixture::listQuery('chain', 'chain', false, 0)->sql('sqlite');
        $stmt = $pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $this->assertSame(
            [1 => 2, 2 => 2, 3 => 2, 5 => 2, 10 => 2, 11 => 2],
            $this->idsWithMemberships($stmt->fetchAll(PDO::FETCH_ASSOC))
        );
    }

    // ---- AK 5: the CTE name is written the same way in WITH, JOIN and FROM ----

    #[DataProvider('dialects')]
    public function testMixedCaseChainNameRunsBecauseWithJoinAndFromQuoteItTheSame(string $dialect): void
    {
        $pdo = $this->seedStandard($dialect);

        $rows = $this->fetch($pdo, $dialect, ChainQueryFixture::listQuery('ReachableSet', 'prev', false));

        $this->assertSame([1 => 2, 2 => 2, 3 => 2, 5 => 2, 10 => 2], $this->idsWithMemberships($rows));
    }

    // ---- AK 6 (G6): chains of length 1000 and the server limits on recursion depth ----
    //
    // Proven upper bound of maxDepth on all four databases: 999. MySQL aborts a recursive query after
    // 1001 iterations (cte_max_recursion_depth = 1000, error 3636) as soon as the DATA is deeper than
    // 999 and the limit allows it; MariaDB (max_recursive_iterations = 1000) stops silently at 1001
    // rows - no error, a truncated set; PostgreSQL and SQLite have no such limit.

    #[DataProvider('dialects')]
    public function testChainOfLengthNineHundredNinetyNineWithLimitNineHundredNinetyNineRuns(string $dialect): void
    {
        $pdo = $this->seedChain($dialect, 999);

        $this->assertSame(1000, $this->total($pdo, $dialect, 999));
        $this->assertSame(1000, $this->maxChainId($pdo, $dialect, 999));
    }

    #[DataProvider('dialects')]
    public function testLimitNineHundredNinetyNineStopsALongerChainWithoutServerAbort(string $dialect): void
    {
        $pdo = $this->seedChain($dialect, 1500);

        $this->assertSame(1000, $this->total($pdo, $dialect, 999));
        $this->assertSame(1000, $this->maxChainId($pdo, $dialect, 999));
    }

    /**
     * Chain of length 1000 with limit 1000 (the plan's G6 case): runs on MariaDB, PostgreSQL and SQLite,
     * is aborted by MySQL (1001 iterations needed, 1000 allowed by default).
     */
    #[DataProvider('dialects')]
    public function testChainOfLengthOneThousandWithLimitOneThousand(string $dialect): void
    {
        $pdo = $this->seedChain($dialect, 1000);

        if ($dialect === 'mysql') {
            $this->expectException(\PDOException::class);
            $this->expectExceptionMessage('Recursive query aborted after 1001 iterations');
        }

        $this->assertSame(1001, $this->total($pdo, $dialect, 1000));
        $this->assertSame(1001, $this->maxChainId($pdo, $dialect, 1000));
    }

    /** @return array<string, array{string, int|null}> dialect, rows reached (null: server aborts) */
    public static function behaviourBeyondTheServerLimit(): array
    {
        return [
            'mysql aborts' => ['mysql', null],
            'mariadb truncates silently at 1001' => ['mariadb', 1001],
            'postgres runs on' => ['postgres', 1501],
            'sqlite runs on' => ['sqlite', 1501],
        ];
    }

    /** Documents why the bound is 999: a limit above it meets the server limit on data 1500 deep. */
    #[DataProvider('behaviourBeyondTheServerLimit')]
    public function testLimitOneThousandFiveHundredOnAChainOfOneThousandFiveHundred(string $dialect, ?int $rows): void
    {
        $pdo = $this->seedChain($dialect, 1500);

        if ($rows === null) {
            $this->expectException(\PDOException::class);
            $this->expectExceptionMessage('Recursive query aborted after 1001 iterations');
        }

        $this->assertSame($rows, $this->total($pdo, $dialect, 1500));
    }

    /** One chain 1 <- 2 <- ... <- length+1 (group id = depth + 1), anchor group 1. */
    private function seedChain(string $dialect, int $length): PDO
    {
        $pdo = $this->connection($dialect);
        $this->createTables($pdo);

        $pdo->beginTransaction();
        $group = $pdo->prepare('INSERT INTO chain_groups (id, user_id, tenant_id, kind) VALUES (?, ?, 1, ?)');
        $link = $pdo->prepare('INSERT INTO chain_links (group_id, member_group_id, ends_at) VALUES (?, ?, NULL)');
        for ($id = 1; $id <= $length + 1; $id++) {
            $group->execute([$id, $id === 1 ? 10 : null, $id === 1 ? 'person' : 'group']);
            if ($id > 1) {
                $link->execute([$id, $id - 1]);
            }
        }
        $pdo->exec("INSERT INTO chain_memberships (id, user_id, tenant_id, status) VALUES (1, 20, 1, 'active')");
        $pdo->commit();

        return $pdo;
    }

    /** The deepest group the list reaches (ids run with the depth). */
    private function maxChainId(PDO $pdo, string $dialect, int $maxDepth): int
    {
        $rows = $this->fetch($pdo, $dialect, ChainQueryFixture::listQuery('chain', 'chain', false, $maxDepth));

        return (int) end($rows)['id'];
    }
}
