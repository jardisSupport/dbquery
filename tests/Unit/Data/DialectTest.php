<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Data;

use JardisSupport\DbQuery\Data\Dialect;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Dialect enum
 */
class DialectTest extends TestCase
{
    public function testEnumCasesExist(): void
    {
        $this->assertTrue(enum_exists(Dialect::class));
        $this->assertCount(4, Dialect::cases());
    }

    public function testMySqlCaseExists(): void
    {
        $this->assertSame('mysql', Dialect::MySQL->value);
    }

    public function testMariaDBCaseExists(): void
    {
        $this->assertSame('mariadb', Dialect::MariaDB->value);
    }

    public function testPostgreSQLCaseExists(): void
    {
        $this->assertSame('postgres', Dialect::PostgreSQL->value);
    }

    public function testSQLiteCaseExists(): void
    {
        $this->assertSame('sqlite', Dialect::SQLite->value);
    }

    public function testDefaultVersionReturnsCorrectVersionForMySQL(): void
    {
        $this->assertSame('8.0', Dialect::MySQL->defaultVersion());
    }

    public function testDefaultVersionReturnsCorrectVersionForPostgreSQL(): void
    {
        $this->assertSame('14', Dialect::PostgreSQL->defaultVersion());
    }

    public function testDefaultVersionReturnsCorrectVersionForSQLite(): void
    {
        $this->assertSame('3.39', Dialect::SQLite->defaultVersion());
    }

    public function testDefaultVersionReturnsCorrectVersionForMariaDB(): void
    {
        $this->assertSame('10.6', Dialect::MariaDB->defaultVersion());
    }

    public function testSupportedVersionsReturnsArrayForMySQL(): void
    {
        $versions = Dialect::MySQL->supportedVersions();
        $this->assertIsArray($versions);
        $this->assertContains('8.0', $versions);
        $this->assertContains('8.4', $versions);
        $this->assertContains('9.0', $versions);
    }

    public function testSupportedVersionsReturnsArrayForPostgreSQL(): void
    {
        $versions = Dialect::PostgreSQL->supportedVersions();
        $this->assertIsArray($versions);
        $this->assertContains('14', $versions);
        $this->assertContains('15', $versions);
        $this->assertContains('16', $versions);
        $this->assertContains('17', $versions);
    }

    public function testSupportedVersionsReturnsArrayForSQLite(): void
    {
        $versions = Dialect::SQLite->supportedVersions();
        $this->assertIsArray($versions);
        $this->assertContains('3.39', $versions);
        $this->assertContains('3.40', $versions);
        $this->assertContains('3.45', $versions);
    }

    public function testSupportedVersionsReturnsArrayForMariaDB(): void
    {
        $versions = Dialect::MariaDB->supportedVersions();
        $this->assertIsArray($versions);
        $this->assertContains('10.6', $versions);
        $this->assertContains('11.0', $versions);
        $this->assertContains('11.4', $versions);
    }

    public function testSupportsVersionReturnsTrueForSupportedVersion(): void
    {
        $this->assertTrue(Dialect::MySQL->supportsVersion('8.0'));
        $this->assertTrue(Dialect::PostgreSQL->supportsVersion('14'));
        $this->assertTrue(Dialect::SQLite->supportsVersion('3.39'));
        $this->assertTrue(Dialect::MariaDB->supportsVersion('10.6'));
    }

    public function testSupportsVersionReturnsFalseForUnsupportedVersion(): void
    {
        $this->assertFalse(Dialect::MySQL->supportsVersion('5.7'));
        $this->assertFalse(Dialect::PostgreSQL->supportsVersion('12'));
        $this->assertFalse(Dialect::SQLite->supportsVersion('3.20'));
        $this->assertFalse(Dialect::MariaDB->supportsVersion('10.2'));
    }

    public function testFromStringParsesCorrectly(): void
    {
        $this->assertSame(Dialect::MySQL, Dialect::fromString('mysql'));
        $this->assertSame(Dialect::PostgreSQL, Dialect::fromString('postgres'));
        $this->assertSame(Dialect::SQLite, Dialect::fromString('sqlite'));
        $this->assertSame(Dialect::MariaDB, Dialect::fromString('mariadb'));
    }

    public function testFromStringIsCaseInsensitive(): void
    {
        $this->assertSame(Dialect::MySQL, Dialect::fromString('MySQL'));
        $this->assertSame(Dialect::MySQL, Dialect::fromString('MYSQL'));
        $this->assertSame(Dialect::PostgreSQL, Dialect::fromString('POSTGRES'));
    }

    public function testFromStringThrowsForInvalidDialect(): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('Unsupported database dialect: invalid');
        Dialect::fromString('invalid');
    }

    public function testTryFromStringReturnsNullForInvalidDialect(): void
    {
        $this->assertNull(Dialect::tryFromString('invalid'));
    }

    public function testTryFromStringParsesCorrectly(): void
    {
        $this->assertSame(Dialect::MySQL, Dialect::tryFromString('mysql'));
        $this->assertSame(Dialect::PostgreSQL, Dialect::tryFromString('postgres'));
    }

    public function testValuesReturnsAllDialectValues(): void
    {
        $values = Dialect::values();
        $this->assertIsArray($values);
        $this->assertCount(4, $values);
        $this->assertContains('mysql', $values);
        $this->assertContains('postgres', $values);
        $this->assertContains('sqlite', $values);
        $this->assertContains('mariadb', $values);
    }

    public function testAllValuesAreLowercase(): void
    {
        foreach (Dialect::cases() as $dialect) {
            $this->assertSame(strtolower($dialect->value), $dialect->value);
        }
    }
}
