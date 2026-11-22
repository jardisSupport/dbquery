<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Integration\CrossDialect;

use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Tests\Integration\DatabaseConnection;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A JOIN condition made of column pairs against the real databases.
 *
 * Class under test: an identifier inside a JOIN condition went past the dialect quoting.
 * Invariant: root alias `group` (reserved word), partner alias `user` (reserved word) and a camelCase
 * column/alias with two column pairs run on MySQL, MariaDB, PostgreSQL and SQLite; the same join
 * written as raw text fails on PostgreSQL (case folding / reserved word).
 *
 * Fixture: tables are created with quoted camelCase columns (`tenantId`, `groupId`). Rows read are
 * asserted, never the SQL text.
 */
class JoinOnPairsIntegrationTest extends TestCase
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
            foreach (['jop_users', 'jop_groups'] as $table) {
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

    private function seed(string $dialect): PDO
    {
        $pdo = $this->connection($dialect);
        foreach (['jop_users', 'jop_groups'] as $table) {
            $this->db->dropTestTable($pdo, $table);
        }

        $q = $dialect === 'postgres' ? '"' : '`';
        $pdo->exec(
            "CREATE TABLE {$q}jop_groups{$q} ({$q}id{$q} INT NOT NULL, {$q}tenantId{$q} INT NOT NULL, "
            . "{$q}name{$q} VARCHAR(50) NOT NULL)"
        );
        $pdo->exec(
            "CREATE TABLE {$q}jop_users{$q} ({$q}id{$q} INT NOT NULL, {$q}tenantId{$q} INT NOT NULL, "
            . "{$q}groupId{$q} INT NOT NULL, {$q}login{$q} VARCHAR(50) NOT NULL)"
        );

        // group 1 exists in tenant 1 and tenant 2: the tenant pair must keep the rows apart
        $groups = [[1, 1, 'alpha'], [1, 2, 'beta'], [2, 1, 'gamma']];
        foreach ($groups as [$id, $tenant, $name]) {
            $stmt = $pdo->prepare(
                "INSERT INTO {$q}jop_groups{$q} ({$q}id{$q}, {$q}tenantId{$q}, {$q}name{$q}) VALUES (?, ?, ?)"
            );
            $stmt->execute([$id, $tenant, $name]);
        }
        $users = [[10, 1, 1, 'ann'], [11, 2, 1, 'bob'], [12, 1, 2, 'cid'], [13, 1, 9, 'dan']];
        foreach ($users as [$id, $tenant, $group, $login]) {
            $stmt = $pdo->prepare(
                "INSERT INTO {$q}jop_users{$q} ({$q}id{$q}, {$q}tenantId{$q}, {$q}groupId{$q}, {$q}login{$q}) "
                . 'VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$id, $tenant, $group, $login]);
        }

        return $pdo;
    }

    /** @return list<array<string, mixed>> */
    private function fetch(PDO $pdo, string $dialect, DbQuery $query): array
    {
        $prepared = $query->sql($dialect);
        $stmt = $pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    #[DataProvider('dialects')]
    public function testTwoColumnPairsJoinReservedAndCamelCaseAliases(string $dialect): void
    {
        $pdo = $this->seed($dialect);

        $query = (new DbQuery())
            ->select('group.name AS groupName, user.login AS userLogin')
            ->from('jop_groups', 'group')
            ->innerJoin(
                'jop_users',
                Expression::joinOn([['group.tenantId', 'user.tenantId'], ['group.id', 'user.groupId']]),
                'user'
            )
            ->orderBy('user.id');

        $rows = $this->fetch($pdo, $dialect, $query);

        $this->assertSame(
            [['alpha', 'ann'], ['beta', 'bob'], ['gamma', 'cid']],
            array_map(static fn(array $r): array => [$r['groupName'], $r['userLogin']], $rows)
        );
    }

    #[DataProvider('dialects')]
    public function testCamelCaseAliasWithPairs(string $dialect): void
    {
        $pdo = $this->seed($dialect);

        $query = (new DbQuery())
            ->select('memberUser.login AS userLogin')
            ->from('jop_groups', 'group')
            ->leftJoin(
                'jop_users',
                Expression::joinOn([['group.tenantId', 'memberUser.tenantId'], ['group.id', 'memberUser.groupId']]),
                'memberUser'
            )
            ->where('group.name')->equals('gamma');

        $rows = $this->fetch($pdo, $dialect, $query);

        $this->assertSame([['userLogin' => 'cid']], $rows);
    }

    /**
     * Rot-Beleg: the same join as raw text. PostgreSQL folds the unquoted camelCase columns to
     * lower case (and rejects the reserved word alias), the other three accept it.
     */
    public function testSameJoinAsRawTextFailsOnPostgres(): void
    {
        $pdo = $this->seed('postgres');

        $query = (new DbQuery())
            ->select('group.name AS groupName')
            ->from('jop_groups', 'group')
            ->innerJoin(
                'jop_users',
                'group.tenantId = user.tenantId AND group.id = user.groupId',
                'user'
            );

        try {
            $this->fetch($pdo, 'postgres', $query);
            $this->fail('Raw join condition was expected to fail on PostgreSQL');
        } catch (PDOException $e) {
            $this->assertSame('42601', $e->errorInfo[0] ?? $e->getCode());
        }
    }

    /** @return array<string, array{string}> */
    public static function dialectsAcceptingRawText(): array
    {
        return ['mysql' => ['mysql'], 'mariadb' => ['mariadb'], 'sqlite' => ['sqlite']];
    }

    /** Rohtext stays valid and raw on the databases that accept it (PostgreSQL: see the rot-beleg test). */
    #[DataProvider('dialectsAcceptingRawText')]
    public function testRawTextJoinStillRuns(string $dialect): void
    {
        $pdo = $this->seed($dialect);
        $q = '`';

        $query = (new DbQuery())
            ->select('g.name AS groupName')
            ->from('jop_groups', 'g')
            ->innerJoin(
                'jop_users',
                "g.{$q}tenantId{$q} = u.{$q}tenantId{$q} AND g.id = u.{$q}groupId{$q}",
                'u'
            )
            ->where('u.login')->equals('bob');

        $this->assertSame([['groupName' => 'beta']], $this->fetch($pdo, $dialect, $query));
    }
}
