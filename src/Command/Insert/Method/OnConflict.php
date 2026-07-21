<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Command\Insert\Method;

use JardisSupport\DbQuery\Data\InsertState;
use JardisSupport\Contract\DbQuery\DbInsertBuilderInterface;

/**
 * Handles ON CONFLICT clause configuration for INSERT statements
 *
 * Sets the columns or constraint to check for conflicts in PostgreSQL/SQLite style upserts.
 */
class OnConflict
{
    /**
     * @template T of DbInsertBuilderInterface
     * @param T $builder
     * @return T
     */
    public function __invoke(
        InsertState $state,
        DbInsertBuilderInterface $builder,
        string ...$columnsOrConstraint
    ): DbInsertBuilderInterface {
        $state->setOnConflictColumns(array_values($columnsOrConstraint));

        return $builder;
    }
}
