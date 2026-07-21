<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Data\Contract;

/**
 * Interface for states that support ORDER BY clause building
 *
 * Required by OrderByBuilder to access ordering information.
 */
interface OrderByStateInterface
{
    /**
     * Get all ORDER BY clauses
     *
     * @return array<int, string>
     */
    public function getOrderBy(): array;

    /**
     * Add an ORDER BY clause
     *
     * @param string $field
     * @param string $direction
     * @return void
     */
    public function addOrderBy(string $field, string $direction): void;
}
