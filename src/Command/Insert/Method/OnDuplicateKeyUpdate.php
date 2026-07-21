<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Command\Insert\Method;

use JardisSupport\DbQuery\Data\InsertState;
use JardisSupport\Contract\DbQuery\DbInsertBuilderInterface;
use JardisSupport\Contract\DbQuery\ExpressionInterface;

/**
 * Handles ON DUPLICATE KEY UPDATE clause for MySQL INSERT statements
 *
 * Updates specified fields when a duplicate key/unique constraint violation occurs (MySQL-specific).
 */
class OnDuplicateKeyUpdate
{
    /**
     * @template T of DbInsertBuilderInterface
     * @param T $builder
     * @return T
     */
    public function __invoke(
        InsertState $state,
        DbInsertBuilderInterface $builder,
        string $field,
        string|int|float|bool|null|ExpressionInterface $value
    ): DbInsertBuilderInterface {
        $state->addOnDuplicateKeyUpdate($field, $value);

        return $builder;
    }
}
