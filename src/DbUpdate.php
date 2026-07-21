<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery;

use InvalidArgumentException;
use JardisSupport\DbQuery\Data\QueryConditionCollector;
use JardisSupport\DbQuery\Data\UpdateState;
use JardisSupport\DbQuery\Query\Condition\QueryCondition;
use JardisSupport\DbQuery\Query\Condition\QueryJsonCondition;
use JardisSupport\DbQuery\Query\Builder\Method;
use JardisSupport\DbQuery\Command\Update\Method as updateMethod;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use JardisSupport\DbQuery\Factory\SqlBuilderFactory;
use JardisSupport\DbQuery\Query\Validator\QueryBracketValidator;
use JardisSupport\Contract\DbQuery\DbDeleteBuilderInterface;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\DbQueryConditionBuilderInterface;
use JardisSupport\Contract\DbQuery\DbQueryJsonConditionBuilderInterface;
use JardisSupport\Contract\DbQuery\DbUpdateBuilderInterface;
use JardisSupport\Contract\DbQuery\ExpressionInterface;
use UnexpectedValueException;

/**
 * Provides methods for creating and managing SQL UPDATE commands.
 *
 * This class implements DbUpdateBuilderInterface to facilitate
 * UPDATE command building with a fluent interface.
 *
 * Supports:
 * - SET operations with scalar values and subqueries
 * - WHERE conditions (standard and JSON)
 * - JOINs (MySQL/MariaDB)
 * - ORDER BY and LIMIT (MySQL/MariaDB)
 */
class DbUpdate implements DbUpdateBuilderInterface
{
    private UpdateState $state;
    private QueryConditionCollector $collector;
    private QueryCondition $queryCondition;
    private QueryJsonCondition $queryJsonCondition;
    private BuilderRegistry $registry;

    public function __construct()
    {
        $this->registry = new BuilderRegistry();
        $this->state = new UpdateState();
        $this->collector = new QueryConditionCollector();
        $this->queryCondition = new QueryCondition($this, $this->collector, $this->registry);
        $this->queryJsonCondition = new QueryJsonCondition($this, $this->collector, $this->registry);
    }

    /**
     * Specifies the table to update
     *
     * @param string $container The table name
     * @param string|null $alias Optional alias for the table
     * @return self
     */
    public function table(string $container, ?string $alias = null): self
    {
        $this->state->setTable($container);
        $this->state->setAlias($alias);
        return $this;
    }

    /**
     * Sets a single column to a specific value
     *
     * @param string $field The column name to update
     * @param string|int|float|bool|null|DbQueryBuilderInterface|ExpressionInterface $value The value to set
     * @return self
     */
    public function set(
        string $field,
        string|int|float|bool|null|DbQueryBuilderInterface|ExpressionInterface $value
    ): self {
        $this->state->setColumn($field, $value);
        return $this;
    }

    /**
     * Sets multiple columns using an associative array
     *
     * @param array<string, string|int|float|bool|null|DbQueryBuilderInterface> $data array of column => value pairs
     * @return self
     */
    public function setMultiple(array $data): self
    {
        $this->state->setMultipleColumns($data);
        return $this;
    }

    /**
     * Starts a WHERE condition clause
     *
     * @param string|ExpressionInterface|null $field The column name or expression to compare
     * @param string|null $openBracket Optional opening bracket(s) for grouping conditions
     * @return DbQueryConditionBuilderInterface
     */
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

    /**
     * Adds an AND condition to the WHERE clause
     *
     * @param string|ExpressionInterface|null $field The column name or expression to compare
     * @param string|null $openBracket Optional opening bracket(s) for grouping conditions
     * @return DbQueryConditionBuilderInterface
     */
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
            false  // DbUpdate does NOT support HAVING
        );
    }

    /**
     * Adds an OR condition to the WHERE clause
     *
     * @param string|ExpressionInterface|null $field The column name or expression to compare
     * @param string|null $openBracket Optional opening bracket(s) for grouping conditions
     * @return DbQueryConditionBuilderInterface
     */
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
            false  // DbUpdate does NOT support HAVING
        );
    }

    /**
     * Starts a JSON-specific WHERE condition
     *
     * @param string $field The JSON column name
     * @param string|null $openBracket Optional opening bracket(s)
     * @return DbQueryJsonConditionBuilderInterface
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
     * @param string $field The JSON column name
     * @param string|null $openBracket Optional opening bracket(s)
     * @return DbQueryJsonConditionBuilderInterface
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
     * @param string $field The JSON column name
     * @param string|null $openBracket Optional opening bracket(s)
     * @return DbQueryJsonConditionBuilderInterface
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
     * Adds an EXISTS condition to the query.
     *
     * @param DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $query
     * @param string|null $closeBracket Optional closing bracket for the condition
     * @return DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface
     */
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

    /**
     * Adds a "NOT EXISTS" condition to the query builder.
     *
     * @param DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $query
     * @param string|null $closeBracket Optional closing bracket for the condition.
     * @return DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface
     */
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
     * Adds an INNER JOIN clause
     *
     * @param string|DbQueryBuilderInterface $container The table name or subquery to join
     * @param string $constraint The join condition
     * @param string|null $alias Optional alias for the joined table
     * @return $this Returns this update builder for method chaining
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
     * Adds a LEFT JOIN clause
     *
     * @param string|DbQueryBuilderInterface $container The table name or subquery to join
     * @param string $constraint The join condition
     * @param string|null $alias Optional alias for the joined table
     * @return $this Returns this update builder for method chaining
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
     * Specifies the column and direction for sorting results
     *
     * @param string $field The column name to sort by
     * @param string $direction The sort direction: 'ASC' or 'DESC'
     * @return $this Returns this update builder for method chaining
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
     * Limits the number of rows affected
     *
     * @param int|null $limit Maximum number of rows to affect
     * @param int|null $offset Ignored in UPDATE (not supported)
     * @return $this Returns this update builder for method chaining
     */
    public function limit(?int $limit = null, ?int $offset = null): self
    {
        return $this->registry->get(Method\Limit::class)(
            $this->state,
            $this,
            $limit,
            $offset
        );
    }

    /**
     * Silently ignores errors during UPDATE operation
     *
     * @return $this Returns this update builder for method chaining
     */
    public function ignore(): self
    {
        return $this->registry->get(updateMethod\Ignore::class)(
            $this->state,
            $this
        );
    }

    /**
     * Generate UPDATE SQL
     *
     * @param string $dialect The SQL dialect (mysql, postgres, sqlite)
     * @param bool $prepared Whether to generate a prepared statement (default: true)
     * @param string|null $version Database version (e.g., '5.7', '8.0'). Uses default if null.
     * @return string|DbPreparedQueryInterface
     * @throws InvalidArgumentException If validation fails
     */
    public function sql(
        string $dialect,
        bool $prepared = true,
        ?string $version = null
    ): string|DbPreparedQueryInterface {
        // Validate state
        if (empty($this->state->getTable())) {
            throw new InvalidArgumentException('Table name must be specified with table()');
        }

        if (empty($this->state->getSetData())) {
            throw new InvalidArgumentException('At least one column must be set with set() or setMultiple()');
        }

        $validator = new QueryBracketValidator();
        if (
            !$validator->hasValidBrackets(
                $this->collector->whereConditions(),
                $this->collector->havingConditions()
            )
        ) {
            throw new UnexpectedValueException('Invalid brackets in query');
        }

        $sqlBuilder = SqlBuilderFactory::createUpdate($dialect, $version);

        return $sqlBuilder(
            $this->state,
            $this->collector,
            $prepared
        );
    }
}
