<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Integration\CrossDialect;

use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\DbQuery\DbDelete;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\DbUpdate;
use JardisSupport\DbQuery\Tests\Integration\DatabaseConnection;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A subquery is rendered and bound exactly where it stands - whatever stands before it.
 *
 * Class under test: "a DbQuery used as a value or operand is only inlined in the first
 * condition of a statement". Invariant: every subquery sits at its own place and its
 * bindings sit between the bindings of the outer statement in placeholder order, in
 * prepared mode, on MySQL, MariaDB, PostgreSQL and SQLite.
 *
 * Fixture: users 1..4 (1, 2, 4 active; 3 inactive; ages 30, 25, 35, 28) and
 * grants(user_id, scope): scope "solo" -> user 1, "trio" -> users 1, 2, 3, 4, "none" -> nobody.
 */
class SubqueryPlacementIntegrationTest extends TestCase
{
    private DatabaseConnection $db;

    protected function setUp(): void
    {
        $this->db = new DatabaseConnection();
    }

    protected function tearDown(): void
    {
        foreach (['mysql', 'mariadb', 'postgres', 'sqlite'] as $dialect) {
            $pdo = $this->connection($dialect);
            $this->db->dropTestTable($pdo, 'grants');
            $this->db->dropTestTable($pdo, 'users');
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

    /** @return array<string, array{string, string, list<int>}> dialect, scope, expected ids */
    public static function dialectsWithHitCounts(): array
    {
        $cases = [];
        foreach (array_keys(self::dialects()) as $dialect) {
            $cases["$dialect / 0 hits"] = [$dialect, 'none', []];
            $cases["$dialect / 1 hit"] = [$dialect, 'solo', [1]];
            $cases["$dialect / 3 hits"] = [$dialect, 'trio', [1, 2, 4]];
        }

        return $cases;
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
        $this->db->createTestTable($pdo, $dialect, 'users');
        $this->db->dropTestTable($pdo, 'grants');
        $pdo->exec('CREATE TABLE grants (user_id INT NOT NULL, scope VARCHAR(20) NOT NULL)');

        $this->db->insertTestData($pdo, 'users', [
            ['name' => 'John', 'email' => 'john@example.com', 'status' => 'active', 'age' => 30],
            ['name' => 'Jane', 'email' => 'jane@example.com', 'status' => 'active', 'age' => 25],
            ['name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'inactive', 'age' => 35],
            ['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active', 'age' => 28],
        ]);
        $this->db->insertTestData($pdo, 'grants', [
            ['user_id' => 1, 'scope' => 'solo'],
            ['user_id' => 1, 'scope' => 'trio'],
            ['user_id' => 2, 'scope' => 'trio'],
            ['user_id' => 3, 'scope' => 'trio'],
            ['user_id' => 4, 'scope' => 'trio'],
        ]);

        return $pdo;
    }

    /** @return list<int> */
    private function ids(PDO $pdo, string $dialect, DbQuery $query): array
    {
        $prepared = $query->orderBy('id')->sql($dialect);
        $stmt = $pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function grantedTo(string $scope): DbQuery
    {
        return (new DbQuery())->select('user_id')->from('grants')->where('scope')->equals($scope);
    }

    /** @param list<int> $expected */
    #[DataProvider('dialectsWithHitCounts')]
    public function testInSubqueryAfterAnEarlierConditionFiltersByBothConditions(
        string $dialect,
        string $scope,
        array $expected
    ): void {
        $pdo = $this->seed($dialect);

        $query = (new DbQuery())->select('id')->from('users')
            ->where('status')->equals('active')
            ->and('id')->in($this->grantedTo($scope));

        $this->assertSame($expected, $this->ids($pdo, $dialect, $query));
    }

    /** @return array<string, array{string, string, int, list<int>}> */
    public static function scalarCases(): array
    {
        $cases = [];
        foreach (array_keys(self::dialects()) as $dialect) {
            // 1 = (SELECT COUNT(*) ...) is true for "solo" (one grant row) and false otherwise.
            $cases["$dialect / 0 hits"] = [$dialect, 'none', 0, []];
            $cases["$dialect / 1 hit"] = [$dialect, 'solo', 29, [1]];
            $cases["$dialect / 3 hits"] = [$dialect, 'solo', 0, [1, 2, 4]];
        }

        return $cases;
    }

    /** @param list<int> $expected */
    #[DataProvider('scalarCases')]
    public function testSubqueryAsRightOperandOfAnExpressionAfterAnEarlierCondition(
        string $dialect,
        string $scope,
        int $minAge,
        array $expected
    ): void {
        $pdo = $this->seed($dialect);

        $count = (new DbQuery())->select('COUNT(*)')->from('grants')->where('scope')->equals($scope);
        $query = (new DbQuery())->select('id')->from('users')
            ->where('status')->equals('active')
            ->and(Expression::integer(1))->equals($count)
            ->and('age')->greater($minAge);

        $this->assertSame($expected, $this->ids($pdo, $dialect, $query));
    }

    #[DataProvider('dialects')]
    public function testTwoSubqueriesWithOwnBindingsInOneStatement(string $dialect): void
    {
        $pdo = $this->seed($dialect);

        $oldestBelow29 = (new DbQuery())->select('MAX(age)')->from('users')
            ->where('status')->equals('active')
            ->and('age')->lower(29);
        $query = (new DbQuery())->select('id')->from('users')
            ->where('status')->equals('active')
            ->and('id')->in($this->grantedTo('trio'))
            ->and('age')->equals($oldestBelow29)
            ->and('name')->notEquals('Nobody');

        $this->assertSame([4], $this->ids($pdo, $dialect, $query));
    }

    #[DataProvider('dialects')]
    public function testSubqueriesInOrBranchAndBracketGroup(string $dialect): void
    {
        $pdo = $this->seed($dialect);

        $query = (new DbQuery())->select('id')->from('users')
            ->where('status')->equals('inactive')
            ->and('(id')->in($this->grantedTo('solo'))
            ->or('id')->in($this->grantedTo('none'), ')')
            ->and('age')->greaterEquals(0);
        $this->assertSame([], $this->ids($pdo, $dialect, $query));

        $query = (new DbQuery())->select('id')->from('users')
            ->where('status')->equals('active')
            ->and('(age')->greater(100)
            ->or('id')->in($this->grantedTo('solo'), ')')
            ->and('age')->greaterEquals(0);
        $this->assertSame([1], $this->ids($pdo, $dialect, $query));
    }

    #[DataProvider('dialects')]
    public function testNestedSubqueryAfterAnEarlierCondition(string $dialect): void
    {
        $pdo = $this->seed($dialect);

        $inner = $this->grantedTo('solo');
        $middle = (new DbQuery())->select('id')->from('users')
            ->where('status')->equals('active')
            ->and('id')->in($inner)
            ->and('age')->greater(0);
        $query = (new DbQuery())->select('id')->from('users')
            ->where('age')->greater(0)
            ->and('id')->in($middle);

        $this->assertSame([1], $this->ids($pdo, $dialect, $query));
    }

    #[DataProvider('dialects')]
    public function testExistsSubqueryAfterAnEarlierConditionAndBeforeALaterOne(string $dialect): void
    {
        $pdo = $this->seed($dialect);

        $query = (new DbQuery())->select('id')->from('users')
            ->where('status')->equals('active')
            ->exists($this->grantedTo('trio'))
            ->and('age')->greater(26);

        $this->assertSame([1, 4], $this->ids($pdo, $dialect, $query));
    }

    #[DataProvider('dialects')]
    public function testSubqueryInHaving(string $dialect): void
    {
        $pdo = $this->seed($dialect);

        // Scalar subqueries: MariaDB 11.8 returns no row for "HAVING MAX(id) IN (SELECT ...)" even when
        // written by hand, so the HAVING position is exercised with "=" there.
        $highest = (new DbQuery())->select('MAX(user_id)')->from('grants')->where('scope')->equals('trio');
        $lowest = (new DbQuery())->select('MIN(user_id)')->from('grants')->where('scope')->equals('solo');
        $query = (new DbQuery())->select('status')->from('users')
            ->where('age')->greater(0)
            ->groupBy('status')
            ->having('MAX(id)')->equals($highest)
            ->and('MIN(id)')->equals($lowest);

        $prepared = $query->sql($dialect);
        $stmt = $pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        $this->assertSame(['active'], $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    #[DataProvider('dialects')]
    public function testDeleteWithSubqueryAfterAnEarlierCondition(string $dialect): void
    {
        $pdo = $this->seed($dialect);

        $prepared = (new DbDelete())->from('users')
            ->where('status')->equals('inactive')
            ->and('id')->in($this->grantedTo('trio'))
            ->sql($dialect);
        $pdo->prepare($prepared->sql())->execute($prepared->bindings());

        $remaining = $this->ids($pdo, $dialect, (new DbQuery())->select('id')->from('users'));
        $this->assertSame([1, 2, 4], $remaining);
    }

    #[DataProvider('dialects')]
    public function testUpdateWithSubqueryAfterAnEarlierCondition(string $dialect): void
    {
        $pdo = $this->seed($dialect);

        $prepared = (new DbUpdate())->table('users')
            ->set('age', 99)
            ->where('status')->equals('active')
            ->and('id')->in($this->grantedTo('solo'))
            ->sql($dialect);
        $pdo->prepare($prepared->sql())->execute($prepared->bindings());

        $changed = $this->ids(
            $pdo,
            $dialect,
            (new DbQuery())->select('id')->from('users')->where('age')->equals(99)
        );
        $this->assertSame([1], $changed);
    }
}
