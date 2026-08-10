<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Command\Delete;

use InvalidArgumentException;
use JardisSupport\DbQuery\Data\DbPreparedQuery;
use JardisSupport\DbQuery\Data\DeleteState;
use JardisSupport\DbQuery\Data\QueryConditionCollector;
use JardisSupport\DbQuery\Query\Builder\Clause\ConditionBuilder;
use JardisSupport\DbQuery\Query\Builder\Clause\JoinBuilder;
use JardisSupport\DbQuery\Query\Builder\Clause\LimitBuilder;
use JardisSupport\DbQuery\Query\Builder\Clause\OrderByBuilder;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use JardisSupport\DbQuery\Query\Formatter\IdentifierMarkerReplacer;
use JardisSupport\DbQuery\Query\Formatter\PlaceholderReplacer;
use JardisSupport\DbQuery\Query\Formatter\SimpleIdentifierQuoter;
use JardisSupport\DbQuery\Query\Formatter\ValueFormatter;
use JardisSupport\DbQuery\Query\Processor\JsonPlaceholderProcessor;
use JardisSupport\DbQuery\Query\Validator\SqlInjectionValidator;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;

/**
 * Base class for database-specific DELETE SQL generation
 *
 * Provides common functionality for building DELETE statements across different SQL dialects.
 * Subclasses implement dialect-specific features like identifier quoting and JSON functions.
 */
abstract class DeleteSqlBuilder
{
    protected DeleteState $state;
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
     * Generate DELETE SQL
     *
     * @param DeleteState $state Delete state containing table, alias, JOINs, ORDER BY, LIMIT
     * @param QueryConditionCollector $collector Collector with WHERE conditions and bindings
     * @param bool $prepared Whether to generate a prepared statement
     * @return string|DbPreparedQueryInterface
     */
    public function __invoke(
        DeleteState $state,
        QueryConditionCollector $collector,
        bool $prepared
    ): string|DbPreparedQueryInterface {
        $this->state = $state;
        $this->collector = $collector;

        // Initialize bindings from WHERE conditions
        $this->bindings = $collector->bindings();

        $delete = $this->buildDelete();
        $joins = $this->buildJoins($prepared);
        $where = $this->buildWhere($prepared);
        $order = $this->buildOrderBy();
        $limit = $this->buildLimit();

        $sql = $delete . $joins . $where . $order . $limit;

        // Normalize whitespace
        $sql = $this->normalizeWhitespace($sql);

        if ($prepared) {
            return new DbPreparedQuery($sql, $this->bindings, $this->dialect);
        }

        // Non-prepared mode: manually replace placeholders with validation
        return $this->replacePlaceholders($sql);
    }

    /**
     * Build DELETE clause with table name and optional alias
     *
     * @return string
     */
    protected function buildDelete(): string
    {
        $result = 'DELETE FROM ' . $this->quoteIdentifier($this->state->getTable());

        $alias = $this->state->getAlias();
        if ($alias !== null) {
            $result .= ' ' . $this->quoteIdentifier($alias);
        }

        return $result . ' ';
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
            fn(string $cond) => $this->quoteMarkedIdentifiers($cond),
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

        return $builder(
            $this->state,
            fn(string $id) => $this->quoteIfSimpleIdentifier($id)
        );
    }

    /**
     * Quote a candidate string when it is a simple identifier
     * (`ident` or `alias.ident`), otherwise return it unchanged
     *
     * @param string $identifier The candidate string
     * @return string The quoted identifier or the unchanged input
     */
    protected function quoteIfSimpleIdentifier(string $identifier): string
    {
        /** @var SimpleIdentifierQuoter $quoter */
        $quoter = $this->registry->get(SimpleIdentifierQuoter::class);

        return $quoter($identifier, fn(string $part) => $this->quoteIdentifier($part));
    }

    /**
     * Replace identifier markers in a condition string with
     * dialect-quoted identifiers
     *
     * @param string $condition The condition string possibly containing markers
     * @return string The condition string with all markers replaced
     */
    protected function quoteMarkedIdentifiers(string $condition): string
    {
        /** @var IdentifierMarkerReplacer $replacer */
        $replacer = $this->registry->get(IdentifierMarkerReplacer::class);

        return $replacer($condition, fn(string $id) => $this->quoteIfSimpleIdentifier($id));
    }

    /**
     * Build LIMIT clause using LimitBuilder
     * Note: LIMIT in DELETE is MySQL/MariaDB specific
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

        // DELETE doesn't support OFFSET
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
