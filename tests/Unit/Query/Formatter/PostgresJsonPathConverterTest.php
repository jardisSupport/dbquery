<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Formatter;

use JardisSupport\DbQuery\Query\Formatter\PostgresJsonPathConverter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PostgresJsonPathConverter
 *
 * Covers JSON path conversion from MySQL notation ($.path) to PostgreSQL notation (path)
 */
class PostgresJsonPathConverterTest extends TestCase
{
    private PostgresJsonPathConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new PostgresJsonPathConverter();
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function convert_withDollarDotPrefix_removesPrefix(): void
    {
        $result = ($this->converter)('$.field');

        $this->assertEquals('field', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function convert_withDollarOnlyPrefix_removesPrefix(): void
    {
        $result = ($this->converter)('$field');

        $this->assertEquals('field', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function convert_withoutDollar_keepsPath(): void
    {
        $result = ($this->converter)('field');

        $this->assertEquals('field', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function convert_withNestedPath_removesPrefixOnly(): void
    {
        $result = ($this->converter)('$.user.profile.name');

        $this->assertEquals('user.profile.name', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function convert_withDeepNesting_preservesStructure(): void
    {
        $result = ($this->converter)('$.a.b.c.d.e');

        $this->assertEquals('a.b.c.d.e', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function convert_withEmptyString_returnsEmpty(): void
    {
        $result = ($this->converter)('');

        $this->assertEquals('', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group formatter
     */
    public function convert_withOnlyDollar_returnsEmpty(): void
    {
        $result = ($this->converter)('$');

        $this->assertEquals('', $result);
    }
}
