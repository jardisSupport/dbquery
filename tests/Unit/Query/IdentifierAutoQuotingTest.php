<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query;

use JardisSupport\DbQuery\Data\Expression;
use JardisSupport\DbQuery\DbDelete;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\DbUpdate;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Auto-Quoting of SIMPLE identifiers (2026-08-10)
 *
 * Simple identifiers (`ident` or `alias.ident`) passed to WHERE/AND/OR,
 * HAVING, ORDER BY, GROUP BY and the SELECT field list are quoted with the
 * dialect's identifier quoting (MySQL/MariaDB/SQLite: backtick,
 * PostgreSQL: double quote). Everything else - functions, operators, '*',
 * already quoted strings, Expression::raw() - stays byte-identical raw.
 *
 * Motivation: camelCase columns (e.g. "createdAt") created with quoted DDL
 * failed on PostgreSQL (42703) when referenced unquoted in WHERE while the
 * same column arrived quoted in INSERT.
 */
class IdentifierAutoQuotingTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function dialects(): array
    {
        return [
            'mysql' => ['mysql', '`'],
            'postgres' => ['postgres', '"'],
            'sqlite' => ['sqlite', '`'],
        ];
    }

    private function q(string $quoteChar, string $identifier): string
    {
        return $quoteChar . $identifier . $quoteChar;
    }

    // ==================== camelCase over all clauses, all drivers ====================

    #[DataProvider('dialects')]
    public function testCamelCaseWhereIsQuoted(string $dialect, string $qc): void
    {
        $prepared = (new DbQuery())
            ->select('*')
            ->from('orders')
            ->where('createdAt')->greater('2024-01-01')
            ->sql($dialect);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $prepared);
        $this->assertStringContainsString(
            'WHERE ' . $this->q($qc, 'createdAt') . ' > ?',
            $prepared->sql()
        );
    }

    #[DataProvider('dialects')]
    public function testCamelCaseAndOrAreQuoted(string $dialect, string $qc): void
    {
        $prepared = (new DbQuery())
            ->select('*')
            ->from('orders')
            ->where('buyerName')->equals('X')
            ->and('createdAt')->greater('2024-01-01')
            ->or('updatedAt')->isNull()
            ->sql($dialect);

        $sql = $prepared->sql();
        $this->assertStringContainsString('WHERE ' . $this->q($qc, 'buyerName') . ' = ?', $sql);
        $this->assertStringContainsString('AND ' . $this->q($qc, 'createdAt') . ' > ?', $sql);
        $this->assertStringContainsString('OR ' . $this->q($qc, 'updatedAt') . ' IS NULL', $sql);
    }

    #[DataProvider('dialects')]
    public function testCamelCaseOrderByIsQuoted(string $dialect, string $qc): void
    {
        $prepared = (new DbQuery())
            ->select('*')
            ->from('orders')
            ->orderBy('createdAt', 'DESC')
            ->sql($dialect);

        $this->assertStringContainsString(
            'ORDER BY ' . $this->q($qc, 'createdAt') . ' DESC',
            $prepared->sql()
        );
    }

    #[DataProvider('dialects')]
    public function testCamelCaseGroupByIsQuoted(string $dialect, string $qc): void
    {
        $prepared = (new DbQuery())
            ->select('buyerName, COUNT(*) AS orderCount')
            ->from('orders')
            ->groupBy('buyerName')
            ->sql($dialect);

        $sql = $prepared->sql();
        $this->assertStringContainsString('GROUP BY ' . $this->q($qc, 'buyerName'), $sql);
        // SELECT list: simple identifier quoted, function raw, alias quoted
        $this->assertStringContainsString(
            'SELECT ' . $this->q($qc, 'buyerName') . ', COUNT(*) AS ' . $this->q($qc, 'orderCount'),
            $sql
        );
    }

    #[DataProvider('dialects')]
    public function testCamelCaseSelectFieldListIsQuoted(string $dialect, string $qc): void
    {
        $prepared = (new DbQuery())
            ->select('id, buyerName, createdAt')
            ->from('orders')
            ->sql($dialect);

        $this->assertStringContainsString(
            'SELECT ' . $this->q($qc, 'id') . ', ' . $this->q($qc, 'buyerName')
            . ', ' . $this->q($qc, 'createdAt'),
            $prepared->sql()
        );
    }

    #[DataProvider('dialects')]
    public function testCamelCaseHavingIsQuoted(string $dialect, string $qc): void
    {
        $prepared = (new DbQuery())
            ->select('buyerName, COUNT(*) AS orderCount')
            ->from('orders')
            ->groupBy('buyerName')
            ->having('orderCount')->greater(5)
            ->sql($dialect);

        $this->assertStringContainsString(
            'HAVING ' . $this->q($qc, 'orderCount') . ' > ?',
            $prepared->sql()
        );
    }

    #[DataProvider('dialects')]
    public function testCamelCaseUpdateWhereAndOrderByAreQuoted(string $dialect, string $qc): void
    {
        $update = (new DbUpdate())
            ->table('orders')
            ->set('buyerName', 'X')
            ->where('createdAt')->greater('2024-01-01');

        if ($dialect === 'mysql') {
            // ORDER BY in UPDATE is MySQL-only
            $update->orderBy('createdAt', 'ASC')->limit(10);
        }

        $prepared = $update->sql($dialect);
        $sql = $prepared->sql();

        $this->assertStringContainsString('WHERE ' . $this->q($qc, 'createdAt') . ' > ?', $sql);
        if ($dialect === 'mysql') {
            $this->assertStringContainsString('ORDER BY ' . $this->q($qc, 'createdAt') . ' ASC', $sql);
        }
    }

    #[DataProvider('dialects')]
    public function testCamelCaseDeleteWhereIsQuoted(string $dialect, string $qc): void
    {
        $prepared = (new DbDelete())
            ->from('orders')
            ->where('createdAt')->lower('2020-01-01')
            ->sql($dialect);

        $this->assertStringContainsString(
            'WHERE ' . $this->q($qc, 'createdAt') . ' < ?',
            $prepared->sql()
        );
    }

    // ==================== alias.ident ====================

    #[DataProvider('dialects')]
    public function testAliasDotIdentIsQuotedPerPart(string $dialect, string $qc): void
    {
        $prepared = (new DbQuery())
            ->select('*')
            ->from('orders', 'o')
            ->where('o.createdAt')->greater('2024-01-01')
            ->orderBy('o.createdAt')
            ->sql($dialect);

        $sql = $prepared->sql();
        $qualified = $this->q($qc, 'o') . '.' . $this->q($qc, 'createdAt');
        $this->assertStringContainsString('WHERE ' . $qualified . ' > ?', $sql);
        $this->assertStringContainsString('ORDER BY ' . $qualified . ' ASC', $sql);
    }

    // ==================== expression regression: everything else stays raw ====================

    #[DataProvider('dialects')]
    public function testFunctionExpressionInWhereStaysRaw(string $dialect, string $qc): void
    {
        $prepared = (new DbQuery())
            ->select('*')
            ->from('orders')
            ->where('YEAR(created)')->equals(2024)
            ->sql($dialect);

        $this->assertStringContainsString('WHERE YEAR(created) = ?', $prepared->sql());
    }

    #[DataProvider('dialects')]
    public function testExpressionRawFieldStaysRawEvenWhenSimple(string $dialect, string $qc): void
    {
        // Expression::raw is the escape hatch: even a simple identifier
        // must stay byte-identical raw.
        $prepared = (new DbQuery())
            ->select('*')
            ->from('orders')
            ->where(Expression::raw('createdAt'))->greater('2024-01-01')
            ->sql($dialect);

        $this->assertStringContainsString('WHERE createdAt > ?', $prepared->sql());
    }

    #[DataProvider('dialects')]
    public function testAlreadyQuotedFieldStaysRaw(string $dialect, string $qc): void
    {
        $field = $qc . 'createdAt' . $qc;
        $prepared = (new DbQuery())
            ->select('*')
            ->from('orders')
            ->where($field)->greater('2024-01-01')
            ->sql($dialect);

        $this->assertStringContainsString('WHERE ' . $field . ' > ?', $prepared->sql());
    }

    #[DataProvider('dialects')]
    public function testStarSelectStaysRaw(string $dialect, string $qc): void
    {
        $prepared = (new DbQuery())
            ->select('*')
            ->from('orders')
            ->sql($dialect);

        $this->assertStringContainsString('SELECT *', $prepared->sql());
    }

    #[DataProvider('dialects')]
    public function testOrderByExpressionStaysRaw(string $dialect, string $qc): void
    {
        $prepared = (new DbQuery())
            ->select('*')
            ->from('orders')
            ->orderBy('YEAR(created)', 'DESC')
            ->sql($dialect);

        $this->assertStringContainsString('ORDER BY YEAR(created) DESC', $prepared->sql());
    }

    #[DataProvider('dialects')]
    public function testGroupByExpressionStaysRaw(string $dialect, string $qc): void
    {
        $prepared = (new DbQuery())
            ->select('*')
            ->from('orders')
            ->groupBy('YEAR(created)')
            ->sql($dialect);

        $this->assertStringContainsString('GROUP BY YEAR(created)', $prepared->sql());
    }

    #[DataProvider('dialects')]
    public function testHavingExpressionStaysRaw(string $dialect, string $qc): void
    {
        $prepared = (new DbQuery())
            ->select('dept, COUNT(*) AS cnt')
            ->from('emp')
            ->groupBy('dept')
            ->having('COUNT(*)')->greater(5)
            ->sql($dialect);

        $this->assertStringContainsString('HAVING COUNT(*) > ?', $prepared->sql());
    }

    // ==================== keyword exceptions ====================

    #[DataProvider('dialects')]
    public function testUnionPaddingNullLiteralStaysRaw(string $dialect, string $qc): void
    {
        // UNION padding: NULL is an SQL literal, not a column - must stay raw
        // even though it matches the identifier pattern.
        $left = (new DbQuery())
            ->select('id, NULL AS email')
            ->from('guests');
        $prepared = $left
            ->unionAll(
                (new DbQuery())->select('id, email')->from('users')
            )
            ->sql($dialect);

        $sql = $prepared->sql();
        $this->assertStringContainsString(
            'SELECT ' . $this->q($qc, 'id') . ', NULL AS ' . $this->q($qc, 'email'),
            $sql
        );
        $this->assertStringNotContainsString($this->q($qc, 'NULL'), $sql);
    }

    #[DataProvider('dialects')]
    public function testKeywordLiteralsStayRawInSelectAndWhere(string $dialect, string $qc): void
    {
        $prepared = (new DbQuery())
            ->select('id, CURRENT_TIMESTAMP AS fetchedAt, TRUE AS isActive')
            ->from('orders')
            ->where('createdAt')->lower(Expression::raw('CURRENT_DATE'))
            ->sql($dialect);

        $sql = $prepared->sql();
        $this->assertStringContainsString('CURRENT_TIMESTAMP AS ' . $this->q($qc, 'fetchedAt'), $sql);
        $this->assertStringContainsString('TRUE AS ' . $this->q($qc, 'isActive'), $sql);
        $this->assertStringContainsString('WHERE ' . $this->q($qc, 'createdAt') . ' < CURRENT_DATE', $sql);
    }

    #[DataProvider('dialects')]
    public function testColumnLiterallyNamedNullStaysRawWithQuotedEscapeHatch(string $dialect, string $qc): void
    {
        // A column literally named "null" is NOT auto-quoted (keyword
        // exception, case-insensitive) - it stays raw ...
        $prepared = (new DbQuery())
            ->select('*')
            ->from('t')
            ->where('null')->equals(1)
            ->sql($dialect);
        $this->assertStringContainsString('WHERE null = ?', $prepared->sql());

        // ... the escape hatch is passing it pre-quoted (stays byte-identical).
        $preQuoted = $qc . 'null' . $qc;
        $prepared = (new DbQuery())
            ->select('*')
            ->from('t')
            ->where($preQuoted)->equals(1)
            ->sql($dialect);
        $this->assertStringContainsString('WHERE ' . $preQuoted . ' = ?', $prepared->sql());
    }

    #[DataProvider('dialects')]
    public function testQualifiedKeywordNameIsStillQuoted(string $dialect, string $qc): void
    {
        // Qualified names are always identifiers - `t.null` is a column.
        $prepared = (new DbQuery())
            ->select('*')
            ->from('t')
            ->where('t.null')->equals(1)
            ->sql($dialect);

        $this->assertStringContainsString(
            'WHERE ' . $this->q($qc, 't') . '.' . $this->q($qc, 'null') . ' = ?',
            $prepared->sql()
        );
    }

    #[DataProvider('dialects')]
    public function testEmptyInWithEmbeddedBracketFieldKeepsBrackets(string $dialect, string $qc): void
    {
        // Field with embedded opening bracket is not a simple identifier;
        // the bracket-preserving 1=0 replacement must keep working.
        $prepared = (new DbQuery())
            ->select('*')
            ->from('orders')
            ->where('status')->equals('open')
            ->and('(id')->in([], ')')
            ->sql($dialect);

        $this->assertStringContainsString('AND (1=0)', $prepared->sql());
    }
}
