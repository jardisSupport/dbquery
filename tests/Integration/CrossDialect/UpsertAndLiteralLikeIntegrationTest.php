<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Integration\CrossDialect;

use InvalidArgumentException;
use JardisSupport\Contract\DbQuery\LikePatternInterface;
use JardisSupport\DbQuery\Data\LikePattern;
use JardisSupport\DbQuery\DbInsert;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\Tests\Integration\DatabaseConnection;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * One capability, every dialect, same effect - against the real databases.
 *
 * K1: onDuplicateKeyUpdate() upserts on MySQL, MariaDB, PostgreSQL and SQLite.
 * K2: LikePattern searches a literal substring on all four dialects.
 */
class UpsertAndLiteralLikeIntegrationTest extends TestCase
{
    private DatabaseConnection $db;

    protected function setUp(): void
    {
        $this->db = new DatabaseConnection();
    }

    protected function tearDown(): void
    {
        foreach (['mysql', 'mariadb', 'postgres', 'sqlite'] as $dialect) {
            $this->db->dropTestTable($this->connection($dialect), 'users');
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

    private function prepareTable(string $dialect): PDO
    {
        $pdo = $this->connection($dialect);
        $this->db->createTestTable($pdo, $dialect, 'users');

        return $pdo;
    }

    /** @param list<string> $names */
    private function seedNames(PDO $pdo, array $names): void
    {
        $rows = [];
        foreach ($names as $i => $name) {
            $rows[] = ['name' => $name, 'email' => "u{$i}@example.com", 'status' => 'active', 'age' => 1];
        }
        $this->db->insertTestData($pdo, 'users', $rows);
    }

    /** @return list<string> */
    private function names(PDO $pdo, string $dialect, DbQuery $query): array
    {
        $prepared = $query->orderBy('name')->sql($dialect);
        $stmt = $pdo->prepare($prepared->sql());
        $stmt->execute($prepared->bindings());

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    #[DataProvider('dialects')]
    public function testUpsertTwiceWithSameKeyYieldsOneUpdatedRow(string $dialect): void
    {
        $pdo = $this->prepareTable($dialect);

        for ($round = 1; $round <= 2; $round++) {
            $prepared = (new DbInsert())
                ->into('users')
                ->set(['name' => 'Name ' . $round, 'email' => 'a@example.com', 'age' => $round])
                ->onConflict('email')
                ->onDuplicateKeyUpdate('name', 'Name ' . $round)
                ->onDuplicateKeyUpdate('age', $round)
                ->sql($dialect);

            $pdo->prepare($prepared->sql())->execute($prepared->bindings());
        }

        $rows = $this->db->getAllRows($pdo, 'users');
        $this->assertCount(1, $rows);
        $this->assertSame('Name 2', $rows[0]['name']);
        $this->assertEquals(2, $rows[0]['age']);
    }

    #[DataProvider('dialects')]
    public function testDoUpdateEntryPointHasTheSameEffectInEveryDialect(string $dialect): void
    {
        $pdo = $this->prepareTable($dialect);

        for ($round = 1; $round <= 2; $round++) {
            $prepared = (new DbInsert())
                ->into('users')
                ->set(['name' => 'Name ' . $round, 'email' => 'b@example.com'])
                ->onConflict('email')
                ->doUpdate(['name' => 'Name ' . $round])
                ->sql($dialect);

            $pdo->prepare($prepared->sql())->execute($prepared->bindings());
        }

        $rows = $this->db->getAllRows($pdo, 'users');
        $this->assertCount(1, $rows);
        $this->assertSame('Name 2', $rows[0]['name']);
    }

    #[DataProvider('dialects')]
    public function testDoNothingKeepsTheFirstRowInEveryDialect(string $dialect): void
    {
        $pdo = $this->prepareTable($dialect);

        foreach (['first', 'second'] as $name) {
            $prepared = (new DbInsert())
                ->into('users')
                ->set(['name' => $name, 'email' => 'c@example.com'])
                ->onConflict('email')
                ->doNothing()
                ->sql($dialect);

            $pdo->prepare($prepared->sql())->execute($prepared->bindings());
        }

        $rows = $this->db->getAllRows($pdo, 'users');
        $this->assertCount(1, $rows);
        $this->assertSame('first', $rows[0]['name']);
    }

    #[DataProvider('dialects')]
    public function testUpsertWithoutConflictTargetIsALoudErrorWhereTheDialectNeedsOne(string $dialect): void
    {
        $insert = (new DbInsert())
            ->into('users')
            ->set(['name' => 'x', 'email' => 'd@example.com'])
            ->onDuplicateKeyUpdate('name', 'y');

        if ($dialect === 'postgres' || $dialect === 'sqlite') {
            $this->expectException(InvalidArgumentException::class);
        }

        $sql = $insert->sql($dialect)->sql();

        // MySQL / MariaDB choose the conflicting unique key themselves
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
    }

    /** @return array<string, array{string, string, list<string>}> */
    public static function literalSearches(): array
    {
        $cases = [
            'percent' => ['50%', ['50%']],
            'underscore' => ['a_b', ['a_b']],
            'backslash' => ['a\\b', ['a\\b']],
            'escape char' => ['a!b', ['a!b']],
            'plain' => ['abc', ['abc']],
        ];

        $out = [];
        foreach (['mysql', 'mariadb', 'postgres', 'sqlite'] as $dialect) {
            foreach ($cases as $label => [$term, $expected]) {
                $out[$dialect . ' ' . $label] = [$dialect, $term, $expected];
            }
        }

        return $out;
    }

    /** @param list<string> $expected */
    #[DataProvider('literalSearches')]
    public function testContainsFindsOnlyTheLiteralSubstring(string $dialect, string $term, array $expected): void
    {
        $pdo = $this->prepareTable($dialect);
        $this->seedNames($pdo, ['50%', '5000', 'a_b', 'axb', 'a\\b', 'a!b', 'abc']);

        $found = $this->names($pdo, $dialect, (new DbQuery())
            ->select('name')->from('users')
            ->where('name')->like(LikePattern::contains($term)));

        $this->assertSame($expected, $found);
    }

    #[DataProvider('dialects')]
    public function testStartsWithEndsWithLiteralAndNotLike(string $dialect): void
    {
        $pdo = $this->prepareTable($dialect);
        $this->seedNames($pdo, ['50%', '5000', 'a_b', 'axb']);

        $this->assertSame(['50%'], $this->names($pdo, $dialect, (new DbQuery())->select('name')->from('users')
            ->where('name')->like(LikePattern::startsWith('50%'))));
        $this->assertSame(['a_b'], $this->names($pdo, $dialect, (new DbQuery())->select('name')->from('users')
            ->where('name')->like(LikePattern::endsWith('_b'))));
        $this->assertSame(['a_b'], $this->names($pdo, $dialect, (new DbQuery())->select('name')->from('users')
            ->where('name')->like(LikePattern::literal('a_b'))));
        $this->assertSame(['5000', 'a_b', 'axb'], $this->names($pdo, $dialect, (new DbQuery())
            ->select('name')->from('users')
            ->where('name')->notLike(LikePattern::contains('50%'))));
    }

    #[DataProvider('dialects')]
    public function testAnyLikePatternInterfaceImplementationSearchesLiterally(string $dialect): void
    {
        $pdo = $this->prepareTable($dialect);
        $this->seedNames($pdo, ['50%', '5000', 'a_b', 'axb']);
        $foreign = new class implements LikePatternInterface {
            public function pattern(): string
            {
                return '50#%%';
            }

            public function escapeClause(): string
            {
                return " ESCAPE '#'";
            }
        };

        $this->assertSame(['50%'], $this->names($pdo, $dialect, (new DbQuery())
            ->select('name')->from('users')
            ->where('name')->like($foreign)));
    }
}
