<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Static;

use JardisSupport\Contract\DbQuery\DbDeleteBuilderInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\DbUpdateBuilderInterface;
use JardisSupport\DbQuery\Data\LikePattern;

/**
 * Static type check (analysed by `make phpstan`, never executed).
 *
 * Every call below goes through the CONTRACT interfaces only - exactly what a
 * consumer typed against `jardissupport/contracts` writes. If the contract's
 * `@param` for like()/notLike() does not name the LIKE-pattern type, phpstan
 * reports `argument.type` here although the runtime accepts the value.
 */
final class LikePatternContractTypeCheck
{
    public function select(DbQueryBuilderInterface $builder): void
    {
        $builder->where('name')->like(LikePattern::contains('x'));
        $builder->where('name')->notLike(LikePattern::startsWith('x'));
        $builder->where('name')->like(LikePattern::endsWith('x'))->or('code')->like(LikePattern::endsWith('y'));
        $builder->whereJson('settings')->extract('$.theme')->like(LikePattern::literal('x'));
        $builder->whereJson('settings')->extract('$.theme')->notLike(LikePattern::literal('x'));

        $builder->having('name')->like(LikePattern::contains('x'));
    }

    public function update(DbUpdateBuilderInterface $builder): void
    {
        $builder->where('name')->like(LikePattern::contains('x'));
    }

    public function delete(DbDeleteBuilderInterface $builder): void
    {
        $builder->where('name')->notLike(LikePattern::contains('x'));
    }
}
