<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Sqlite;

use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbWindowBuilderInterface;
use PHPUnit\Framework\TestCase;

/**
 * SQLite Window Function Tests
 *
 * Tests: Window functions (ROW_NUMBER, RANK, SUM, etc.), Named windows, PARTITION BY, ORDER BY, Frames
 * Note: SQLite 3.25.0+ required for window functions
 */
/*
 * SQL-Pins am 2026-08-10 an das Auto-Quoting einfacher Identifier angepasst:
 * WHERE/AND/OR-, HAVING-, ORDER-BY-, GROUP-BY-Felder und die SELECT-Feldliste
 * quoten `ident` bzw. `alias.ident` jetzt dialektgerecht (MySQL/SQLite: Backtick,
 * PostgreSQL: Double-Quote). Ausdruecke, '*', bereits Gequotetes und
 * Expression::raw() bleiben byte-identisch roh. Alle Aenderungen in dieser
 * Datei sind reine Quote-Zeichen-Diffs in erwarteten SQL-Strings.
 */
class DbQuerySqliteWindowTest extends TestCase
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

        $sql = $query->sql('sqlite', false);
        $expected = 'SELECT `id`, `name`, `salary`, ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC) AS `row_num` FROM `employees`';
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
            ->sql('sqlite', false);

        $expected = 'SELECT `id`, `score`, RANK() OVER (ORDER BY score DESC) AS `rank` FROM `players`';
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
            ->sql('sqlite', false);

        $expected = 'SELECT `date`, `amount`, SUM(amount) OVER (ORDER BY date ASC) AS `running_total` FROM `transactions`';
        $this->assertEquals($expected, $sql);
    }

    public function testSelectWindowWithLag(): void
    {
        $query = new DbQuery();
        $sql = $query->select('date, price')
            ->selectWindow('LAG', 'prev_price', 'price, 1')
                ->windowOrderBy('date')
                ->endWindow()
            ->from('stock_prices')
            ->sql('sqlite', false);

        $expected = 'SELECT `date`, `price`, LAG(price, 1) OVER (ORDER BY date ASC) AS `prev_price` FROM `stock_prices`';
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
            ->sql('sqlite', false);

        $expected = 'SELECT `id`, `name`, ROW_NUMBER() OVER (PARTITION BY department, location ORDER BY hire_date ASC) AS `row_num` FROM `employees`';
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
            ->sql('sqlite', false);

        $expected = 'SELECT `date`, `amount`, AVG(amount) OVER (ORDER BY date ASC ROWS BETWEEN 2 PRECEDING AND CURRENT ROW) AS `moving_avg` FROM `sales`';
        $this->assertEquals($expected, $sql);
    }

    public function testSelectWindowWithRangeFrame(): void
    {
        $query = new DbQuery();
        $sql = $query->select('id, value')
            ->selectWindow('SUM', 'total', 'value')
                ->windowOrderBy('id')
                ->frame('RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW')
                ->endWindow()
            ->from('data')
            ->sql('sqlite', false);

        $expected = 'SELECT `id`, `value`, SUM(value) OVER (ORDER BY id ASC RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) AS `total` FROM `data`';
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
            ->selectWindow('RANK', 'rank')
                ->partitionBy('department')
                ->windowOrderBy('salary', 'DESC')
                ->endWindow()
            ->from('employees')
            ->sql('sqlite', false);

        $expected = 'SELECT `id`, `name`, `salary`, ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC) AS `row_num`, RANK() OVER (PARTITION BY department ORDER BY salary DESC) AS `rank` FROM `employees`';
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
            ->sql('sqlite', false);

        $expected = 'SELECT `id`, `name`, `salary`, ROW_NUMBER() OVER dept_window AS `row_num`, RANK() OVER dept_window AS `rank` FROM `employees` WINDOW dept_window AS (PARTITION BY department ORDER BY salary DESC)';
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
            ->selectWindowRef('LAG', 'time_window', 'prev_value', 'value, 1')
            ->from('metrics')
            ->sql('sqlite', false);

        $expected = 'SELECT `timestamp`, `value`, SUM(value) OVER time_window AS `running_total`, LAG(value, 1) OVER time_window AS `prev_value` FROM `metrics` WINDOW time_window AS (ORDER BY timestamp ASC)';
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
            ->sql('sqlite', false);

        $expected = 'SELECT `id`, `name`, ROW_NUMBER() OVER by_dept AS `dept_seniority`, RANK() OVER by_team AS `team_rank` FROM `employees` WINDOW by_dept AS (PARTITION BY department ORDER BY hire_date ASC), by_team AS (PARTITION BY team ORDER BY performance DESC)';
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
            ->where('active')->equals(1)
            ->sql('sqlite', false);

        $expected = 'SELECT `id`, `name`, `salary`, ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC) AS `row_num` FROM `employees` WHERE `active` = 1';
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
            ->sql('sqlite', false);

        $expected = 'SELECT `department`, COUNT(*) as `emp_count`, RANK() OVER (ORDER BY COUNT(*) DESC) AS `dept_rank` FROM `employees` GROUP BY `department` HAVING COUNT(*) > 5';
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
            ->sql('sqlite', true);

        $this->assertInstanceOf(DbPreparedQueryInterface::class, $result);
        $expected = 'SELECT `id`, `name`, `salary`, ROW_NUMBER() OVER (PARTITION BY department ORDER BY salary DESC) AS `row_num` FROM `employees` WHERE `salary` > ?';
        $this->assertEquals($expected, $result->sql());
        $this->assertEquals([50000], $result->bindings());
    }

    public function testWindowWithOnlyPartitionBy(): void
    {
        $query = new DbQuery();
        $sql = $query->select('id, department')
            ->selectWindow('COUNT', 'dept_count', '*')
                ->partitionBy('department')
                ->endWindow()
            ->from('employees')
            ->sql('sqlite', false);

        $expected = 'SELECT `id`, `department`, COUNT(*) OVER (PARTITION BY department) AS `dept_count` FROM `employees`';
        $this->assertEquals($expected, $sql);
    }

    public function testWindowWithDenseRank(): void
    {
        $query = new DbQuery();
        $sql = $query->select('id, score')
            ->selectWindow('DENSE_RANK', 'dense_rank')
                ->windowOrderBy('score', 'DESC')
                ->endWindow()
            ->from('players')
            ->sql('sqlite', false);

        $expected = 'SELECT `id`, `score`, DENSE_RANK() OVER (ORDER BY score DESC) AS `dense_rank` FROM `players`';
        $this->assertEquals($expected, $sql);
    }

    public function testWindowWithNtile(): void
    {
        $query = new DbQuery();
        $sql = $query->select('id, salary')
            ->selectWindow('NTILE', 'quartile', '4')
                ->windowOrderBy('salary')
                ->endWindow()
            ->from('employees')
            ->sql('sqlite', false);

        $expected = 'SELECT `id`, `salary`, NTILE(4) OVER (ORDER BY salary ASC) AS `quartile` FROM `employees`';
        $this->assertEquals($expected, $sql);
    }

    public function testComplexWindowWithAllFeatures(): void
    {
        $query = new DbQuery();
        $sql = $query->select('date, amount, category')
            ->selectWindow('AVG', 'moving_avg', 'amount')
                ->partitionBy('category')
                ->windowOrderBy('date')
                ->frame('ROWS', '3 PRECEDING', '1 FOLLOWING')
                ->endWindow()
            ->from('transactions')
            ->where('category')->notEquals('void')
            ->sql('sqlite', false);

        $expected = 'SELECT `date`, `amount`, `category`, AVG(amount) OVER (PARTITION BY category ORDER BY date ASC ROWS BETWEEN 3 PRECEDING AND 1 FOLLOWING) AS `moving_avg` FROM `transactions` WHERE `category` != \'void\'';
        $this->assertEquals($expected, $sql);
    }
}
