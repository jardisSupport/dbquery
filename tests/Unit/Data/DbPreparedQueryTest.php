<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Data;

use JardisSupport\DbQuery\Data\DbPreparedQuery;
use JardisSupport\Contract\DbQuery\DbPreparedQueryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for DbPreparedQuery
 *
 * Tests the prepared query implementation with SQL, bindings, and type.
 */
class DbPreparedQueryTest extends TestCase
{
    // ==================== Constructor Tests ====================

    public function testConstructorWithBasicParameters(): void
    {
        $sql = 'SELECT * FROM users WHERE id = :param1';
        $bindings = ['param1' => 123];
        $type = 'mysql';

        $query = new DbPreparedQuery($sql, $bindings, $type);

        $this->assertInstanceOf(DbPreparedQuery::class, $query);
        $this->assertInstanceOf(DbPreparedQueryInterface::class, $query);
    }

    public function testConstructorWithEmptyBindings(): void
    {
        $sql = 'SELECT * FROM users';
        $bindings = [];
        $type = 'postgres';

        $query = new DbPreparedQuery($sql, $bindings, $type);

        $this->assertInstanceOf(DbPreparedQuery::class, $query);
    }

    public function testConstructorWithComplexBindings(): void
    {
        $sql = 'INSERT INTO users (name, age, active, data) VALUES (:param1, :param2, :param3, :param4)';
        $bindings = [
            'param1' => 'John Doe',
            'param2' => 30,
            'param3' => true,
            'param4' => ['key' => 'value'],
        ];
        $type = 'mysql';

        $query = new DbPreparedQuery($sql, $bindings, $type);

        $this->assertInstanceOf(DbPreparedQuery::class, $query);
    }

    // ==================== sql() Method Tests ====================

    public function testSqlReturnsCorrectSqlString(): void
    {
        $expectedSql = 'SELECT * FROM users WHERE id = :param1';
        $query = new DbPreparedQuery($expectedSql, [], 'mysql');

        $this->assertEquals($expectedSql, $query->sql());
    }

    public function testSqlReturnsComplexQuery(): void
    {
        $expectedSql = 'SELECT u.*, p.name AS post_name FROM users u '
            . 'LEFT JOIN posts p ON u.id = p.user_id WHERE u.age > :param1 AND u.status = :param2';
        $query = new DbPreparedQuery($expectedSql, ['param1' => 18, 'param2' => 'active'], 'postgres');

        $this->assertEquals($expectedSql, $query->sql());
    }

    public function testSqlReturnsEmptyString(): void
    {
        $query = new DbPreparedQuery('', [], 'sqlite');

        $this->assertEquals('', $query->sql());
    }

    public function testSqlReturnsStringWithPlaceholders(): void
    {
        $expectedSql = 'UPDATE users SET name = :param1, email = :param2 WHERE id = :param3';
        $query = new DbPreparedQuery($expectedSql, [], 'mysql');

        $this->assertEquals($expectedSql, $query->sql());
        $this->assertStringContainsString(':param', $query->sql());
    }

    // ==================== bindings() Method Tests ====================

    public function testBindingsReturnsEmptyArray(): void
    {
        $query = new DbPreparedQuery('SELECT * FROM users', [], 'mysql');

        $bindings = $query->bindings();

        $this->assertIsArray($bindings);
        $this->assertEmpty($bindings);
    }

    public function testBindingsReturnsSingleBinding(): void
    {
        $expectedBindings = ['param1' => 'value1'];
        $query = new DbPreparedQuery('SELECT * FROM users WHERE id = :param1', $expectedBindings, 'mysql');

        $bindings = $query->bindings();

        $this->assertEquals($expectedBindings, $bindings);
        $this->assertArrayHasKey('param1', $bindings);
        $this->assertEquals('value1', $bindings['param1']);
    }

    public function testBindingsReturnsMultipleBindings(): void
    {
        $expectedBindings = [
            'param1' => 'John',
            'param2' => 25,
            'param3' => true,
        ];
        $query = new DbPreparedQuery('SELECT * FROM users', $expectedBindings, 'postgres');

        $bindings = $query->bindings();

        $this->assertCount(3, $bindings);
        $this->assertEquals($expectedBindings, $bindings);
    }

    public function testBindingsHandlesDifferentDataTypes(): void
    {
        $expectedBindings = [
            'string' => 'text',
            'int' => 123,
            'float' => 45.67,
            'bool' => false,
            'null' => null,
            'array' => ['a', 'b', 'c'],
        ];
        $query = new DbPreparedQuery('SELECT *', $expectedBindings, 'sqlite');

        $bindings = $query->bindings();

        $this->assertIsString($bindings['string']);
        $this->assertIsInt($bindings['int']);
        $this->assertIsFloat($bindings['float']);
        $this->assertIsBool($bindings['bool']);
        $this->assertNull($bindings['null']);
        $this->assertIsArray($bindings['array']);
    }

    public function testBindingsAreImmutable(): void
    {
        $originalBindings = ['param1' => 'original'];
        $query = new DbPreparedQuery('SELECT *', $originalBindings, 'mysql');

        $bindings = $query->bindings();
        $bindings['param1'] = 'modified';
        $bindings['param2'] = 'new';

        // Original bindings in query should remain unchanged
        $freshBindings = $query->bindings();
        $this->assertEquals('original', $freshBindings['param1']);
        $this->assertArrayNotHasKey('param2', $freshBindings);
    }

    // ==================== type() Method Tests ====================

    public function testTypeReturnsMysql(): void
    {
        $query = new DbPreparedQuery('SELECT *', [], 'mysql');

        $this->assertEquals('mysql', $query->type());
    }

    public function testTypeReturnsPostgres(): void
    {
        $query = new DbPreparedQuery('SELECT *', [], 'postgres');

        $this->assertEquals('postgres', $query->type());
    }

    public function testTypeReturnsSqlite(): void
    {
        $query = new DbPreparedQuery('SELECT *', [], 'sqlite');

        $this->assertEquals('sqlite', $query->type());
    }

    public function testTypeReturnsMariadb(): void
    {
        $query = new DbPreparedQuery('SELECT *', [], 'mariadb');

        $this->assertEquals('mariadb', $query->type());
    }

    public function testTypeReturnsCustomValue(): void
    {
        $query = new DbPreparedQuery('SELECT *', [], 'custom_dialect');

        $this->assertEquals('custom_dialect', $query->type());
    }

    // ==================== __toString() Method Tests ====================

    public function testToStringReturnsSqlString(): void
    {
        $sql = 'SELECT * FROM users WHERE id = :param1';
        $query = new DbPreparedQuery($sql, ['param1' => 123], 'mysql');

        $this->assertEquals($sql, (string) $query);
    }

    public function testToStringWorksWithStringConcatenation(): void
    {
        $sql = 'SELECT * FROM users';
        $query = new DbPreparedQuery($sql, [], 'postgres');

        $concatenated = 'Query: ' . $query;

        $this->assertEquals('Query: ' . $sql, $concatenated);
    }

    public function testToStringWorksInEcho(): void
    {
        $sql = 'DELETE FROM users WHERE id = :param1';
        $query = new DbPreparedQuery($sql, [], 'sqlite');

        ob_start();
        echo $query;
        $output = ob_get_clean();

        $this->assertEquals($sql, $output);
    }

    // ==================== Integration Tests ====================

    public function testCompleteQueryWithAllProperties(): void
    {
        $sql = 'SELECT u.name, COUNT(p.id) AS post_count FROM users u '
            . 'LEFT JOIN posts p ON u.id = p.user_id WHERE u.status = :q123_p1 '
            . 'GROUP BY u.id HAVING COUNT(p.id) > :q123_p2 ORDER BY post_count DESC';
        $bindings = [
            'q123_p1' => 'active',
            'q123_p2' => 5,
        ];
        $type = 'mysql';

        $query = new DbPreparedQuery($sql, $bindings, $type);

        $this->assertEquals($sql, $query->sql());
        $this->assertEquals($bindings, $query->bindings());
        $this->assertEquals($type, $query->type());
        $this->assertEquals($sql, (string) $query);
    }

    public function testMultipleInstancesAreIndependent(): void
    {
        $query1 = new DbPreparedQuery('SELECT * FROM users', ['param1' => 'value1'], 'mysql');
        $query2 = new DbPreparedQuery('SELECT * FROM posts', ['param2' => 'value2'], 'postgres');

        $this->assertNotEquals($query1->sql(), $query2->sql());
        $this->assertNotEquals($query1->bindings(), $query2->bindings());
        $this->assertNotEquals($query1->type(), $query2->type());
    }

    public function testQueryWithSpecialCharactersInSql(): void
    {
        $sql = "SELECT * FROM users WHERE name LIKE :param1 AND email != '' AND status IN (:param2, :param3, :param4)";
        $bindings = [
            'param1' => '%John%',
            'param2' => 'active',
            'param3' => 'pending',
            'param4' => 'verified',
        ];
        $query = new DbPreparedQuery($sql, $bindings, 'mysql');

        $this->assertEquals($sql, $query->sql());
        $this->assertCount(4, $query->bindings());
    }

    public function testQueryWithUnicodeCharacters(): void
    {
        $sql = 'SELECT * FROM users WHERE name = :param1 OR city = :param2';
        $bindings = [
            'param1' => 'José',
            'param2' => 'München',
        ];
        $query = new DbPreparedQuery($sql, $bindings, 'postgres');

        $this->assertEquals('José', $query->bindings()['param1']);
        $this->assertEquals('München', $query->bindings()['param2']);
    }
}
