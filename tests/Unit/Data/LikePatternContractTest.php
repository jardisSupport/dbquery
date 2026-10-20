<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Data;

use JardisSupport\Contract\DbQuery\DbComparisonOperatorsInterface;
use JardisSupport\Contract\DbQuery\DbDeleteBuilderInterface;
use JardisSupport\Contract\DbQuery\DbQueryBuilderInterface;
use JardisSupport\Contract\DbQuery\DbUpdateBuilderInterface;
use JardisSupport\Contract\DbQuery\LikePatternInterface;
use JardisSupport\DbQuery\Data\LikePattern;
use JardisSupport\DbQuery\DbDelete;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\DbUpdate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The LIKE value type is a contract type: every call below is typed against the
 * `jardissupport/contracts` interfaces only, the way a consumer writes it.
 * The static counterpart is tests/Static/LikePatternContractTypeCheck.php (phpstan).
 */
class LikePatternContractTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function dialects(): array
    {
        return ['mysql' => ['mysql'], 'mariadb' => ['mariadb'], 'postgres' => ['postgres'], 'sqlite' => ['sqlite']];
    }

    public function testLikePatternIsTheContractType(): void
    {
        $this->assertInstanceOf(LikePatternInterface::class, LikePattern::contains('x'));
    }

    #[DataProvider('dialects')]
    public function testLikeAndNotLikeThroughTheContractRenderEscapeClause(string $dialect): void
    {
        $builder = $this->select();
        $builder->where('name')->like(LikePattern::contains('50%'))
            ->and('code')->notLike(LikePattern::startsWith('a_b'));

        $prepared = $builder->sql($dialect);

        $this->assertStringContainsString("LIKE ? ESCAPE '!'", $prepared->sql());
        $this->assertStringContainsString("NOT LIKE ? ESCAPE '!'", $prepared->sql());
        $this->assertSame(['%50!%%', 'a!_b%'], $prepared->bindings());
    }

    #[DataProvider('dialects')]
    public function testAForeignImplementationOfTheContractIsEvaluatedLikeLikePattern(string $dialect): void
    {
        $foreign = new class implements LikePatternInterface {
            public function pattern(): string
            {
                return '%x#%%';
            }

            public function escapeClause(): string
            {
                return " ESCAPE '#'";
            }
        };

        $prepared = $this->select()->where('name')->like($foreign)->sql($dialect);

        $this->assertStringContainsString("LIKE ? ESCAPE '#'", $prepared->sql());
        $this->assertSame(['%x#%%'], $prepared->bindings());
    }

    public function testJsonHavingUpdateAndDeleteBuildersTakeTheContractTypeToo(): void
    {
        $query = $this->select();
        $query->whereJson('settings')->extract('$.theme')->like(LikePattern::contains('50%'));
        $this->assertStringContainsString("ESCAPE '!'", $query->sql('mysql')->sql());

        $having = $this->select();
        $having->having('name')->notLike(LikePattern::contains('x'));
        $this->assertStringContainsString("NOT LIKE ? ESCAPE '!'", $having->sql('sqlite')->sql());

        $update = $this->update();
        $update->where('name')->like(LikePattern::endsWith('x'));
        $this->assertStringContainsString("LIKE ? ESCAPE '!'", $update->sql('postgres')->sql());

        $delete = $this->delete();
        $delete->where('name')->notLike(LikePattern::literal('x'));
        $this->assertStringContainsString("NOT LIKE ? ESCAPE '!'", $delete->sql('mariadb')->sql());
    }

    public function testThePlainStringStillBindsWithoutEscapeClause(): void
    {
        $comparison = $this->select()->where('name');
        $this->assertInstanceOf(DbComparisonOperatorsInterface::class, $comparison);

        $prepared = $comparison->like('John%')->sql('mysql');

        $this->assertStringNotContainsString('ESCAPE', $prepared->sql());
        $this->assertSame(['John%'], $prepared->bindings());
    }

    private function select(): DbQueryBuilderInterface
    {
        return (new DbQuery())->select('*')->from('t');
    }

    private function update(): DbUpdateBuilderInterface
    {
        return (new DbUpdate())->table('t')->set('a', 1);
    }

    private function delete(): DbDeleteBuilderInterface
    {
        return (new DbDelete())->from('t');
    }
}
