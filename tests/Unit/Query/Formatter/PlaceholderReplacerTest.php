<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Formatter;

use JardisSupport\DbQuery\Query\Formatter\PlaceholderReplacer;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * Tests for PlaceholderReplacer class
 *
 * Coverage:
 * - replaceAll(): Replace all ? placeholders with formatted values
 * - replaceSubqueries(): Replace subquery placeholders with actual SQL
 * - Error handling for missing bindings
 * - Callback-based value formatting
 * - Subquery detection and binding merging
 */
class PlaceholderReplacerTest extends TestCase
{
    private PlaceholderReplacer $replacer;

    protected function setUp(): void
    {
        $this->replacer = new PlaceholderReplacer();
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceAll_withSinglePlaceholder_replacesCorrectly(): void
    {
        $sql = 'SELECT * FROM users WHERE id = ?';
        $bindings = [42];
        $formatValue = fn($value) => (string)$value;

        $result = $this->replacer->replaceAll($sql, $bindings, $formatValue);

        $this->assertSame('SELECT * FROM users WHERE id = 42', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceAll_withMultiplePlaceholders_replacesInOrder(): void
    {
        $sql = 'SELECT * FROM users WHERE id = ? AND name = ? AND age > ?';
        $bindings = [42, 'John', 25];
        $formatValue = fn($value) => is_string($value) ? "'$value'" : (string)$value;

        $result = $this->replacer->replaceAll($sql, $bindings, $formatValue);

        $this->assertSame("SELECT * FROM users WHERE id = 42 AND name = 'John' AND age > 25", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceAll_withNoPlaceholders_returnsUnchanged(): void
    {
        $sql = 'SELECT * FROM users';
        $bindings = [];
        $formatValue = fn($value) => (string)$value;

        $result = $this->replacer->replaceAll($sql, $bindings, $formatValue);

        $this->assertSame('SELECT * FROM users', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceAll_withMissingBinding_throwsException(): void
    {
        $sql = 'SELECT * FROM users WHERE id = ? AND name = ?';
        $bindings = [42];  // Missing second binding
        $formatValue = fn($value) => (string)$value;

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Binding at position 1 not found (total bindings: 1)');

        $this->replacer->replaceAll($sql, $bindings, $formatValue);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceAll_withNullValue_formatsAsNull(): void
    {
        $sql = 'SELECT * FROM users WHERE deleted_at = ?';
        $bindings = [null];
        $formatValue = fn($value) => $value === null ? 'NULL' : (string)$value;

        $result = $this->replacer->replaceAll($sql, $bindings, $formatValue);

        $this->assertSame('SELECT * FROM users WHERE deleted_at = NULL', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceAll_withComplexFormatting_appliesCorrectly(): void
    {
        $sql = 'INSERT INTO users (name, email, active) VALUES (?, ?, ?)';
        $bindings = ['John', 'john@example.com', true];

        // Complex formatter that handles strings and booleans
        $formatValue = function ($value) {
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }
            if (is_string($value)) {
                return "'" . addslashes($value) . "'";
            }
            return (string)$value;
        };

        $result = $this->replacer->replaceAll($sql, $bindings, $formatValue);

        $this->assertSame("INSERT INTO users (name, email, active) VALUES ('John', 'john@example.com', 1)", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceAll_withExtraBindings_usesOnlyNeeded(): void
    {
        $sql = 'SELECT * FROM users WHERE id = ?';
        $bindings = [42, 'extra', 'bindings'];  // Extra bindings
        $formatValue = fn($value) => (string)$value;

        $result = $this->replacer->replaceAll($sql, $bindings, $formatValue);

        $this->assertSame('SELECT * FROM users WHERE id = 42', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceSubqueries_withNoSubqueries_keepsPlaceholders(): void
    {
        $condition = 'id = ? AND name = ?';
        $bindings = [42, 'John'];

        $result = $this->replacer->replaceSubqueries($condition, $bindings, 'mysql');

        $this->assertSame('id = ? AND name = ?', $result);
        $this->assertSame([42, 'John'], $bindings);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceSubqueries_withSingleSubquery_replacesWithSql(): void
    {
        $condition = 'id IN (?)';

        // Mock subquery
        $subquery = $this->createMock(DbQueryBuilderInterface::class);
        $preparedResult = $this->createMock(DbPreparedQueryInterface::class);
        $preparedResult->method('sql')->willReturn('SELECT id FROM active_users');
        $preparedResult->method('bindings')->willReturn([]);

        $subquery->method('sql')->with('mysql', true)->willReturn($preparedResult);

        $bindings = [$subquery];

        $result = $this->replacer->replaceSubqueries($condition, $bindings, 'mysql');

        $this->assertSame('id IN ((SELECT id FROM active_users))', $result);
        $this->assertSame([], $bindings);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceSubqueries_withSubqueryAndBindings_mergesBindings(): void
    {
        $condition = 'id IN (?) AND status = ?';

        // Mock subquery with bindings
        $subquery = $this->createMock(DbQueryBuilderInterface::class);
        $preparedResult = $this->createMock(DbPreparedQueryInterface::class);
        $preparedResult->method('sql')->willReturn('SELECT id FROM users WHERE age > ?');
        $preparedResult->method('bindings')->willReturn([18]);

        $subquery->method('sql')->with('postgres', true)->willReturn($preparedResult);

        $bindings = [$subquery, 'active'];

        $result = $this->replacer->replaceSubqueries($condition, $bindings, 'postgres');

        $this->assertSame('id IN ((SELECT id FROM users WHERE age > ?)) AND status = ?', $result);
        $this->assertSame([18, 'active'], $bindings);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceSubqueries_withMultipleSubqueries_replacesAllAndMergesBindings(): void
    {
        $condition = 'id IN (?) AND manager_id IN (?)';

        // First subquery
        $subquery1 = $this->createMock(DbQueryBuilderInterface::class);
        $preparedResult1 = $this->createMock(DbPreparedQueryInterface::class);
        $preparedResult1->method('sql')->willReturn('SELECT id FROM users WHERE active = ?');
        $preparedResult1->method('bindings')->willReturn([true]);

        $subquery1->method('sql')->with('sqlite', true)->willReturn($preparedResult1);

        // Second subquery
        $subquery2 = $this->createMock(DbQueryBuilderInterface::class);
        $preparedResult2 = $this->createMock(DbPreparedQueryInterface::class);
        $preparedResult2->method('sql')->willReturn('SELECT id FROM managers WHERE dept = ?');
        $preparedResult2->method('bindings')->willReturn(['IT']);

        $subquery2->method('sql')->with('sqlite', true)->willReturn($preparedResult2);

        $bindings = [$subquery1, $subquery2];

        $result = $this->replacer->replaceSubqueries($condition, $bindings, 'sqlite');

        $this->assertSame('id IN ((SELECT id FROM users WHERE active = ?)) AND manager_id IN ((SELECT id FROM managers WHERE dept = ?))', $result);
        $this->assertSame([true, 'IT'], $bindings);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceSubqueries_withMixedValues_handlesCorrectly(): void
    {
        $condition = 'name = ? AND id IN (?) AND age > ?';

        // Mock subquery
        $subquery = $this->createMock(DbQueryBuilderInterface::class);
        $preparedResult = $this->createMock(DbPreparedQueryInterface::class);
        $preparedResult->method('sql')->willReturn('SELECT id FROM active_users WHERE status = ?');
        $preparedResult->method('bindings')->willReturn(['confirmed']);

        $subquery->method('sql')->with('mysql', true)->willReturn($preparedResult);

        $bindings = ['John', $subquery, 25];

        $result = $this->replacer->replaceSubqueries($condition, $bindings, 'mysql');

        $this->assertSame('name = ? AND id IN ((SELECT id FROM active_users WHERE status = ?)) AND age > ?', $result);
        $this->assertSame(['John', 'confirmed', 25], $bindings);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceSubqueries_withEmptySubqueryBindings_mergesCorrectly(): void
    {
        $condition = 'id IN (?) AND name = ?';

        // Mock subquery with no bindings
        $subquery = $this->createMock(DbQueryBuilderInterface::class);
        $preparedResult = $this->createMock(DbPreparedQueryInterface::class);
        $preparedResult->method('sql')->willReturn('SELECT id FROM active_users');
        $preparedResult->method('bindings')->willReturn([]);

        $subquery->method('sql')->with('postgres', true)->willReturn($preparedResult);

        $bindings = [$subquery, 'John'];

        $result = $this->replacer->replaceSubqueries($condition, $bindings, 'postgres');

        $this->assertSame('id IN ((SELECT id FROM active_users)) AND name = ?', $result);
        $this->assertSame(['John'], $bindings);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceSubqueries_withFewerBindingsThanPlaceholders_keepsExtraPlaceholders(): void
    {
        $condition = 'id = ? AND name = ?';
        $bindings = [42];  // Only one binding

        $result = $this->replacer->replaceSubqueries($condition, $bindings, 'mysql');

        $this->assertSame('id = ? AND name = ?', $result);
        $this->assertSame([42], $bindings);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceSubqueries_withNestedSubqueryBindings_mergesCorrectly(): void
    {
        $condition = 'id IN (?)';

        // Mock subquery with multiple bindings
        $subquery = $this->createMock(DbQueryBuilderInterface::class);
        $preparedResult = $this->createMock(DbPreparedQueryInterface::class);
        $preparedResult->method('sql')->willReturn('SELECT id FROM users WHERE age BETWEEN ? AND ?');
        $preparedResult->method('bindings')->willReturn([18, 65]);

        $subquery->method('sql')->with('sqlite', true)->willReturn($preparedResult);

        $bindings = [$subquery];

        $result = $this->replacer->replaceSubqueries($condition, $bindings, 'sqlite');

        $this->assertSame('id IN ((SELECT id FROM users WHERE age BETWEEN ? AND ?))', $result);
        $this->assertSame([18, 65], $bindings);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceAll_withMultipleNullValues_formatsCorrectly(): void
    {
        $sql = 'INSERT INTO users (name, email) VALUES (?, ?)';
        $bindings = [null, null];
        $formatValue = fn($value) => $value === null ? 'NULL' : "'" . $value . "'";

        $result = $this->replacer->replaceAll($sql, $bindings, $formatValue);

        $this->assertSame('INSERT INTO users (name, email) VALUES (NULL, NULL)', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceSubqueries_withNullBinding_keepsPlaceholder(): void
    {
        $condition = 'deleted_at = ? AND name = ?';
        $bindings = [null, 'John'];

        $result = $this->replacer->replaceSubqueries($condition, $bindings, 'mysql');

        $this->assertSame('deleted_at = ? AND name = ?', $result);
        $this->assertSame([null, 'John'], $bindings);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceAll_withMultiplePlaceholdersInComplexQuery_replacesAll(): void
    {
        $sql = "SELECT * FROM users WHERE name LIKE ? AND age > ? AND city = ?";
        $bindings = ['John%', 25, 'Berlin'];
        $formatValue = fn($value) => is_string($value) ? "'$value'" : (string)$value;

        $result = $this->replacer->replaceAll($sql, $bindings, $formatValue);

        $this->assertSame("SELECT * FROM users WHERE name LIKE 'John%' AND age > 25 AND city = 'Berlin'", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function replaceAll_withSpecialCharactersInFormattedValue_handlesCorrectly(): void
    {
        $sql = 'SELECT * FROM users WHERE comment = ?';
        $bindings = ["O'Reilly & Sons"];
        $formatValue = fn($value) => "'" . addslashes($value) . "'";

        $result = $this->replacer->replaceAll($sql, $bindings, $formatValue);

        $this->assertSame("SELECT * FROM users WHERE comment = 'O\\'Reilly & Sons'", $result);
    }
}
