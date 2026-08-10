<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Postgres;

use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbWindowBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * PostgreSQL Window Function Tests
 *
 * Tests: Window functions (ROW_NUMBER, RANK, SUM, etc.), Named windows, PARTITION BY, ORDER BY, Frames, GROUPS
 */
/*
 * SQL-Pins am 2026-08-10 an das Auto-Quoting einfacher Identifier angepasst:
 * WHERE/AND/OR-, HAVING-, ORDER-BY-, GROUP-BY-Felder und die SELECT-Feldliste
 * quoten `ident` bzw. `alias.ident` jetzt dialektgerecht (MySQL/SQLite: Backtick,
 * PostgreSQL: Double-Quote). Ausdruecke, '*', bereits Gequotetes und
 * Expression::raw() bleiben byte-identisch roh. Alle Aenderungen in dieser
 * Datei sind reine Quote-Zeichen-Diffs in erwarteten SQL-Strings.
 */
class DbQueryPostgresWindowTest extends TestCase
{
    public function testSelectWindowWithRowNumber(): void
    {
        $query = new DbQuery();
        $result = $query->select('id, name, salary')
            ->selectWindow('ROW_NUMBER', 'row_num')
                ->partitionBy('department')
                ->windowOrderBy('salary', 'DESC')
                ->endWindow()
            ->from('employees');

        $this->assertInstanceOf(DbQuery::class, $result);

        $sql = $query->sql('postgres', false);
        $expected = 'SELECT "id", "name", "salary", ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC) AS "row_num" FROM "employees"';
        $this->assertEquals($expected, $sql);
    }

    public function testSelectWindowWithRank(): void
    {
        $query = new DbQuery();
        $sql = $query->select('id, score')
            ->selectWindow('RANK', 'rank')
                ->windowOrderBy('score', 'DESC')
                ->endWindow()
            ->from('players')
            ->sql('postgres', false);

        $expected = 'SELECT "id", "score", RANK() OVER (ORDER BY score DESC) AS "rank" FROM "players"';
        $this->assertEquals($expected, $sql);
    }

    public function testSelectWindowWithSumAndArgs(): void
    {
        $query = new DbQuery();
        $sql = $query->select('date, amount')
            ->selectWindow('SUM', 'running_total', 'amount')
                ->windowOrderBy('date')
                ->endWindow()
            ->from('transactions')
            ->sql('postgres', false);

        $expected = 'SELECT "date", "amount", SUM(amount) OVER (ORDER BY date ASC) AS "running_total" FROM "transactions"';
        $this->assertEquals($expected, $sql);
    }

    public function testSelectWindowWithLead(): void
    {
        $query = new DbQuery();
        $sql = $query->select('date, price')
            ->selectWindow('LEAD', 'next_price', 'price, 1')
                ->windowOrderBy('date')
                ->endWindow()
            ->from('stock_prices')
            ->sql('postgres', false);

        $expected = 'SELECT "date", "price", LEAD(price, 1) OVER (ORDER BY date ASC) AS "next_price" FROM "stock_prices"';
        $this->assertEquals($expected, $sql);
    }

    public function testSelectWindowWithMultiplePartitions(): void
    {
        $query = new DbQuery();
        $sql = $query->select('id, name')
            ->selectWindow('ROW_NUMBER', 'row_num')
                ->partitionBy('department', 'location')
                ->windowOrderBy('hire_date', 'ASC')
                ->endWindow()
            ->from('employees')
            ->sql('postgres', false);

        $expected = 'SELECT "id", "name", ROW_NUMBER() OVER (PARTITION BY department, location ORDER BY hire_date ASC) AS "row_num" FROM "employees"';
        $this->assertEquals($expected, $sql);
    }

    public function testSelectWindowWithFrame(): void
    {
        $query = new DbQuery();
        $sql = $query->select('date, amount')
            ->selectWindow('AVG', 'moving_avg', 'amount')
                ->windowOrderBy('date')
                ->frame('ROWS', '2 PRECEDING', 'CURRENT ROW')
                ->endWindow()
            ->from('sales')
            ->sql('postgres', false);

        $expected = 'SELECT "date", "amount", AVG(amount) OVER (ORDER BY date ASC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS "moving_avg" FROM "sales"';
        $this->assertEquals($expected, $sql);
    }

    public function testSelectWindowWithGroupsFrame(): void
    {
        $query = new DbQuery();
        $sql = $query->select('id, value')
            ->selectWindow('SUM', 'total', 'value')
                ->windowOrderBy('id')
                ->frame('GROUPS', 'UNBOUNDED PRECEDING', 'CURRENT ROW')
                ->endWindow()
            ->from('data')
            ->sql('postgres', false);

        $expected = 'SELECT "id", "value", SUM(value) OVER (ORDER BY id ASC GROUPS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS "total" FROM "data"';
        $this->assertEquals($expected, $sql);
    }

    public function testMultipleWindowFunctions(): void
    {
        $query = new DbQuery();
        $sql = $query->select('id, name, salary')
            ->selectWindow('ROW_NUMBER', 'row_num')
                ->partitionBy('department')
                ->windowOrderBy('salary', 'DESC')
                ->endWindow()
            ->selectWindow('DENSE_RANK', 'dense_rank')
                ->partitionBy('department')
                ->windowOrderBy('salary', 'DESC')
                ->endWindow()
            ->from('employees')
            ->sql('postgres', false);

        $expected = 'SELECT "id", "name", "salary", ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC) AS "row_num", DENSE_RANK() OVER (PARTITION BY department ORDER BY salary DESC) AS "dense_rank" FROM "employees"';
        $this->assertEquals($expected, $sql);
    }

    public function testNamedWindow(): void
    {
        $query = new DbQuery();
        $result = $query->select('id, name, salary')
            ->window('dept_window')
                ->partitionBy('department')
                ->windowOrderBy('salary', 'DESC')
                ->endWindow();

        $this->assertInstanceOf(DbQuery::class, $result);

        $sql = $query->selectWindowRef('ROW_NUMBER', 'dept_window', 'row_num')
            ->selectWindowRef('RANK', 'dept_window', 'rank')
            ->from('employees')
            ->sql('postgres', false);

        $expected = 'SELECT "id", "name", "salary", ROW_NUMBER() OVER dept_window AS "row_num", RANK() OVER dept_window AS "rank" FROM "employees" WINDOW dept_window AS (PARTITION BY department ORDER BY salary DESC)';
        $this->assertEquals($expected, $sql);
    }

    public function testNamedWindowWithArgs(): void
    {
        $query = new DbQuery();
        $sql = $query->select('timestamp, value')
            ->window('time_window')
                ->windowOrderBy('timestamp')
                ->endWindow()
            ->selectWindowRef('SUM', 'time_window', 'running_total', 'value')
            ->selectWindowRef('FIRST_VALUE', 'time_window', 'first_value', 'value')
            ->from('metrics')
            ->sql('postgres', false);

        $expected = 'SELECT "timestamp", "value", SUM(value) OVER time_window AS "running_total", FIRST_VALUE(value) OVER time_window AS "first_value" FROM "metrics" WINDOW time_window AS (ORDER BY timestamp ASC)';
        $this->assertEquals($expected, $sql);
    }

    public function testMultipleNamedWindows(): void
    {
        $query = new DbQuery();
        $sql = $query->select('id, name')
            ->window('by_dept')
                ->partitionBy('department')
                ->windowOrderBy('hire_date')
                ->endWindow()
            ->window('by_team')
                ->partitionBy('team')
                ->windowOrderBy('performance', 'DESC')
                ->endWindow()
            ->selectWindowRef('ROW_NUMBER', 'by_dept', 'dept_seniority')
            ->selectWindowRef('RANK', 'by_team', 'team_rank')
            ->from('employees')
            ->sql('postgres', false);

        $expected = 'SELECT "id", "name", ROW_NUMBER() OVER by_dept AS "dept_seniority", RANK() OVER by_team AS "team_rank" FROM "employees" WINDOW by_dept AS (PARTITION BY department ORDER BY hire_date ASC), by_team AS (PARTITION BY team ORDER BY performance DESC)';
        $this->assertEquals($expected, $sql);
    }

    public function testWindowBuilderReturnsCorrectInterface(): void
    {
        $query = new DbQuery();
        $windowBuilder = $query->selectWindow('ROW_NUMBER', 'row_num');

        $this->assertInstanceOf(DbWindowBuilderInterface::class, $windowBuilder);
    }

    public function testWindowWithWhereClause(): void
    {
        $query = new DbQuery();
        $sql = $query->select('id, name, salary')
            ->selectWindow('ROW_NUMBER', 'row_num')
                ->partitionBy('department')
                ->windowOrderBy('salary', 'DESC')
                ->endWindow()
            ->from('employees')
            ->where('active')->equals(true)
            ->sql('postgres', false);

        $expected = 'SELECT "id", "name", "salary", ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC) AS "row_num" FROM "employees" WHERE "active" = TRUE';
        $this->assertEquals($expected, $sql);
    }

    public function testWindowWithGroupByAndHaving(): void
    {
        $query = new DbQuery();
        $sql = $query->select('department, COUNT(*) as emp_count')
            ->selectWindow('RANK', 'dept_rank')
                ->windowOrderBy('COUNT(*)', 'DESC')
                ->endWindow()
            ->from('employees')
            ->groupBy('department')
            ->having('COUNT(*)')->greater(5)
            ->sql('postgres', false);

        $expected = 'SELECT "department", COUNT(*) as "emp_count", RANK() OVER (ORDER BY COUNT(*) DESC) AS "dept_rank" FROM "employees" GROUP BY "department" HAVING COUNT(*) > 5';
        $this->assertEquals($expected, $sql);
    }

    public function testWindowFunctionWithPreparedStatement(): void
    {
        $query = new DbQuery();
        $result = $query->select('id, name, salary')
            ->selectWindow('ROW_NUMBER', 'row_num')
                ->partitionBy('department')
                ->windowOrderBy('salary', 'DESC')
                ->endWindow()
            ->from('employees')
            ->where('salary')->greater(50000)
            ->sql('postgres', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $expected = 'SELECT "id", "name", "salary", ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC) AS "row_num" FROM "employees" WHERE "salary" > ?';
        $this->assertEquals($expected, $result->sql());
        $this->assertEquals([50000], $result->bindings());
    }

    public function testSelectWindowWithNthValue(): void
    {
        $query = new DbQuery();
        $sql = $query->select('id, value')
            ->selectWindow('NTH_VALUE', 'third_value', 'value, 3')
                ->windowOrderBy('id')
                ->frame('ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING')
                ->endWindow()
            ->from('data')
            ->sql('postgres', false);

        $expected = 'SELECT "id", "value", NTH_VALUE(value, 3) OVER (ORDER BY id ASC ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING) AS "third_value" FROM "data"';
        $this->assertEquals($expected, $sql);
    }

    public function testSelectWindowWithLastValue(): void
    {
        $query = new DbQuery();
        $sql = $query->select('date, amount')
            ->selectWindow('LAST_VALUE', 'last_amount', 'amount')
                ->windowOrderBy('date')
                ->frame('RANGE', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING')
                ->endWindow()
            ->from('transactions')
            ->sql('postgres', false);

        $expected = 'SELECT "date", "amount", LAST_VALUE(amount) OVER (ORDER BY date ASC RANGE BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING) AS "last_amount" FROM "transactions"';
        $this->assertEquals($expected, $sql);
    }

    public function testWindowWithCTE(): void
    {
        $subQuery = new DbQuery();
        $subQuery->select('*')->from('employees')->where('active')->equals(true);

        $query = new DbQuery();
        $sql = $query->with('active_emp', $subQuery)
            ->select('id, name')
            ->selectWindow('ROW_NUMBER', 'row_num')
                ->windowOrderBy('id')
                ->endWindow()
            ->from('active_emp')
            ->sql('postgres', false);

        $expected = 'WITH "active_emp" AS (SELECT * FROM "employees" WHERE "active" = TRUE) SELECT "id", "name", ROW_NUMBER() OVER (ORDER BY id ASC) AS "row_num" FROM "active_emp"';
        $this->assertEquals($expected, $sql);
    }
}
