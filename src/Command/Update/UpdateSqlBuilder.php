<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Command\Update;

use InvalidArgumentException;
use JardisSupport\DbQuery\Data\DbPreparedQuery;
use JardisSupport\DbQuery\Data\QueryConditionCollector;
use JardisSupport\DbQuery\Data\UpdateState;
use JardisSupport\DbQuery\Query\Builder\Clause\ConditionBuilder;
use JardisSupport\DbQuery\Query\Builder\Clause\JoinBuilder;
use JardisSupport\DbQuery\Query\Builder\Clause\LimitBuilder;
use JardisSupport\DbQuery\Query\Builder\Clause\OrderByBuilder;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use JardisSupport\DbQuery\Query\Formatter\PlaceholderReplacer;
use JardisSupport\DbQuery\Query\Formatter\ValueFormatter;
use JardisSupport\DbQuery\Query\Processor\JsonPlaceholderProcessor;
use JardisSupport\DbQuery\Query\Validator\SqlInjectionValidator;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\ExpressionInterface;

/**
 * Base class for database-specific UPDATE SQL generation
 *
 * Provides common functionality for building UPDATE statements across different SQL dialects.
 * Subclasses implement dialect-specific features like identifier quoting and JSON functions.
 */
abstract class UpdateSqlBuilder
{
    protected UpdateState $state;
    protected QueryConditionCollector $collector;
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
     * Generate UPDATE SQL
     *
     * @param UpdateState $state Update state containing table, alias, SET data, JOINs, ORDER BY, LIMIT
     * @param QueryConditionCollector $collector Collector with WHERE conditions and bindings
     * @param bool $prepared Whether to generate a prepared statement
     * @return string|DbPreparedQueryInterface
     */
    public function __invoke(
        UpdateState $state,
        QueryConditionCollector $collector,
        bool $prepared
    ): string|DbPreparedQueryInterface {
        $this->state = $state;
        $this->collector = $collector;

        // Initialize bindings: SET bindings first, then WHERE bindings
        $this->bindings = [];

        $update = $this->buildUpdate();
        $joins = $this->buildJoins($prepared);
        $set = $this->buildSet($prepared);

        // Merge WHERE condition bindings after SET bindings
        $this->bindings = array_merge($this->bindings, $collector->bindings());

        $where = $this->buildWhere($prepared);
        $order = $this->buildOrderBy();
        $limit = $this->buildLimit();

        $sql = $update . $joins . $set . $where . $order . $limit;

        // Normalize whitespace
        $sql = $this->normalizeWhitespace($sql);

        if ($prepared) {
            return new DbPreparedQuery($sql, $this->bindings, $this->dialect);
        }

        // Non-prepared mode: manually replace placeholders with validation
        return $this->replacePlaceholders($sql);
    }

    /**
     * Build UPDATE clause with table name and optional alias
     *
     * @return string
     */
    protected function buildUpdate(): string
    {
        $command = 'UPDATE';

        // Handle IGNORE (MySQL-only)
        if ($this->state->isIgnore()) {
            $command = $this->buildIgnoreUpdate();
        }

        $result = $command . ' ' . $this->quoteIdentifier($this->state->getTable());

        $alias = $this->state->getAlias();
        if ($alias !== null) {
            $result .= ' ' . $this->quoteIdentifier($alias);
        }

        return $result . ' ';
    }

    /**
     * Build UPDATE IGNORE (dialect-specific, MySQL-only)
     *
     * @return string
     */
    protected function buildIgnoreUpdate(): string
    {
        return 'UPDATE'; // Override in subclasses
    }

    /**
     * Build SET clause with column = value pairs
     * Supports scalar values and subqueries
     *
     * @param bool $prepared
     * @return string
     * @throws InvalidArgumentException If SET data is empty
     */
    protected function buildSet(bool $prepared): string
    {
        $setData = $this->state->getSetData();

        if (empty($setData)) {
            throw new InvalidArgumentException('SET clause cannot be empty');
        }

        $setParts = [];

        foreach ($setData as $field => $value) {
            $quotedField = $this->quoteIdentifier($field);

            // Handle Expression (raw SQL like NOW(), CURRENT_TIMESTAMP, etc.)
            if ($value instanceof ExpressionInterface) {
                $this->validateValue($value->toSql());
                $setParts[] = $quotedField . ' = ' . $value->toSql();
                continue;
            }

            // Handle subquery
            if ($value instanceof DbQueryBuilderInterface) {
                $subResult = $value->sql($this->dialect, $prepared);

                if ($prepared && $subResult instanceof DbPreparedQueryInterface) {
                    $setParts[] = $quotedField . ' = (' . $subResult->sql() . ')';
                    $this->bindings = array_merge($this->bindings, $subResult->bindings());
                } else {
                    /** @phpstan-ignore binaryOp.invalid */
                    $setParts[] = $quotedField . ' = (' . $subResult . ')';
                }
            } else {
                // Scalar value - use placeholder
                $setParts[] = $quotedField . ' = ?';
                $this->bindings[] = $value;
            }
        }

        return 'SET ' . implode(', ', $setParts) . ' ';
    }

    /**
     * Build JOIN clauses using JoinBuilder
     *
     * @param bool $prepared
     * @return string
     */
    protected function buildJoins(bool $prepared): string
    {
        if (empty($this->state->getJoins())) {
            return '';
        }

        /** @var JoinBuilder $builder */
        $builder = $this->registry->get(JoinBuilder::class);

        return $builder(
            $this->state,
            $this->dialect,
            $prepared,
            fn(string $id) => $this->quoteIdentifier($id),
            fn(string $type) => $this->shouldSkipJoinType($type),
            $this->bindings
        );
    }

    /**
     * Build WHERE clause using ConditionBuilder
     *
     * @param bool $prepared
     * @return string
     */
    protected function buildWhere(bool $prepared): string
    {
        if (empty($this->collector->whereConditions())) {
            return '';
        }

        /** @var ConditionBuilder $builder */
        $builder = $this->registry->get(ConditionBuilder::class);

        return $builder(
            $this->collector->whereConditions(),
            $this->dialect,
            $prepared,
            fn(string $cond) => $this->processJsonPlaceholders($cond),
            fn(string $cond) => $this->replaceSubqueryPlaceholders($cond),
            $this->bindings
        );
    }

    /**
     * Build ORDER BY clause using OrderByBuilder
     *
     * @return string
     */
    protected function buildOrderBy(): string
    {
        if (empty($this->state->getOrderBy())) {
            return '';
        }

        /** @var OrderByBuilder $builder */
        $builder = $this->registry->get(OrderByBuilder::class);

        return $builder($this->state);
    }

    /**
     * Build LIMIT clause using LimitBuilder
     * Note: LIMIT in UPDATE is MySQL/MariaDB specific
     *
     * @return string
     */
    protected function buildLimit(): string
    {
        $limit = $this->state->getLimit();

        if ($limit === null) {
            return '';
        }

        /** @var LimitBuilder $builder */
        $builder = $this->registry->get(LimitBuilder::class);

        // UPDATE doesn't support OFFSET
        return $builder($this->state);
    }

    /**
     * Process JSON placeholders in conditions
     *
     * @param string $condition
     * @return string
     */
    protected function processJsonPlaceholders(string $condition): string
    {
        /** @var JsonPlaceholderProcessor $processor */
        $processor = $this->registry->get(JsonPlaceholderProcessor::class);

        return $processor(
            $condition,
            fn(string $col, string $path) => $this->buildJsonExtract($col, $path),
            fn(string $col, string $val, ?string $path) => $this->buildJsonContains($col, $val, $path),
            fn(string $col, string $val, ?string $path) => $this->buildJsonNotContains($col, $val, $path),
            fn(string $col, ?string $path) => $this->buildJsonLength($col, $path)
        );
    }

    /**
     * Replace subquery placeholders in conditions
     *
     * @param string $condition
     * @return string
     */
    protected function replaceSubqueryPlaceholders(string $condition): string
    {
        // Subquery placeholders are handled by ConditionBuilder
        return $condition;
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
     * @return string
     */
    protected function replacePlaceholders(string $sql): string
    {
        /** @var PlaceholderReplacer $replacer */
        $replacer = $this->registry->get(PlaceholderReplacer::class);

        return $replacer->replaceAll(
            $sql,
            $this->bindings,
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

    /**
     * Determines if a join type should be skipped for this database dialect
     * Can be overridden by subclasses to filter unsupported join types
     *
     * @param string $joinType
     * @return bool
     */
    protected function shouldSkipJoinType(string $joinType): bool
    {
        return false;
    }

    /**
     * Build JSON_EXTRACT expression for this dialect
     * Must be implemented by dialect-specific subclasses
     *
     * @param string $column
     * @param string $path
     * @return string
     */
    abstract protected function buildJsonExtract(string $column, string $path): string;

    /**
     * Build JSON_CONTAINS expression for this dialect
     * Must be implemented by dialect-specific subclasses
     *
     * @param string $column
     * @param string $value
     * @param string|null $path
     * @return string
     */
    abstract protected function buildJsonContains(string $column, string $value, ?string $path): string;

    /**
     * Build negated JSON_CONTAINS expression for this dialect
     * Can be overridden by dialect-specific subclasses
     *
     * @param string $column
     * @param string $value
     * @param string|null $path
     * @return string
     */
    protected function buildJsonNotContains(string $column, string $value, ?string $path): string
    {
        return 'NOT (' . $this->buildJsonContains($column, $value, $path) . ')';
    }

    /**
     * Build JSON_LENGTH expression for this dialect
     * Must be implemented by dialect-specific subclasses
     *
     * @param string $column
     * @param string|null $path
     * @return string
     */
    abstract protected function buildJsonLength(string $column, ?string $path): string;
}
