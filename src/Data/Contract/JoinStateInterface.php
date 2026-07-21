<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Data\Contract;

use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;

/**
 * Interface for states that support JOIN clause building
 *
 * Required by JoinBuilder to access join definitions.
 */
interface JoinStateInterface
{
    /**
     * Get all JOIN definitions
     *
     * @return array<int, array{
     *     join: string,
     *     container: string|DbQueryBuilderInterface,
     *     alias: ?string,
     *     constraint: ?string
     * }>
     */
    public function getJoins(): array;

    /**
     * Add a JOIN definition
     *
     * @param array{
     *     join: string,
     *     container: string|DbQueryBuilderInterface,
     *     alias: ?string,
     *     constraint: ?string
     * } $join
     * @return void
     */
    public function addJoin(array $join): void;
}
