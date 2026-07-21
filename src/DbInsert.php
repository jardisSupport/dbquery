<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery;

use InvalidArgumentException;
use JardisSupport\DbQuery\Data\InsertState;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use JardisSupport\DbQuery\Factory\SqlBuilderFactory;
use JardisSupport\DbQuery\Command\Insert\Method;
use JardisSupport\Contract\DbQuery\DbInsertBuilderInterface;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\ExpressionInterface;

/**
 * Provides methods for creating and managing SQL INSERT commands.
 *
 * This class implements DbInsertBuilderInterface to facilitate
 * INSERT command building with a fluent interface.
 *
 * Supports three modes:
 * 1. fields() + values() - For single or multi-row inserts
 * 2. set() - For single-row inserts with an associative array
 * 3. fromSelect() - For INSERT...SELECT operations
 */
class DbInsert implements DbInsertBuilderInterface
{
    private InsertState $state;
    /** @var array<int, mixed> */
    private array $bindings = [];
    private BuilderRegistry $registry;

    public function __construct()
    {
        $this->registry = new BuilderRegistry();
        $this->state = new InsertState();
    }

    public function into(string $container): self
    {
        $this->state->setTable($container);
        return $this;
    }

    public function fields(string ...$fields): self
    {
        $this->state->setFields(array_values($fields));
        return $this;
    }

    public function values(mixed ...$values): self
    {
        // Validate that columns are specified before adding values
        if (empty($this->state->getFields())) {
            throw new InvalidArgumentException(
                'Column names must be specified with columns() before adding values'
            );
        }

        // Validate that values count matches columns count
        if (count($values) !== count($this->state->getFields())) {
            throw new InvalidArgumentException(
                sprintf(
                    'Number of values (%d) does not match number of columns (%d)',
                    count($values),
                    count($this->state->getFields())
                )
            );
        }

        // Store values
        $this->state->addValueRow(array_values($values));

        // Add bindings for prepared statements
        foreach ($values as $value) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function set(array $data): self
    {
        // Clear any previous set() or values() data
        $this->state->setFields([]);
        $this->state->clearValueRows();
        $this->bindings = [];

        // Extract columns and values from an associative array
        $this->state->setFields(array_keys($data));
        $values = array_values($data);
        $this->state->addValueRow($values);

        // Add bindings for prepared statements
        foreach ($values as $value) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function fromSelect(DbQueryBuilderInterface $query): self
    {
        // Clear any previous values() or set() data
        $this->state->clearValueRows();
        $this->bindings = [];

        $this->state->setSelectQuery($query);

        return $this;
    }

    /**
     * MySQL/MariaDB: Handle duplicate key conflicts by updating existing rows
     *
     * @param string $field The column name to update on conflict
     * @param string|int|float|bool|null|ExpressionInterface $value The value to set
     * @return $this Returns this insert builder for method chaining
     */
    public function onDuplicateKeyUpdate(
        string $field,
        string|int|float|bool|null|ExpressionInterface $value
    ): self {
        return $this->registry->get(Method\OnDuplicateKeyUpdate::class)(
            $this->state,
            $this,
            $field,
            $value
        );
    }

    /**
     * SQLite: Silently ignore INSERT conflicts
     *
     * @return $this Returns this insert builder for method chaining
     */
    public function orIgnore(): self
    {
        return $this->registry->get(Method\OrIgnore::class)(
            $this->state,
            $this
        );
    }

    /**
     * SQLite: Replace existing row on conflict
     *
     * @return $this Returns this insert builder for method chaining
     */
    public function replace(): self
    {
        return $this->registry->get(Method\Replace::class)(
            $this->state,
            $this
        );
    }

    /**
     * PostgreSQL: Specify conflict target columns or constraint
     *
     * @param string ...$columnsOrConstraint Column names or constraint name
     * @return $this Returns this insert builder for method chaining
     */
    public function onConflict(string ...$columnsOrConstraint): self
    {
        return $this->registry->get(Method\OnConflict::class)(
            $this->state,
            $this,
            ...$columnsOrConstraint
        );
    }

    /**
     * PostgreSQL: Update specified fields on conflict
     *
     * @param array<string, mixed> $fields Associative array of field => value pairs to update
     * @return $this Returns this insert builder for method chaining
     */
    public function doUpdate(array $fields): self
    {
        return $this->registry->get(Method\DoUpdate::class)(
            $this->state,
            $this,
            $fields
        );
    }

    /**
     * PostgreSQL: Do nothing on conflict (silently ignore)
     *
     * @return $this Returns this insert builder for method chaining
     */
    public function doNothing(): self
    {
        return $this->registry->get(Method\DoNothing::class)(
            $this->state,
            $this
        );
    }

    /**
     * Generate INSERT SQL
     *
     * @param string $dialect The SQL dialect (mysql, postgres, sqlite)
     * @param bool $prepared Whether to generate a prepared statement (default: true)
     * @param string|null $version Database version (e.g., '5.7', '8.0'). Uses default if null.
     * @return string|DbPreparedQueryInterface
     * @throws InvalidArgumentException If a table is not set or invalid state
     */
    public function sql(
        string $dialect,
        bool $prepared = true,
        ?string $version = null
    ): string|DbPreparedQueryInterface {
        // Validate state
        if (empty($this->state->getTable())) {
            throw new InvalidArgumentException('Table name must be specified with into()');
        }

        // Validate that columns are specified (always required except when using set())
        if (empty($this->state->getFields())) {
            throw new InvalidArgumentException(
                'Column names must be specified with columns() or use set() instead'
            );
        }

        // Validate that wildcard '*' is not used (strict mode-explicit columns only)
        $columns = $this->state->getFields();
        if (count($columns) === 1 && $columns[0] === '*') {
            throw new InvalidArgumentException(
                'Wildcard \'*\' is not allowed. Specify explicit column names for safety and maintainability.'
            );
        }

        // Validate that we don't have both values and select a query (FIRST PRIORITY)
        if (!empty($this->state->getValueRows()) && $this->state->getSelectQuery() !== null) {
            throw new InvalidArgumentException(
                'Cannot use both values()/set() and fromSelect() in the same INSERT command'
            );
        }

        // Validate that we have either values or select a query
        if (empty($this->state->getValueRows()) && $this->state->getSelectQuery() === null) {
            throw new InvalidArgumentException(
                'Must specify values with values()/set() or a SELECT query with fromSelect()'
            );
        }

        // Validate that columns are specified (always required except when using set())
        $valueRows = $this->state->getValueRows();
        if (!empty($valueRows) && count($columns) !== count($valueRows[0])) {
            throw new InvalidArgumentException(
                'The number of columns must be equal to the number of values.'
            );
        }

        // Validate that the SELECT query doesn't use wildcard (strict mode)
        $selectQuery = $this->state->getSelectQuery();
        if ($selectQuery !== null) {
            // Generate SQL to check for wildcard
            $subquerySql = $selectQuery->sql($dialect, false);

            // Fast-path: Skip regex if no wildcard present
            // Performance: ~50% faster for queries without wildcards
            if (is_string($subquerySql) && str_contains($subquerySql, '*')) {
                if (preg_match('/SELECT\s+(?:DISTINCT\s+)?\*/i', $subquerySql)) {
                    throw new InvalidArgumentException(
                        'Wildcard \'*\' in SELECT subquery is not allowed. ' .
                        'Use explicit column names in your SELECT query.'
                    );
                }
            }
        }

        $sqlBuilder = SqlBuilderFactory::createInsert($dialect, $version);

        return $sqlBuilder(
            $this->state,
            $this->bindings,
            $prepared
        );
    }
}
