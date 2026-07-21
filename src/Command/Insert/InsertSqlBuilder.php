<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Command\Insert;

use InvalidArgumentException;
use JardisSupport\DbQuery\Data\DbPreparedQuery;
use JardisSupport\DbQuery\Data\InsertState;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use JardisSupport\DbQuery\Query\Formatter\PlaceholderReplacer;
use JardisSupport\DbQuery\Query\Formatter\ValueFormatter;
use JardisSupport\DbQuery\Query\Validator\SqlInjectionValidator;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\ExpressionInterface;

/**
 * Base class for database-specific INSERT SQL generation
 *
 * Provides common functionality for building INSERT statements across different SQL dialects.
 * Subclasses implement dialect-specific features like identifier quoting.
 */
abstract class InsertSqlBuilder
{
    protected InsertState $state;
    /** @var array<int|string, mixed> */
    protected array $bindings;
    protected string $dialect;
    protected BuilderRegistry $registry;

    /**
     * @param BuilderRegistry $registry Instance-based builder registry with dialect context
     */
    public function __construct(BuilderRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Generate INSERT SQL
     *
     * @param InsertState $state Insert state containing table, columns, values, selectQuery
     * @param array<int|string, mixed> $bindings Bindings for prepared statements
     * @param bool $prepared Whether to generate a prepared statement
     * @return string|DbPreparedQueryInterface
     */
    public function __invoke(
        InsertState $state,
        array $bindings,
        bool $prepared
    ): string|DbPreparedQueryInterface {
        $this->state = $state;
        $this->bindings = $prepared ? $bindings : [];

        $insert = $this->buildInsert();
        $columns = $this->buildColumns();

        if ($this->state->getSelectQuery() !== null) {
            $values = $this->buildSelectClause($prepared);
        } else {
            $values = $this->buildValues($prepared);
        }

        $onDuplicate = $this->buildOnDuplicateKeyUpdate($prepared);
        $onConflict = $this->buildOnConflict($prepared);

        $sql = $insert . $columns . $values . $onDuplicate . $onConflict;

        // Normalize whitespace
        $sql = $this->normalizeWhitespace($sql);

        if ($prepared) {
            return new DbPreparedQuery($sql, $this->bindings, $this->dialect);
        }

        // Non-prepared mode: values are already formatted inline
        return $this->replacePlaceholders($sql, $this->bindings);
    }

    /**
     * Build INSERT INTO clause with modifiers
     *
     * @return string
     */
    protected function buildInsert(): string
    {
        $command = 'INSERT';

        // Handle OR IGNORE (SQLite)
        if ($this->state->isOrIgnore()) {
            $command = $this->buildOrIgnoreInsert();
        }

        // Handle REPLACE (MySQL, SQLite)
        if ($this->state->isReplace()) {
            $command = $this->buildReplaceInsert();
        }

        return $command . ' INTO ' . $this->quoteIdentifier($this->state->getTable()) . ' ';
    }

    /**
     * Build OR IGNORE modifier (dialect-specific)
     *
     * @return string
     */
    protected function buildOrIgnoreInsert(): string
    {
        return 'INSERT'; // Override in subclasses
    }

    /**
     * Build REPLACE command (dialect-specific)
     *
     * @return string
     */
    protected function buildReplaceInsert(): string
    {
        return 'REPLACE'; // Override in subclasses
    }

    /**
     * Build columns clause
     *
     * @return string
     */
    protected function buildColumns(): string
    {
        $columns = $this->state->getFields();

        if (empty($columns)) {
            return '';
        }

        $quotedColumns = array_map(
            fn($col) => $this->quoteIdentifier($col),
            $columns
        );

        return '(' . implode(', ', $quotedColumns) . ') ';
    }

    /**
     * Build VALUES clause
     *
     * @param bool $prepared
     * @return string
     */
    protected function buildValues(bool $prepared): string
    {
        $valueRows = $this->state->getValueRows();

        if (empty($valueRows)) {
            return '';
        }

        $rowStrings = [];

        if ($prepared) {
            // Prepared mode: use placeholders
            foreach ($valueRows as $row) {
                $placeholders = array_fill(0, count($row), '?');
                $rowStrings[] = '(' . implode(', ', $placeholders) . ')';
            }
        } else {
            // Non-prepared mode: format values directly
            foreach ($valueRows as $row) {
                $formattedValues = array_map(
                    fn($value) => $this->formatValue($value),
                    $row
                );
                $rowStrings[] = '(' . implode(', ', $formattedValues) . ')';
            }
        }

        return 'VALUES ' . implode(', ', $rowStrings);
    }

    /**
     * Build SELECT clause for INSERT...SELECT
     *
     * @param bool $prepared
     * @return string
     */
    protected function buildSelectClause(bool $prepared): string
    {
        $selectQuery = $this->state->getSelectQuery();

        if ($selectQuery === null) {
            return '';
        }

        $subResult = $selectQuery->sql($this->dialect, $prepared);

        if ($prepared && $subResult instanceof DbPreparedQueryInterface) {
            $this->bindings = array_merge($this->bindings, $subResult->bindings());
            return $subResult->sql();
        }

        /** @phpstan-ignore return.type */
        return $subResult;
    }

    /**
     * Normalize whitespace in SQL string
     *
     * @param string $sql
     * @return string
     */
    protected function normalizeWhitespace(string $sql): string
    {
        $result = preg_replace('/\s+/', ' ', $sql);
        return is_string($result) ? trim($result) : '';
    }

    /**
     * Replace placeholders with actual values in non-prepared mode
     *
     * @param string $sql
     * @param array<int|string, mixed> $bindings
     * @return string
     */
    protected function replacePlaceholders(string $sql, array $bindings): string
    {
        /** @var PlaceholderReplacer $replacer */
        $replacer = $this->registry->get(PlaceholderReplacer::class);

        return $replacer->replaceAll(
            $sql,
            $bindings,
            fn(mixed $value) => $this->formatValue($value)
        );
    }

    /**
     * Format a value for SQL (non-prepared mode only)
     *
     * @param mixed $value
     * @return string
     */
    protected function formatValue(mixed $value): string
    {
        // Handle ExpressionInterface (raw SQL expressions)
        if ($value instanceof ExpressionInterface) {
            $sql = $value->toSql();
            $this->validateValue($sql);  // Security check
            return $sql;  // Return directly, NO escaping!
        }

        /** @var ValueFormatter $formatter */
        $formatter = $this->registry->get(ValueFormatter::class);

        return $formatter(
            $value,
            $this->dialect,
            fn(bool $val) => $this->formatBoolean($val),
            fn(string $val) => $this->escapeString($val),
            fn(string $val) => $this->validateValue($val)
        );
    }

    /**
     * Validate value against SQL injection patterns
     *
     * @param string $value
     * @return void
     * @throws InvalidArgumentException If unsafe SQL patterns are detected
     */
    protected function validateValue(string $value): void
    {
        /** @var SqlInjectionValidator $validator */
        $validator = $this->registry->get(SqlInjectionValidator::class);
        $validator($value);
    }

    /**
     * Escape a string value for SQL
     *
     * @param string $value
     * @return string
     */
    protected function escapeString(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        return str_replace("'", "''", $value);
    }

    /**
     * Build ON DUPLICATE KEY UPDATE clause (MySQL-specific)
     *
     * @param bool $prepared
     * @return string
     */
    protected function buildOnDuplicateKeyUpdate(bool $prepared): string
    {
        $updates = $this->state->getOnDuplicateKeyUpdate();

        if (empty($updates)) {
            return '';
        }

        // Only MySQL supports ON DUPLICATE KEY UPDATE
        if ($this->dialect !== 'mysql') {
            return '';
        }

        $setParts = [];
        foreach ($updates as $field => $value) {
            $quotedField = $this->quoteIdentifier($field);

            if ($value instanceof ExpressionInterface) {
                $setParts[] = $quotedField . ' = ' . $value->toSql();
            } else {
                if ($prepared) {
                    $this->bindings[] = $value;
                    $setParts[] = $quotedField . ' = ?';
                } else {
                    $formattedValue = $this->formatValue($value);
                    $setParts[] = $quotedField . ' = ' . $formattedValue;
                }
            }
        }

        return ' ON DUPLICATE KEY UPDATE ' . implode(', ', $setParts);
    }

    /**
     * Build ON CONFLICT clause (PostgreSQL/SQLite style upsert)
     *
     * @param bool $prepared
     * @return string
     */
    protected function buildOnConflict(bool $prepared): string
    {
        // Default implementation returns empty string
        // Override in dialect-specific builders for PostgreSQL and SQLite
        return '';
    }

    /**
     * Quote an identifier (table or column name) based on dialect
     * Must be implemented by dialect-specific subclasses
     *
     * @param string $identifier
     * @return string
     */
    abstract protected function quoteIdentifier(string $identifier): string;

    /**
     * Format boolean value based on dialect
     * Can be overridden by dialect-specific builders
     *
     * @param bool $value
     * @return string
     */
    protected function formatBoolean(bool $value): string
    {
        return $value ? '1' : '0';
    }
}
