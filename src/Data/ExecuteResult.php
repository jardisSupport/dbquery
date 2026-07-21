<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Data;

use JardisSupport\Contract\DbQuery\ExecuteResultInterface;

/**
 * Result of a DML operation (INSERT, UPDATE, DELETE).
 */
readonly class ExecuteResult implements ExecuteResultInterface
{
    public function __construct(
        private int $affectedRows,
        private string|false $lastInsertId
    ) {
    }

    public function affectedRows(): int
    {
        return $this->affectedRows;
    }

    public function lastInsertId(): string|false
    {
        return $this->lastInsertId;
    }
}
