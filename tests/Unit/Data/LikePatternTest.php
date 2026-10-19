<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Data;

use JardisSupport\DbQuery\Data\LikePattern;
use JardisSupport\DbQuery\DbDelete;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\DbUpdate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LikePatternTest extends TestCase
{
    public function testEscapesWildcardsAndTheEscapeCharacter(): void
    {
        $this->assertSame('%50!%!_!!x%', LikePattern::contains('50%_!x')->pattern());
        $this->assertSame('a!_b%', LikePattern::startsWith('a_b')->pattern());
        $this->assertSame('%a!%', LikePattern::endsWith('a%')->pattern());
        $this->assertSame('a!_b', LikePattern::literal('a_b')->pattern());
        $this->assertSame('a\\b', LikePattern::literal('a\\b')->pattern());
    }

    /** @return array<string, array{string}> */
    public static function dialects(): array
    {
        return ['mysql' => ['mysql'], 'mariadb' => ['mariadb'], 'postgres' => ['postgres'], 'sqlite' => ['sqlite']];
    }

    #[DataProvider('dialects')]
    public function testLikeRendersEscapeClauseAndBindsThePattern(string $dialect): void
    {
        $prepared = (new DbQuery())->select('*')->from('t')
            ->where('name')->like(LikePattern::contains('50%'))
            ->sql($dialect);

        $this->assertStringContainsString("LIKE ? ESCAPE '!'", $prepared->sql());
        $this->assertSame(['%50!%%'], $prepared->bindings());
    }

    #[DataProvider('dialects')]
    public function testNotLikeAndInlineMode(string $dialect): void
    {
        $query = (new DbQuery())->select('*')->from('t')
            ->where('name')->notLike(LikePattern::startsWith('a_b'));

        $this->assertStringContainsString("NOT LIKE ? ESCAPE '!'", $query->sql($dialect)->sql());
        $this->assertStringContainsString("NOT LIKE 'a!_b%' ESCAPE '!'", $query->sql($dialect, false));
    }

    public function testWorksInUpdateDeleteAndHaving(): void
    {
        $update = (new DbUpdate())->table('t')->set('x', 1)
            ->where('name')->like(LikePattern::contains('a_b'))
            ->sql('mysql');
        $this->assertStringContainsString("LIKE ? ESCAPE '!'", $update->sql());

        $delete = (new DbDelete())->from('t')
            ->where('name')->like(LikePattern::contains('a_b'))
            ->sql('postgres');
        $this->assertStringContainsString("LIKE ? ESCAPE '!'", $delete->sql());

        $having = (new DbQuery())->select('name')->from('t')->groupBy('name')
            ->having('name')->like(LikePattern::contains('a_b'))
            ->sql('sqlite');
        $this->assertStringContainsString("LIKE ? ESCAPE '!'", $having->sql());
    }

    public function testWorksOnJsonConditions(): void
    {
        $prepared = (new DbQuery())->select('*')->from('t')
            ->whereJson('settings')->extract('$.theme')->like(LikePattern::contains('50%'))
            ->sql('mysql');
        $this->assertStringContainsString("LIKE ? ESCAPE '!'", $prepared->sql());
        $this->assertSame(['%50!%%'], $prepared->bindings());

        $negated = (new DbQuery())->select('*')->from('t')
            ->whereJson('settings')->extract('$.theme')->notLike(LikePattern::startsWith('a_'))
            ->sql('mysql');
        $this->assertStringContainsString("NOT LIKE ? ESCAPE '!'", $negated->sql());
    }

    public function testPlainLikeKeepsItsBehaviour(): void
    {
        $prepared = (new DbQuery())->select('*')->from('t')->where('name')->like('J%')->sql('mysql');

        $this->assertStringNotContainsString('ESCAPE', $prepared->sql());
        $this->assertSame(['J%'], $prepared->bindings());
    }
}
