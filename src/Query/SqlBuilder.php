<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query;

use InvalidArgumentException;
use JardisSupport\DbQuery\Data\DbPreparedQuery;
use JardisSupport\DbQuery\Data\QueryConditionCollector;
use JardisSupport\DbQuery\Data\QueryState;
use JardisSupport\DbQuery\Query\Builder\Clause\ConditionBuilder;
use JardisSupport\DbQuery\Query\Builder\Clause\CteBuilder;
use JardisSupport\DbQuery\Query\Builder\Clause\FromBuilder;
use JardisSupport\DbQuery\Query\Builder\Clause\GroupByBuilder;
use JardisSupport\DbQuery\Query\Builder\Clause\JoinBuilder;
use JardisSupport\DbQuery\Query\Builder\Clause\LimitBuilder;
use JardisSupport\DbQuery\Query\Builder\Clause\OrderByBuilder;
use JardisSupport\DbQuery\Query\Builder\Clause\SelectBuilder;
use JardisSupport\DbQuery\Query\Builder\Clause\UnionBuilder;
use JardisSupport\DbQuery\Query\Builder\Clause\WindowClauseBuilder;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use JardisSupport\DbQuery\Query\Formatter\PlaceholderReplacer;
use JardisSupport\DbQuery\Query\Formatter\ValueFormatter;
use JardisSupport\DbQuery\Query\Processor\JsonPlaceholderProcessor;
use JardisSupport\DbQuery\Query\Validator\SqlInjectionValidator;
use JardisSupport\Contract\DbQuery\DbDeleteBuilderInterface;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\DbUpdateBuilderInterface;
use UnexpectedValueException;

/**
 * Base class for database-specific SQL generation
 */
class SqlBuilder
{
    protected QueryState $state;
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
     * Create an SQL generator for the specified dialect
     *
     * @param QueryState $state The query state object containing all query data
     * @param QueryConditionCollector $collector The condition collector containing WHERE/HAVING conditions and bindings
     * @param bool $prepared Whether to generate a prepared SQL query with bound parameters
     * @return string|DbPreparedQueryInterface
     */
    public function __invoke(
        QueryState $state,
        QueryConditionCollector $collector,
        bool $prepared
    ): string|DbPreparedQueryInterface {
        $this->state = $state;
        $this->collector = $collector;

        // Start with empty bindings for proper order
        $mainQueryBindings = $collector->bindings();
        $this->bindings = [];

        $cte    = $this->buildCte($prepared);
        $select = $this->buildSelect($prepared);
        $from   = $this->buildFrom($prepared);
        $joins  = $this->buildJoins($prepared);

        // Merge main query bindings after all structural elements (CTE, SELECT, FROM, JOIN)
        $this->bindings = array_merge($this->bindings, $mainQueryBindings);

        $where  = $this->buildWhere($prepared);
        $group  = $this->buildGroupBy();
        $having = $this->buildHaving($prepared);
        $unions = $this->buildUnions($prepared);
        $order  = $this->buildOrderBy();
        $limit  = $this->buildLimitOffset();
        $window = $this->buildWindow();

        $sql = $cte . $select . $from . $joins . $where . $group . $having . $unions . $window . $order . $limit;

        // Normalize whitespace in both modes
        $sql = $this->normalizeWhitespace($sql);

        if ($prepared) {
            return new DbPreparedQuery($sql, $this->bindings, $this->dialect);
        }

        // Non-prepared mode: manually replace placeholders with validation
        return $this->replacePlaceholders($sql);
    }

    protected function buildCte(bool $prepared): string
    {
        /** @var CteBuilder $builder */
        $builder = $this->registry->get(CteBuilder::class);

        return $builder(
            $this->state,
            $this->dialect,
            $prepared,
            fn(string $id) => $this->quoteIdentifier($id),
            $this->bindings
        );
    }

    /**
     * Builds the SELECT clause with fields and optional subqueries
     *
     * @param bool $prepared Whether to use prepared statement mode
     * @return string The SELECT clause
     */
    protected function buildSelect(bool $prepared): string
    {
        /** @var SelectBuilder $builder */
        $builder = $this->registry->get(SelectBuilder::class);

        return $builder(
            $this->state,
            $this->dialect,
            $prepared,
            fn(string $id) => $this->quoteIdentifier($id),
            fn(string $fields) => $this->quoteSelectFieldList($fields),
            $this->bindings
        );
    }

    /**
     * Builds the FROM clause with a table name or subquery
     *
     * @param bool $prepared Whether to use prepared statement mode
     * @return string The FROM clause
     */
    protected function buildFrom(bool $prepared): string
    {
        /** @var FromBuilder $builder */
        $builder = $this->registry->get(FromBuilder::class);

        return $builder(
            $this->state,
            $this->dialect,
            $prepared,
            fn(string $id) => $this->quoteIdentifier($id),
            $this->bindings
        );
    }


    protected function buildJoins(bool $prepared): string
    {
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
     * Processes WHERE conditions including JSON placeholders
     *
     * @param bool $prepared Whether to use prepared statement mode
     * @return string The WHERE clause
     */
    protected function buildWhere(bool $prepared): string
    {
        return $this->buildConditions($this->collector->whereConditions(), $prepared);
    }

    protected function buildGroupBy(): string
    {
        /** @var GroupByBuilder $builder */
        $builder = $this->registry->get(GroupByBuilder::class);

        return $builder(
            $this->state,
            fn(string $id) => $this->quoteIfSimpleIdentifier($id)
        );
    }

    /**
     * Processes HAVING conditions including JSON placeholders
     *
     * @return string The HAVING clause
     */
    protected function buildHaving(bool $prepared = false): string
    {
        $result = $this->buildConditions($this->collector->havingConditions(), $prepared);

        return $result ? ' HAVING ' . $result : $result;
    }

    /**
     * Builds a SQL conditions string based on the provided conditions array.
     *
     * @param array<int, string|array{
     *      type: string,
     *      container: DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface,
     *      closeBracket: ?string
     *  }> $conditions An array of conditions to be processed into a SQL string.
     * @param bool $prepared Indicates whether the SQL query should be built with prepared statements.
     * @return string The resulting SQL conditions string.
     */
    private function buildConditions(array $conditions = [], bool $prepared = false): string
    {
        /** @var ConditionBuilder $builder */
        $builder = $this->registry->get(ConditionBuilder::class);

        return $builder(
            $conditions,
            $this->dialect,
            $prepared,
            fn(string $cond) => $this->processJsonPlaceholders($cond),
            fn(string $cond) => $this->replaceSubqueryPlaceholders($cond),
            fn(string $cond) => $this->quoteMarkedIdentifiers($cond),
            $this->bindings
        );
    }


    protected function buildOrderBy(): string
    {
        /** @var OrderByBuilder $builder */
        $builder = $this->registry->get(OrderByBuilder::class);

        return $builder(
            $this->state,
            fn(string $id) => $this->quoteIfSimpleIdentifier($id)
        );
    }

    protected function buildLimitOffset(): string
    {
        /** @var LimitBuilder $builder */
        $builder = $this->registry->get(LimitBuilder::class);

        return $builder($this->state);
    }

    protected function buildUnions(bool $prepared): string
    {
        /** @var UnionBuilder $builder */
        $builder = $this->registry->get(UnionBuilder::class);

        return $builder(
            $this->state,
            $this->dialect,
            $prepared,
            $this->bindings
        );
    }

    protected function buildWindow(): string
    {
        /** @var WindowClauseBuilder $builder */
        $builder = $this->registry->get(WindowClauseBuilder::class);

        return $builder($this->state);
    }

    protected function normalizeWhitespace(string $sql): string
    {
        $result = preg_replace('/\s+/', ' ', $sql);

        return is_string($result) ? trim($result) : '';
    }

    /**
     * Quote identifier with backticks
     */
    public function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
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
        /** @var Formatter\SimpleIdentifierQuoter $quoter */
        $quoter = $this->registry->get(Formatter\SimpleIdentifierQuoter::class);

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
        /** @var Formatter\IdentifierMarkerReplacer $replacer */
        $replacer = $this->registry->get(Formatter\IdentifierMarkerReplacer::class);

        return $replacer($condition, fn(string $id) => $this->quoteIfSimpleIdentifier($id));
    }

    /**
     * Quote simple identifiers in the SELECT field list
     *
     * @param string $fields The raw field list
     * @return string The field list with simple identifiers quoted
     */
    protected function quoteSelectFieldList(string $fields): string
    {
        /** @var Formatter\FieldListIdentifierQuoter $quoter */
        $quoter = $this->registry->get(Formatter\FieldListIdentifierQuoter::class);

        return $quoter($fields, fn(string $id) => $this->quoteIfSimpleIdentifier($id));
    }

    /**
     * Determines if a join type should be skipped for this database dialect
     * Can be overridden by subclasses to filter unsupported join types
     *
     * @param string $joinType The join type to check
     * @return bool True if the join should be skipped, false otherwise
     */
    protected function shouldSkipJoinType(string $joinType): bool
    {
        return false;
    }

    /**
     * Replaces placeholders in an SQL string with their corresponding values from the binding array.
     *
     * @param string $sql The SQL string containing placeholders.
     * @return string The SQL string with placeholders replaced by their respective values.
     * @throws UnexpectedValueException If a placeholder does not have a corresponding value in the binding array.
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
     * Formats a value for SQL based on its type
     *
     * @param mixed $value The value to format
     * @return string The formatted SQL value
     * @throws InvalidArgumentException If a value type is unsupported or unsafe
     */
    protected function formatValue(mixed $value): string
    {
        /** @var ValueFormatter $formatter */
        $formatter = $this->registry->get(ValueFormatter::class);

        return $formatter(
            $value,
            $this->dialect,
            fn(bool $v) => $this->formatBoolean($v),
            fn(string $v) => $this->escapeString($v),
            fn(string $v) => $this->validateValue($v)
        );
    }

    /**
     * Formats boolean values (can be overridden by dialect-specific classes)
     *
     * @param bool $value
     * @return string
     */
    protected function formatBoolean(bool $value): string
    {
        return (string) (int) $value;
    }

    /**
     * Validates user-provided string values for SQL injection attempts
     *
     * @param string $value The user input value to validate
     * @throws InvalidArgumentException If unsafe SQL patterns are detected
     */
    protected function validateValue(string $value): void
    {
        /** @var SqlInjectionValidator $validator */
        $validator = $this->registry->get(SqlInjectionValidator::class);

        $validator($value);
    }

    /**
     * Escapes a string value for SQL
     * Only used in non-prepared mode
     *
     * @param string $value
     * @return string
     */
    protected function escapeString(string $value): string
    {
        // Escape backslashes first, then single quotes
        $value = str_replace('\\', '\\\\', $value);
        return str_replace("'", "''", $value);
    }

    /**
     * Replaces subquery placeholders with actual subquery SQL in prepared mode
     *
     * @param string $condition The condition string with ? placeholders
     * @return string The condition with subquery placeholders replaced
     */
    protected function replaceSubqueryPlaceholders(string $condition): string
    {
        /** @var PlaceholderReplacer $replacer */
        $replacer = $this->registry->get(PlaceholderReplacer::class);

        return $replacer->replaceSubqueries(
            $condition,
            $this->bindings,
            $this->dialect
        );
    }

    /**
     * Processes a condition string with JSON-specific placeholders
     *
     * @param string $condition The input condition string containing JSON placeholders
     * @return string The condition string with all JSON placeholders replaced
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
     * Builds JSON_EXTRACT expression for the specific dialect
     *
     * @param string $column The JSON column name
     * @param string $path The JSON path
     * @return string The dialect-specific JSON extract expression
     */
    protected function buildJsonExtract(string $column, string $path): string
    {
        // Default implementation - can be overridden by specific dialects
        return "JSON_EXTRACT(" . $this->quoteIdentifier($column) . ", " . $this->escapeString($path) . ")";
    }

    /**
     * Builds JSON_CONTAINS expression for the specific dialect
     *
     * @param string $column The JSON column name
     * @param string $value The value parameter (with: prefix)
     * @param string|null $path Optional JSON path
     * @return string The dialect-specific JSON contains an expression
     */
    protected function buildJsonContains(string $column, string $value, ?string $path): string
    {
        if ($path !== null) {
            return "JSON_CONTAINS("
                . $this->quoteIdentifier($column)
                . ", "
                . $value . ", '"
                . $this->escapeString($path)
                . "')";
        }
        return "JSON_CONTAINS(" . $this->quoteIdentifier($column) . ", " . $value . ")";
    }

    /**
     * Builds JSON_NOT_CONTAINS (negated JSON_CONTAINS) expression for the specific dialect
     *
     * @param string $column The JSON column name
     * @param string $value The value parameter (with: prefix)
     * @param string|null $path Optional JSON path
     * @return string The dialect-specific negated JSON contains an expression
     */
    protected function buildJsonNotContains(string $column, string $value, ?string $path): string
    {
        // Default: negate JSON_CONTAINS
        return "NOT " . $this->buildJsonContains($column, $value, $path);
    }

    /**
     * Builds JSON_LENGTH expression for the specific dialect
     *
     * @param string $column The JSON column name
     * @param string|null $path Optional JSON path
     * @return string The dialect-specific JSON length expression
     */
    protected function buildJsonLength(string $column, ?string $path): string
    {
        // Default implementation - can be overridden by specific dialects
        if ($path !== null) {
            return "JSON_LENGTH(" . $this->quoteIdentifier($column) . ", '" . $this->escapeString($path) . "')";
        }
        return "JSON_LENGTH(" . $this->quoteIdentifier($column) . ")";
    }
}
