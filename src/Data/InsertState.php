<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Data;

use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;

/**
 * Holds the complete state of a SQL INSERT command being built
 *
 * This class encapsulates all INSERT-specific components:
 * - Table name
 * - Column names
 * - Value rows (for INSERT...VALUES)
 * - SELECT query (for INSERT...SELECT)
 *
 * Provides typed getter/setter methods for safe data access.
 */
class InsertState
{
    private string $table = '';

    /** @var array<int, string> */
    private array $fields = [];

    /** @var array<int, array<int, mixed>> */
    private array $valueRows = [];

    private ?DbQueryBuilderInterface $selectQuery = null;

    private bool $orIgnore = false;
    private bool $replace = false;

    /** @var array<string, mixed> */
    private array $onDuplicateKeyUpdate = [];

    /** @var array<int, string> */
    private array $onConflictColumns = [];

    /** @var array<string, mixed> */
    private array $doUpdateFields = [];

    private bool $doNothing = false;

    // ==================== Getters ====================

    public function getTable(): string
    {
        return $this->table;
    }

    /** @return array<int, string> */
    public function getFields(): array
    {
        return $this->fields;
    }

    /** @return array<int, array<int, mixed>> */
    public function getValueRows(): array
    {
        return $this->valueRows;
    }

    public function getSelectQuery(): ?DbQueryBuilderInterface
    {
        return $this->selectQuery;
    }

    public function isOrIgnore(): bool
    {
        return $this->orIgnore;
    }

    public function isReplace(): bool
    {
        return $this->replace;
    }

    /** @return array<string, mixed> */
    public function getOnDuplicateKeyUpdate(): array
    {
        return $this->onDuplicateKeyUpdate;
    }

    /** @return array<int, string> */
    public function getOnConflictColumns(): array
    {
        return $this->onConflictColumns;
    }

    /** @return array<string, mixed> */
    public function getDoUpdateFields(): array
    {
        return $this->doUpdateFields;
    }

    public function isDoNothing(): bool
    {
        return $this->doNothing;
    }

    // ==================== Setters ====================

    public function setTable(string $table): void
    {
        $this->table = $table;
    }

    /**
     * Set field names
     *
     * @param array<int, string> $fields
     */
    public function setFields(array $fields): void
    {
        $this->fields = $fields;
    }

    /**
     * Add a row of values
     *
     * @param array<int, mixed> $values
     */
    public function addValueRow(array $values): void
    {
        $this->valueRows[] = $values;
    }

    /**
     * Clear all value rows
     */
    public function clearValueRows(): void
    {
        $this->valueRows = [];
    }

    /**
     * Set SELECT query for INSERT...SELECT
     *
     * @param DbQueryBuilderInterface|null $query
     */
    public function setSelectQuery(?DbQueryBuilderInterface $query): void
    {
        $this->selectQuery = $query;
    }

    public function setOrIgnore(bool $orIgnore): void
    {
        $this->orIgnore = $orIgnore;
        if ($orIgnore) {
            $this->replace = false;
        }
    }

    public function setReplace(bool $replace): void
    {
        $this->replace = $replace;
        if ($replace) {
            $this->orIgnore = false;
        }
    }

    public function addOnDuplicateKeyUpdate(string $field, mixed $value): void
    {
        $this->onDuplicateKeyUpdate[$field] = $value;
    }

    /**
     * Set ON CONFLICT columns
     *
     * @param array<int, string> $columns
     */
    public function setOnConflictColumns(array $columns): void
    {
        $this->onConflictColumns = $columns;
    }

    /**
     * Set DO UPDATE fields
     *
     * @param array<string, mixed> $fields
     */
    public function setDoUpdateFields(array $fields): void
    {
        $this->doUpdateFields = $fields;
    }

    public function setDoNothing(bool $doNothing): void
    {
        $this->doNothing = $doNothing;
    }
}
