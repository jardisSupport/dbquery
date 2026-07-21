<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Command\Insert\Method;

use JardisSupport\DbQuery\Data\InsertState;
use JardisSupport\Contract\DbQuery\DbInsertBuilderInterface;

/**
 * Handles REPLACE modifier for INSERT statements
 *
 * Replaces existing row on duplicate key (DELETE then INSERT operation).
 */
class Replace
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
        $state->setReplace(true);

        return $builder;
    }
}
