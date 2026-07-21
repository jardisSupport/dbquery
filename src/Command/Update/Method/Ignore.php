<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Command\Update\Method;

use JardisSupport\DbQuery\Data\UpdateState;
use JardisSupport\Contract\DbQuery\DbUpdateBuilderInterface;

/**
 * Handles IGNORE modifier for UPDATE statements
 *
 * Silently ignores errors that would otherwise cause the update to fail (MySQL-specific).
 */
class Ignore
{
    /**
     * @template T of DbUpdateBuilderInterface
     * @param T $builder
     * @return T
     */
    public function __invoke(
        UpdateState $state,
        DbUpdateBuilderInterface $builder
    ): DbUpdateBuilderInterface {
        $state->setIgnore(true);

        return $builder;
    }
}
