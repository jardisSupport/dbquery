<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Data\Contract;

/**
 * Interface for states that support LIMIT clause building
 *
 * Required by LimitBuilder to access limit and offset information.
 */
interface LimitStateInterface
{
    /**
     * Get the LIMIT value
     *
     * @return int|null
     */
    public function getLimit(): ?int;

    /**
     * Get the OFFSET value
     *
     * @return int|null
     */
    public function getOffset(): ?int;

    /**
     * Set the LIMIT and optional OFFSET values
     *
     * @param int|null $limit
     * @param int|null $offset
     * @return void
     */
    public function setLimit(?int $limit, ?int $offset = null): void;
}
