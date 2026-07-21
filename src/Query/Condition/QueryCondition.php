<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Query\Condition;

use JardisSupport\DbQuery\Data\QueryConditionCollector;
use JardisSupport\DbQuery\Factory\BuilderRegistry;
use JardisSupport\DbQuery\Query\Builder\Condition;
use JardisSupport\Contract\DbQuery\DbDeleteBuilderInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\DbQueryConditionBuilderInterface;
use JardisSupport\Contract\DbQuery\DbUpdateBuilderInterface;

/**
 * Class QueryCondition
 *
 * This class provides a set of methods to construct complex query conditions for database queries.
 * It supports various comparison operators, logical clauses, and condition types,
 * allowing for dynamic query building by chaining methods.
 */
class QueryCondition implements DbQueryConditionBuilderInterface
{
    private DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $queryBuilder;
    private QueryConditionCollector $collector;
    private BuilderRegistry $registry;
    private string $conditionPrefix = '';
    private string $conditionField = '';
    private string $currentCondition = '';
    private bool $isHavingCondition = false;

    /**
     * Initializes the class with a query builder and a condition collector.
     *
     * @param DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $builder
     *        The query builder instance for executing or constructing SQL queries.
     * @param QueryConditionCollector $collector
     *        The condition collector instance is used to manage query conditions.
     * @param BuilderRegistry $registry
     *        Instance-based builder registry for resolving condition builders.
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
     * Initializes a new condition with the given prefix and field
     *
     * @param string $prefix The condition prefix (e.g., ' WHERE ', ' AND (')
     * @param string $field The field name or expression
     * @param bool $isHaving Whether this is a HAVING condition
     * @return void
     */
    public function initCondition(string $prefix, ?string $field = null, bool $isHaving = false): void
    {
        $field ??= '';
        $this->conditionPrefix = $prefix;
        $this->conditionField = $field;
        $this->currentCondition = $prefix . $field;
        $this->isHavingCondition = $isHaving;
    }

    public function greater(
        mixed $value,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $compare = ' > ' . $this->getValue($value);
        return $this->buildCondition($compare, $closeBracket);
    }

    public function greaterEquals(
        mixed $value,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $compare = ' >= ' . $this->getValue($value);
        return $this->buildCondition($compare, $closeBracket);
    }

    public function lower(
        mixed $value,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $compare = ' < ' . $this->getValue($value);
        return $this->buildCondition($compare, $closeBracket);
    }

    public function lowerEquals(
        mixed $value,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $compare = ' <= ' . $this->getValue($value);
        return $this->buildCondition($compare, $closeBracket);
    }

    public function equals(
        mixed $value,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $compare = ' = ' . $this->getValue($value);
        return $this->buildCondition($compare, $closeBracket);
    }

    public function notEquals(
        mixed $value,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $compare = ' != ' . $this->getValue($value);
        return $this->buildCondition($compare, $closeBracket);
    }

    public function between(
        mixed $min,
        mixed $max,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $compare = ' BETWEEN ' . $this->getValue($min) .
                   ' AND ' . $this->getValue($max);

        return $this->buildCondition($compare, $closeBracket);
    }

    public function notBetween(
        mixed $min,
        mixed $max,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $compare = ' NOT BETWEEN ' . $this->getValue($min) .
                   ' AND ' . $this->getValue($max);

        return $this->buildCondition($compare, $closeBracket);
    }

    public function in(
        array|DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $value,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        if (is_array($value) && $value === []) {
            $this->currentCondition = $this->conditionPrefix
                . $this->extractLeadingBrackets() . '1=0';
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
            $this->currentCondition = $this->conditionPrefix
                . $this->extractLeadingBrackets() . '1=1';
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

    public function exists(
        DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $query,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $compare = ' EXISTS ' . $this->getValue($query);
        return $this->buildCondition($compare, $closeBracket);
    }

    public function notExists(
        DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $query,
        ?string $closeBracket = null
    ): DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface {
        $compare = ' NOT EXISTS ' . $this->getValue($query);
        return $this->buildCondition($compare, $closeBracket);
    }

    /**
     * Extracts leading opening brackets from the field name
     *
     * Fields may contain embedded brackets (e.g., '(id' from ->and('(id')).
     * These must be preserved when replacing the field with a boolean literal.
     *
     * @return string The leading brackets (e.g., '(' or '((') or empty string
     */
    private function extractLeadingBrackets(): string
    {
        $len = strlen($this->conditionField);
        $brackets = '';
        for ($i = 0; $i < $len && $this->conditionField[$i] === '('; $i++) {
            $brackets .= '(';
        }
        return $brackets;
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
