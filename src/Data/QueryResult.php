<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Data;

use JardisSupport\Contract\DbQuery\QueryResultInterface;

/**
 * Result of a SQL query (SELECT).
 */
readonly class QueryResult implements QueryResultInterface
{
    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(
        private array $rows
    ) {
    }

    public function fetchAll(): array
    {
        return $this->rows;
    }

    public function fetchOne(): ?array
    {
        return $this->rows[0] ?? null;
    }

    public function rowCount(): int
    {
        return count($this->rows);
    }
}
