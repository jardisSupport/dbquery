<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery;

use JardisSupport\DbQuery\Data\QueryConditionCollector;
use JardisSupport\DbQuery\Data\QueryState;
use JardisSupport\DbQuery\Query\Condition\QueryCondition;
use JardisSupport\DbQuery\Query\Condition\QueryJsonCondition;
use JardisSupport\DbQuery\Query\Builder\Method;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use JardisSupport\DbQuery\Factory\SqlBuilderFactory;
use JardisSupport\DbQuery\Query\Validator\QueryBracketValidator;
use JardisSupport\Contract\DbQuery\DbDeleteBuilderInterface;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\DbQueryConditionBuilderInterface;
use JardisSupport\Contract\DbQuery\DbQueryJsonConditionBuilderInterface;
use JardisSupport\Contract\DbQuery\DbUpdateBuilderInterface;
use JardisSupport\Contract\DbQuery\DbWindowBuilderInterface;
use JardisSupport\Contract\DbQuery\ExpressionInterface;
use UnexpectedValueException;

/**
 * Provides methods for creating and managing SQL queries,
 * including support for SELECT, JOIN, WHERE, GROUP BY, and more.
 *
 * This class implements the DbQueryBuilderInterface to facilitate
 * query building with a fluent interface for dynamic query construction.
 */
class DbQuery implements DbQueryBuilderInterface
{
    private QueryConditionCollector $collector;
    private QueryCondition $queryCondition;
    private QueryJsonCondition $queryJsonCondition;
    private QueryState $state;
    private BuilderRegistry $registry;

    public function __construct()
    {
        $this->registry = new BuilderRegistry();
        $this->state = new QueryState();
        $this->collector = new QueryConditionCollector();
        $this->queryCondition = new QueryCondition($this, $this->collector, $this->registry);
        $this->queryJsonCondition = new QueryJsonCondition($this, $this->collector, $this->registry);
    }

    public function with(string $name, DbQueryBuilderInterface $query): DbQueryBuilderInterface
    {
        $this->state->addCte($name, $query);
        return $this;
    }

    public function withRecursive(string $name, DbQueryBuilderInterface $query): DbQueryBuilderInterface
    {
        $this->state->addCteRecursive($name, $query);
        return $this;
    }

    public function select(string $fields = "*"): self
    {
        $this->state->setFields(empty($fields) ? '*' : $fields);
        return $this;
    }

    public function selectSubquery(DbQueryBuilderInterface $query, string $alias): DbQueryBuilderInterface
    {
        $this->state->addSelectSubquery($alias, $query);
        return $this;
    }

    public function distinct(bool $isDistinctQuery = true): self
    {
        $this->state->setDistinct($isDistinctQuery);
        return $this;
    }

    public function from(string|DbQueryBuilderInterface $container, ?string $alias = null): self
    {
        $this->state->setContainer($container);
        $this->state->setAlias($alias);
        return $this;
    }

    public function where(
        string|ExpressionInterface|null $field = null,
        ?string $openBracket = null
    ): DbQueryConditionBuilderInterface {
        $resolvedField = $this->registry->get(Method\ResolveField::class)($field);

        return $this->registry->get(Method\Where::class)(
            $this->collector,
            $this->queryCondition,
            $resolvedField,
            $openBracket
        );
    }

    public function and(
        string|ExpressionInterface|null $field = null,
        ?string $openBracket = null
    ): DbQueryConditionBuilderInterface {
        $resolvedField = $this->registry->get(Method\ResolveField::class)($field);

        return $this->registry->get(Method\AndCondition::class)(
            $this->collector,
            $this->queryCondition,
            $resolvedField,
            $openBracket,
            true  // DbQuery supports HAVING
        );
    }

    public function or(
        string|ExpressionInterface|null $field = null,
        ?string $openBracket = null
    ): DbQueryConditionBuilderInterface {
        $resolvedField = $this->registry->get(Method\ResolveField::class)($field);

        return $this->registry->get(Method\OrCondition::class)(
            $this->collector,
            $this->queryCondition,
            $resolvedField,
            $openBracket,
            true  // DbQuery supports HAVING
        );
    }

    /**
     * Starts a JSON-specific WHERE condition
     *
     * @param string $field The JSON column name (without a path)
     * @param string|null $openBracket Optional opening bracket(s)
     * @return DbQueryJsonConditionBuilderInterface For JSON-specific operations
     */
    public function whereJson(string $field, ?string $openBracket = null): DbQueryJsonConditionBuilderInterface
    {
        return $this->registry->get(Method\WhereJson::class)(
            $this->collector,
            $this->queryJsonCondition,
            $field,
            $openBracket
        );
    }

    /**
     * Starts a JSON-specific AND condition
     *
     * @param string $field The JSON column name (without a path)
     * @param string|null $openBracket Optional opening bracket(s)
     * @return DbQueryJsonConditionBuilderInterface For JSON-specific operations
     */
    public function andJson(string $field, ?string $openBracket = null): DbQueryJsonConditionBuilderInterface
    {
        return $this->registry->get(Method\AndJson::class)(
            $this->collector,
            $this->queryJsonCondition,
            $field,
            $openBracket
        );
    }

    /**
     * Starts a JSON-specific OR condition
     *
     * @param string $field The JSON column name (without a path)
     * @param string|null $openBracket Optional opening bracket(s)
     * @return DbQueryJsonConditionBuilderInterface For JSON-specific operations
     */
    public function orJson(string $field, ?string $openBracket = null): DbQueryJsonConditionBuilderInterface
    {
        return $this->registry->get(Method\OrJson::class)(
            $this->collector,
            $this->queryJsonCondition,
            $field,
            $openBracket
        );
    }

    /**
     * Adds an INNER JOIN clause to the query
     *
     * @param string|DbQueryBuilderInterface $container The table name or subquery to join
     * @param string $constraint The join condition
     * @param string|null $alias Optional alias for the joined table
     * @return $this Returns this query builder for method chaining
     */
    public function innerJoin(
        string|DbQueryBuilderInterface $container,
        string $constraint,
        ?string $alias = null
    ): self {
        return $this->registry->get(Method\InnerJoin::class)(
            $this->state,
            $this,
            $container,
            $constraint,
            $alias
        );
    }

    /**
     * Adds a LEFT JOIN clause to the query
     *
     * @param string|DbQueryBuilderInterface $container The table name or subquery to join
     * @param string $constraint The join condition
     * @param string|null $alias Optional alias for the joined table
     * @return $this Returns this query builder for method chaining
     */
    public function leftJoin(
        string|DbQueryBuilderInterface $container,
        string $constraint,
        ?string $alias = null
    ): self {
        return $this->registry->get(Method\LeftJoin::class)(
            $this->state,
            $this,
            $container,
            $constraint,
            $alias
        );
    }

    /**
     * Adds a RIGHT JOIN clause to the query
     *
     * @param string|DbQueryBuilderInterface $container The table name or subquery to join
     * @param string $constraint The join condition
     * @param string|null $alias Optional alias for the joined table
     * @return $this Returns this query builder for method chaining
     */
    public function rightJoin(
        string|DbQueryBuilderInterface $container,
        string $constraint,
        ?string $alias = null
    ): self {
        return $this->registry->get(Method\RightJoin::class)(
            $this->state,
            $this,
            $container,
            $constraint,
            $alias
        );
    }

    /**
     * Adds a FULL OUTER JOIN clause to the query
     *
     * @param string|DbQueryBuilderInterface $container The table name or subquery to join
     * @param string $constraint The join condition
     * @param string|null $alias Optional alias for the joined table
     * @return $this Returns this query builder for method chaining
     */
    public function fullJoin(
        string|DbQueryBuilderInterface $container,
        string $constraint,
        ?string $alias = null
    ): self {
        return $this->registry->get(Method\FullJoin::class)(
            $this->state,
            $this,
            $container,
            $constraint,
            $alias
        );
    }

    /**
     * Adds a CROSS JOIN clause to the query
     *
     * @param string|DbQueryBuilderInterface $container The table name or subquery to cross join
     * @param string|null $alias Optional alias for the joined table
     * @return $this Returns this query builder for method chaining
     */
    public function crossJoin(string|DbQueryBuilderInterface $container, ?string $alias = null): self
    {
        return $this->registry->get(Method\CrossJoin::class)(
            $this->state,
            $this,
            $container,
            $alias
        );
    }

    public function union(DbQueryBuilderInterface $query): DbQueryBuilderInterface
    {
        $this->state->addUnion($query);
        return $this;
    }

    public function unionAll(DbQueryBuilderInterface $query): DbQueryBuilderInterface
    {
        $this->state->addUnionAll($query);
        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->state->addGroupBy($column);
        }
        return $this;
    }

    public function having(string $expression, ?string $openBracket = null): DbQueryConditionBuilderInterface
    {
        $this->queryCondition->initCondition($openBracket ?? '', $expression, true);

        return $this->queryCondition;
    }

    /**
     * Starts a JSON-specific HAVING condition
     *
     * @param string $field The JSON column name (without a path)
     * @param string|null $openBracket Optional opening bracket(s)
     * @return DbQueryJsonConditionBuilderInterface For JSON-specific operations
     */
    public function havingJson(string $field, ?string $openBracket = null): DbQueryJsonConditionBuilderInterface
    {
        $this->queryJsonCondition->initCondition($field, $openBracket ?? '', true);

        return $this->queryJsonCondition;
    }

    /**
     * Add an ORDER BY clause to the query
     *
     * @param string $field The column name to sort by
     * @param string $direction The sort direction: 'ASC' or 'DESC'
     * @return $this Returns this query builder for method chaining
     */
    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        return $this->registry->get(Method\OrderBy::class)(
            $this->state,
            $this,
            $field,
            $direction
        );
    }

    /**
     * Limits the number of rows returned and optionally sets an offset
     *
     * @param int|null $limit Maximum number of rows to return
     * @param int|null $offset Number of rows to skip before starting to return rows
     * @return $this Returns this query builder for method chaining
     */
    public function limit(int $limit = null, int $offset = null): self
    {
        return $this->registry->get(Method\Limit::class)(
            $this->state,
            $this,
            $limit,
            $offset
        );
    }

    public function selectWindow(string $function, string $alias, ?string $args = null): DbWindowBuilderInterface
    {
        return $this->registry->get(Method\SelectWindow::class)(
            $this->state,
            $this,
            $function,
            $alias,
            $args
        );
    }

    public function window(string $name): DbWindowBuilderInterface
    {
        return $this->registry->get(Method\Window::class)(
            $this->state,
            $this,
            $name
        );
    }

    public function selectWindowRef(string $function, string $windowName, string $alias, ?string $args = null): self
    {
        $this->registry->get(Method\SelectWindowRef::class)(
            $this->state,
            $this,
            $function,
            $windowName,
            $alias,
            $args
        );

        return $this;
    }

    public function exists(
        DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $query,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        return $this->registry->get(Method\Exists::class)(
            $this->collector,
            $this,
            $query,
            $closeBracket
        );
    }

    public function notExists(
        DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $query,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        return $this->registry->get(Method\NotExists::class)(
            $this->collector,
            $this,
            $query,
            $closeBracket
        );
    }

    /**
     * Generates and returns the SQL query string or prepared query interface based on the given dialect.
     *
     * @param string $dialect The SQL dialect to be used for query generation (e.g., MySQL, PostgresSQL).
     * @param bool $prepared Whether to generate a prepared SQL query with bound parameters. Defaults to true.
     * @param string|null $version Database version (e.g., '5.7', '8.0'). Uses default if null.
     * @return string|DbPreparedQueryInterface Returns SQL query string or DbPreparedQueryInterface if prepared is true.
     * @throws UnexpectedValueException Thrown if the query contains invalid bracket structures.
     */
    public function sql(
        string $dialect,
        bool $prepared = true,
        ?string $version = null
    ): string|DbPreparedQueryInterface {
        $sqlBuilder = SqlBuilderFactory::createSelect($dialect, $version);

        // Validate bracket balance across all query parts
        $validator = new QueryBracketValidator();
        if (
            !$validator->hasValidBrackets(
                $this->collector->whereConditions(),
                $this->collector->havingConditions()
            )
        ) {
            throw new UnexpectedValueException('Invalid brackets in query');
        }

        return $sqlBuilder(
            $this->state,
            $this->collector,
            $prepared
        );
    }
}
