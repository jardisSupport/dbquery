<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Query\Processor;

use JardisSupport\DbQuery\Query\Processor\JsonPlaceholderProcessor;
use PHPUnit\Framework\TestCase;

/**
 * Tests for JsonPlaceholderProcessor class
 *
 * Coverage:
 * - JSON_EXTRACT placeholder processing
 * - JSON_CONTAINS placeholder processing (with and without path)
 * - JSON_NOT_CONTAINS placeholder processing (with and without path)
 * - JSON_LENGTH placeholder processing (with and without path)
 * - Multiple placeholder types in same condition
 * - Callback-based SQL generation
 * - Edge cases and special characters
 */
class JsonPlaceholderProcessorTest extends TestCase
{
    private JsonPlaceholderProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new JsonPlaceholderProcessor();
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withJsonExtract_replacesWithCallback(): void
    {
        $condition = 'data{{JSON_EXTRACT::$.age}} > 25';

        $buildJsonExtract = function ($column, $path) {
            return "JSON_EXTRACT($column, '$path')";
        };
        $buildJsonContains = fn() => '';
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = fn() => '';

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame("JSON_EXTRACT(data, '\$.age') > 25", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withJsonExtractComplexPath_handlesCorrectly(): void
    {
        $condition = 'profile{{JSON_EXTRACT::$.address.city}} = ?';

        $buildJsonExtract = function ($column, $path) {
            return "JSON_EXTRACT($column, '$path')";
        };
        $buildJsonContains = fn() => '';
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = fn() => '';

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame("JSON_EXTRACT(profile, '\$.address.city') = ?", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withJsonExtractArrayPath_handlesCorrectly(): void
    {
        $condition = 'data{{JSON_EXTRACT::$.items[0].name}} LIKE ?';

        $buildJsonExtract = function ($column, $path) {
            return "JSON_EXTRACT($column, '$path')";
        };
        $buildJsonContains = fn() => '';
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = fn() => '';

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame("JSON_EXTRACT(data, '\$.items[0].name') LIKE ?", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withJsonContainsWithPath_replacesWithCallback(): void
    {
        $condition = 'data {{JSON_CONTAINS::?::$.tags}} AND active = 1';

        $buildJsonExtract = fn() => '';
        $buildJsonContains = function ($column, $value, $path) {
            return "JSON_CONTAINS($column, $value, '$path')";
        };
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = fn() => '';

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame("JSON_CONTAINS(data, ?, '\$.tags') AND active = 1", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withJsonContainsWithoutPath_replacesWithCallback(): void
    {
        $condition = 'data {{JSON_CONTAINS::?}}';

        $buildJsonExtract = fn() => '';
        $buildJsonContains = function ($column, $value, $path) {
            return $path ? "JSON_CONTAINS($column, $value, '$path')" : "JSON_CONTAINS($column, $value)";
        };
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = fn() => '';

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame('JSON_CONTAINS(data, ?)', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withJsonNotContainsWithPath_replacesWithCallback(): void
    {
        $condition = 'profile {{JSON_NOT_CONTAINS::?::$.preferences}}';

        $buildJsonExtract = fn() => '';
        $buildJsonContains = fn() => '';
        $buildJsonNotContains = function ($column, $value, $path) {
            return "NOT JSON_CONTAINS($column, $value, '$path')";
        };
        $buildJsonLength = fn() => '';

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame("NOT JSON_CONTAINS(profile, ?, '\$.preferences')", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withJsonNotContainsWithoutPath_replacesWithCallback(): void
    {
        $condition = 'data {{JSON_NOT_CONTAINS::?}}';

        $buildJsonExtract = fn() => '';
        $buildJsonContains = fn() => '';
        $buildJsonNotContains = function ($column, $value, $path) {
            return $path ? "NOT JSON_CONTAINS($column, $value, '$path')" : "NOT JSON_CONTAINS($column, $value)";
        };
        $buildJsonLength = fn() => '';

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame('NOT JSON_CONTAINS(data, ?)', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withJsonLengthWithPath_replacesWithCallback(): void
    {
        $condition = 'data{{JSON_LENGTH::$.items}} > 5';

        $buildJsonExtract = fn() => '';
        $buildJsonContains = fn() => '';
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = function ($column, $path) {
            return $path ? "JSON_LENGTH($column, '$path')" : "JSON_LENGTH($column)";
        };

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame("JSON_LENGTH(data, '\$.items') > 5", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withJsonLengthWithoutPath_replacesWithCallback(): void
    {
        $condition = 'data{{JSON_LENGTH}} = 10';

        $buildJsonExtract = fn() => '';
        $buildJsonContains = fn() => '';
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = function ($column, $path) {
            return $path ? "JSON_LENGTH($column, '$path')" : "JSON_LENGTH($column)";
        };

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame('JSON_LENGTH(data) = 10', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withMultiplePlaceholders_replacesAll(): void
    {
        $condition = 'data{{JSON_EXTRACT::$.age}} > 18 AND tags {{JSON_CONTAINS::?::$.active}} AND items{{JSON_LENGTH}} > 0';

        $buildJsonExtract = function ($column, $path) {
            return "JSON_EXTRACT($column, '$path')";
        };
        $buildJsonContains = function ($column, $value, $path) {
            return "JSON_CONTAINS($column, $value, '$path')";
        };
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = function ($column, $path) {
            return $path ? "JSON_LENGTH($column, '$path')" : "JSON_LENGTH($column)";
        };

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $expected = "JSON_EXTRACT(data, '\$.age') > 18 AND JSON_CONTAINS(tags, ?, '\$.active') AND JSON_LENGTH(items) > 0";
        $this->assertSame($expected, $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withNoPlaceholders_returnsUnchanged(): void
    {
        $condition = 'id = ? AND name = ? AND active = 1';

        $buildJsonExtract = fn() => '';
        $buildJsonContains = fn() => '';
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = fn() => '';

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame($condition, $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withMySqlDialect_generatesCorrectSql(): void
    {
        $condition = 'data{{JSON_EXTRACT::$.name}} = ?';

        // MySQL uses JSON_EXTRACT function
        $buildJsonExtract = function ($column, $path) {
            return "JSON_EXTRACT(`$column`, '$path')";
        };
        $buildJsonContains = fn() => '';
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = fn() => '';

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame("JSON_EXTRACT(`data`, '\$.name') = ?", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withPostgresDialect_generatesCorrectSql(): void
    {
        $condition = 'data{{JSON_EXTRACT::$.name}} = ?';

        // PostgreSQL uses ->> operator
        $buildJsonExtract = function ($column, $path) {
            $path = ltrim($path, '$.');
            return "\"$column\"->>'$path'";
        };
        $buildJsonContains = fn() => '';
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = fn() => '';

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame('"data"->>' . "'name' = ?", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withMultipleJsonExtract_replacesAll(): void
    {
        $condition = 'data{{JSON_EXTRACT::$.age}} > 18 AND profile{{JSON_EXTRACT::$.city}} = ?';

        $buildJsonExtract = function ($column, $path) {
            return "JSON_EXTRACT($column, '$path')";
        };
        $buildJsonContains = fn() => '';
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = fn() => '';

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame("JSON_EXTRACT(data, '\$.age') > 18 AND JSON_EXTRACT(profile, '\$.city') = ?", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withMultipleJsonContains_replacesAll(): void
    {
        $condition = 'tags {{JSON_CONTAINS::?::$.items}} AND flags {{JSON_CONTAINS::?}}';

        $buildJsonExtract = fn() => '';
        $buildJsonContains = function ($column, $value, $path) {
            return $path ? "JSON_CONTAINS($column, $value, '$path')" : "JSON_CONTAINS($column, $value)";
        };
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = fn() => '';

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame("JSON_CONTAINS(tags, ?, '\$.items') AND JSON_CONTAINS(flags, ?)", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withMixedContainsAndNotContains_replacesCorrectly(): void
    {
        $condition = 'tags {{JSON_CONTAINS::?}} AND blocked {{JSON_NOT_CONTAINS::?}}';

        $buildJsonExtract = fn() => '';
        $buildJsonContains = function ($column, $value, $path) {
            return "JSON_CONTAINS($column, $value)";
        };
        $buildJsonNotContains = function ($column, $value, $path) {
            return "NOT JSON_CONTAINS($column, $value)";
        };
        $buildJsonLength = fn() => '';

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame('JSON_CONTAINS(tags, ?) AND NOT JSON_CONTAINS(blocked, ?)', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withNestedJsonPath_handlesCorrectly(): void
    {
        $condition = 'data{{JSON_EXTRACT::$.user.profile.settings.theme}} = ?';

        $buildJsonExtract = function ($column, $path) {
            return "JSON_EXTRACT($column, '$path')";
        };
        $buildJsonContains = fn() => '';
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = fn() => '';

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame("JSON_EXTRACT(data, '\$.user.profile.settings.theme') = ?", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withArrayIndexPath_handlesCorrectly(): void
    {
        $condition = 'data{{JSON_EXTRACT::$.items[2].price}} > 100';

        $buildJsonExtract = function ($column, $path) {
            return "JSON_EXTRACT($column, '$path')";
        };
        $buildJsonContains = fn() => '';
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = fn() => '';

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame("JSON_EXTRACT(data, '\$.items[2].price') > 100", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withWildcardPath_handlesCorrectly(): void
    {
        $condition = 'data{{JSON_EXTRACT::$.items[*].name}} LIKE ?';

        $buildJsonExtract = function ($column, $path) {
            return "JSON_EXTRACT($column, '$path')";
        };
        $buildJsonContains = fn() => '';
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = fn() => '';

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame("JSON_EXTRACT(data, '\$.items[*].name') LIKE ?", $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withAllPlaceholderTypes_replacesAll(): void
    {
        $condition = 'data{{JSON_EXTRACT::$.age}} > 18 AND tags {{JSON_CONTAINS::?::$.active}} AND blocked {{JSON_NOT_CONTAINS::?}} AND items{{JSON_LENGTH::$.list}} > 0';

        $buildJsonExtract = function ($column, $path) {
            return "JSON_EXTRACT($column, '$path')";
        };
        $buildJsonContains = function ($column, $value, $path) {
            return $path ? "JSON_CONTAINS($column, $value, '$path')" : "JSON_CONTAINS($column, $value)";
        };
        $buildJsonNotContains = function ($column, $value, $path) {
            return $path ? "NOT JSON_CONTAINS($column, $value, '$path')" : "NOT JSON_CONTAINS($column, $value)";
        };
        $buildJsonLength = function ($column, $path) {
            return $path ? "JSON_LENGTH($column, '$path')" : "JSON_LENGTH($column)";
        };

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $expected = "JSON_EXTRACT(data, '\$.age') > 18 AND JSON_CONTAINS(tags, ?, '\$.active') AND NOT JSON_CONTAINS(blocked, ?) AND JSON_LENGTH(items, '\$.list') > 0";
        $this->assertSame($expected, $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withEmptyCondition_returnsEmpty(): void
    {
        $condition = '';

        $buildJsonExtract = fn() => '';
        $buildJsonContains = fn() => '';
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = fn() => '';

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $this->assertSame('', $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withComplexCondition_handlesCorrectly(): void
    {
        $condition = '(data{{JSON_EXTRACT::$.age}} BETWEEN 18 AND 65) OR (data{{JSON_LENGTH::$.items}} > 10 AND tags {{JSON_CONTAINS::?::$.premium}})';

        $buildJsonExtract = function ($column, $path) {
            return "JSON_EXTRACT($column, '$path')";
        };
        $buildJsonContains = function ($column, $value, $path) {
            return "JSON_CONTAINS($column, $value, '$path')";
        };
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = function ($column, $path) {
            return "JSON_LENGTH($column, '$path')";
        };

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $expected = "(JSON_EXTRACT(data, '\$.age') BETWEEN 18 AND 65) OR (JSON_LENGTH(data, '\$.items') > 10 AND JSON_CONTAINS(tags, ?, '\$.premium'))";
        $this->assertSame($expected, $result);
    }

    /**
     * @test
     * @group unit
     * @group support
     * @group processor
     */
    public function invoke_withSameColumnMultipleTimes_replacesAll(): void
    {
        $condition = 'data{{JSON_EXTRACT::$.age}} > 18 AND data{{JSON_EXTRACT::$.city}} = ? AND data{{JSON_LENGTH}} > 5';

        $buildJsonExtract = function ($column, $path) {
            return "JSON_EXTRACT($column, '$path')";
        };
        $buildJsonContains = fn() => '';
        $buildJsonNotContains = fn() => '';
        $buildJsonLength = function ($column, $path) {
            return $path ? "JSON_LENGTH($column, '$path')" : "JSON_LENGTH($column)";
        };

        $result = ($this->processor)(
            $condition,
            $buildJsonExtract,
            $buildJsonContains,
            $buildJsonNotContains,
            $buildJsonLength
        );

        $expected = "JSON_EXTRACT(data, '\$.age') > 18 AND JSON_EXTRACT(data, '\$.city') = ? AND JSON_LENGTH(data) > 5";
        $this->assertSame($expected, $result);
    }

}
