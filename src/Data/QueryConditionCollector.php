<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Data;

use JardisSupport\Contract\DbQuery\DbDeleteBuilderInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\DbUpdateBuilderInterface;

/**
 * Central Collector for Query Conditions and Bindings
 *
 * Collects WHERE, HAVING and EXISTS conditions along with their parameter bindings.
 * Provides unique parameter name generation for safe query building.
 *
 * This class maintains chronological order of all conditions regardless of type
 * (standard, JSON, etc.) and manages parameter bindings centrally.
 */
class QueryConditionCollector
{
    /** @var array<int, string|array{
     *     type: string,
     *     container: DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface,
     *     closeBracket: ?string
     * }> WHERE conditions
     */
    private array $whereConditions = [];

    /** @var array<int, string|array{
     *     type: string,
     *     container: DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface,
     *     closeBracket: ?string
     * }> HAVING conditions
     */
    private array $havingConditions = [];

    /** @var array<int, mixed> Parameter bindings for prepared statements (indexed array) */
    private array $bindings = [];

    /**
     * Generates a positional parameter placeholder
     *
     * Gibt immer '?' zurück für positional parameters.
     * Dies garantiert deterministisches SQL über Requests hinweg,
     * da die Query-Struktur (nicht die Parameter-Namen) das SQL definiert.
     *
     * @return string The positional parameter placeholder '?'
     */
    public function generateParamName(): string
    {
        return '?';
    }

    // ==================== WHERE Conditions ====================

    /**
     * Adds a WHERE condition to the collection
     *
     * @param string $condition The complete condition string
     * @return void
     */
    public function addWhereCondition(string $condition): void
    {
        if ($condition === '') {
            return;
        }

        $this->whereConditions[] = $condition;
    }

    /**
     * Returns all collected WHERE conditions
     *
     * @return array<int, string|array{
     *     type: string,
     *     container: DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface,
     *     closeBracket: ?string
     * }>
     */
    public function whereConditions(): array
    {
        return $this->whereConditions;
    }

    // ==================== HAVING Conditions ====================

    /**
     * Adds a HAVING condition to the collection
     *
     * @param string $condition The complete condition string
     * @return void
     */
    public function addHavingCondition(string $condition): void
    {
        if ($condition === '') {
            return;
        }

        $this->havingConditions[] = $condition;
    }

    /**
     * Returns all collected HAVING conditions
     *
     * @return array<int, string|array{
     *     type: string,
     *     container: DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface,
     *     closeBracket: ?string
     * }>
     */
    public function havingConditions(): array
    {
        return $this->havingConditions;
    }

    // ==================== EXISTS Conditions ====================

    /**
     * Adds an EXISTS or NOT EXISTS condition
     *
     * @param string $type Type of condition (EXISTS or NOT EXISTS)
     * @param DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $query The subquery
     * @param string|null $closeBracket Optional closing bracket(s)
     * @return void
     */
    public function addExistsCondition(
        string $type,
        DbQueryBuilderInterface|DbUpdateBuilderInterface|DbDeleteBuilderInterface $query,
        ?string $closeBracket = null
    ): void {
        $this->whereConditions[] = [
            'type' => $type,
            'container' => $query,
            'closeBracket' => $closeBracket
        ];
    }

    // ==================== Bindings ====================

    /**
     * Adds a binding for a parameter
     *
     * Bei positional parameters wird der Wert einfach ans Array angehängt.
     * Die Reihenfolge der Bindings entspricht der Reihenfolge der ? im SQL.
     *
     * @param mixed $value The value to bind
     * @return void
     */
    public function addBinding(mixed $value): void
    {
        $this->bindings[] = $value;
    }

    /**
     * Returns all collected bindings
     *
     * @return array<int, mixed> Indexed array of binding values
     */
    public function bindings(): array
    {
        return $this->bindings;
    }
}
