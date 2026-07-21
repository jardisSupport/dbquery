<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Condition;

use JardisSupport\DbQuery\Data\QueryConditionCollector;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use JardisSupport\DbQuery\Query\Builder\Condition;
use JardisSupport\Contract\DbQuery\DbDeleteBuilderInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\DbQueryJsonConditionBuilderInterface;
use JardisSupport\Contract\DbQuery\DbUpdateBuilderInterface;

/**
 * JSON-specific query condition builder
 *
 * This class provides methods for building JSON-specific query conditions
 * such as JSON path extraction, containment checks, and length operations.
 */
class QueryJsonCondition implements DbQueryJsonConditionBuilderInterface
{
    private DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $queryBuilder;
    private QueryConditionCollector $collector;
    private BuilderRegistry $registry;
    private string $conditionPrefix = '';
    private string $currentCondition = '';
    private bool $isHavingCondition = false;

    /**
     * Constructor method to initialize the query builder and condition collector
     *
     * @param DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $builder query builder instance
     * @param QueryConditionCollector $collector The condition collector instance
     * @param BuilderRegistry $registry Instance-based builder registry for resolving condition builders
     * @return void
     */
    public function __construct(
        DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $builder,
        QueryConditionCollector $collector,
        BuilderRegistry $registry
    ) {
        $this->queryBuilder = $builder;
        $this->collector = $collector;
        $this->registry = $registry;
    }

    /**
     * Initializes a new JSON condition
     *
     * @param string $column The JSON column name
     * @param string $prefix The condition prefix (e.g., 'WHERE' or 'AND')
     * @param bool $isHaving Whether this is a HAVING condition
     * @return void
     */
    public function initCondition(string $column, string $prefix, bool $isHaving = false): void
    {
        $this->conditionPrefix = $prefix;
        $this->currentCondition = $prefix . $column;
        $this->isHavingCondition = $isHaving;
    }

    /**
     * Extracts a value from JSON using a path
     *
     * JSON paths are embedded directly into the SQL (not as parameters)
     * because they are structural information required at query compilation time.
     *
     * @param string $path JSON path (e.g., '$.age', '$.address.city')
     * @return self For method chaining
     */
    public function extract(string $path): self
    {
        $this->currentCondition .= '{{JSON_EXTRACT::' . $path . '}}';

        return $this;
    }

    /**
     * Checks if JSON contains a specific value
     *
     * The value is parameterized (?), but the path is embedded directly
     * as it's structural information needed at compilation time.
     *
     * @param mixed $value The value to search for
     * @param string|null $path Optional JSON path
     * @param string|null $closeBracket Optional closing bracket
     * @return DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface
     */
    public function contains(
        mixed $value,
        ?string $path = null,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $this->collector->addBinding($value);
        $valueName = $this->collector->generateParamName();

        if ($path !== null) {
            $this->currentCondition .= ' {{JSON_CONTAINS::' . $valueName . '::' . $path . '}}';
        } else {
            $this->currentCondition .= ' {{JSON_CONTAINS::' . $valueName . '}}';
        }

        $this->currentCondition .= $closeBracket ?? '';

        if ($this->isHavingCondition) {
            $this->collector->addHavingCondition($this->currentCondition);
        } else {
            $this->collector->addWhereCondition($this->currentCondition);
        }

        return $this->queryBuilder;
    }

    /**
     * Checks if JSON does NOT contain a specific value
     *
     * The value is parameterized (?), but the path is embedded directly.
     *
     * @param mixed $value The value to check absence of
     * @param string|null $path Optional JSON path
     * @param string|null $closeBracket Optional closing bracket
     * @return DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface
     */
    public function notContains(
        mixed $value,
        ?string $path = null,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $this->collector->addBinding($value);
        $valueName = $this->collector->generateParamName();

        if ($path !== null) {
            $this->currentCondition .= ' {{JSON_NOT_CONTAINS::' . $valueName . '::' . $path . '}}';
        } else {
            $this->currentCondition .= ' {{JSON_NOT_CONTAINS::' . $valueName . '}}';
        }

        $this->currentCondition .= $closeBracket ?? '';

        if ($this->isHavingCondition) {
            $this->collector->addHavingCondition($this->currentCondition);
        } else {
            $this->collector->addWhereCondition($this->currentCondition);
        }

        return $this->queryBuilder;
    }

    /**
     * Gets the length of a JSON array or object
     *
     * Path is embedded directly as structural information.
     *
     * @param string|null $path Optional JSON path
     * @return self For method chaining with comparison operators
     */
    public function length(?string $path = null): self
    {
        if ($path !== null) {
            $this->currentCondition .= '{{JSON_LENGTH::' . $path . '}}';
        } else {
            $this->currentCondition .= '{{JSON_LENGTH}}';
        }

        return $this;
    }

    // ==================== Comparison Operators ====================

    public function equals(
        mixed $value,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        return $this->addCondition(' = ', $value, $closeBracket);
    }

    public function notEquals(
        mixed $value,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        return $this->addCondition(' != ', $value, $closeBracket);
    }

    public function greater(
        mixed $value,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        return $this->addCondition(' > ', $value, $closeBracket);
    }

    public function greaterEquals(
        mixed $value,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        return $this->addCondition(' >= ', $value, $closeBracket);
    }

    public function lower(
        mixed $value,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        return $this->addCondition(' < ', $value, $closeBracket);
    }

    public function lowerEquals(
        mixed $value,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        return $this->addCondition(' <= ', $value, $closeBracket);
    }

    public function in(
        array|DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $value,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        if (is_array($value) && $value === []) {
            $this->currentCondition = $this->conditionPrefix . '1=0';
            return $this->buildCondition(null, $closeBracket);
        }
        $compare = ' IN ' . $this->getValue($value);
        return $this->buildCondition($compare, $closeBracket);
    }

    public function notIn(
        array|DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $value,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        if (is_array($value) && $value === []) {
            $this->currentCondition = $this->conditionPrefix . '1=1';
            return $this->buildCondition(null, $closeBracket);
        }
        $compare = ' NOT IN ' . $this->getValue($value);
        return $this->buildCondition($compare, $closeBracket);
    }

    public function like(
        mixed $value,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $compare = ' LIKE ' . $this->getValue($value);
        return $this->buildCondition($compare, $closeBracket);
    }

    public function notLike(
        mixed $value,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $compare = ' NOT LIKE ' . $this->getValue($value);
        return $this->buildCondition($compare, $closeBracket);
    }

    public function isNull(
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $compare = ' IS NULL';
        return $this->buildCondition($compare, $closeBracket);
    }

    public function isNotNull(
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $compare = ' IS NOT NULL';
        return $this->buildCondition($compare, $closeBracket);
    }

    /**
     * Processes and returns a formatted value for database queries
     *
     * @param mixed $value The value to be formatted
     * @return string The formatted value as a parameterized string or raw SQL
     */
    protected function getValue(mixed $value = null): string
    {
        $validator = $this->registry->get(Condition\ValueValidator::class);
        return $this->registry->get(Condition\ValueResolver::class)(
            $this->collector,
            $value,
            $validator
        );
    }

    /**
     * Helper method to add a comparison operator
     *
     * @param string $operator The comparison operator
     * @param mixed $value The value to compare
     * @param string|null $closeBracket Optional closing bracket
     * @return DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface
     */
    private function addCondition(
        string $operator,
        mixed $value,
        ?string $closeBracket
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $compare = $operator . $this->getValue($value);
        return $this->buildCondition($compare, $closeBracket);
    }

    /**
     * Builds and appends a query condition
     *
     * @param string $comparePart The comparison part of the condition
     * @param string|null $closeBracket Optional closing bracket
     * @return DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface The query builder instance
     */
    protected function buildCondition(
        ?string $comparePart,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        return $this->registry->get(Condition\ConditionBuilder::class)(
            $this->collector,
            $this->currentCondition,
            $comparePart,
            $closeBracket,
            $this->isHavingCondition,
            $this->queryBuilder
        );
    }
}
