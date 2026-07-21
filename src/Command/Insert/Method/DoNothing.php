<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Command\Insert\Method;

use JardisSupport\DbQuery\Data\InsertState;
use JardisSupport\Contract\DbQuery\DbInsertBuilderInterface;

/**
 * Handles DO NOTHING action for ON CONFLICT clause
 *
 * Silently ignores conflicts without updating when a conflict occurs in PostgreSQL/SQLite upserts.
 */
class DoNothing
{
    /**
     * @template T of DbInsertBuilderInterface
     * @param T $builder
     * @return T
     */
    public function __invoke(
        InsertState $state,
        DbInsertBuilderInterface $builder
    ): DbInsertBuilderInterface {
        $state->setDoNothing(true);

        return $builder;
    }
}
