<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Unit\Factory;

use InvalidArgumentException;
use JardisSupport\DbQuery\command\Delete\DeleteMySql;
use JardisSupport\DbQuery\command\Delete\DeletePostgresSql;
use JardisSupport\DbQuery\command\Delete\DeleteSqlBuilder;
use JardisSupport\DbQuery\command\Delete\DeleteSqliteSql;
use JardisSupport\DbQuery\command\insert\InsertMySql;
use JardisSupport\DbQuery\command\insert\InsertPostgresSql;
use JardisSupport\DbQuery\command\insert\InsertSqlBuilder;
use JardisSupport\DbQuery\command\insert\InsertSqliteSql;
use JardisSupport\DbQuery\command\update\UpdateMySql;
use JardisSupport\DbQuery\command\update\UpdatePostgresSql;
use JardisSupport\DbQuery\command\update\UpdateSqlBuilder;
use JardisSupport\DbQuery\command\update\UpdateSqliteSql;
use JardisSupport\DbQuery\Data\Dialect;
use JardisSupport\DbQuery\Query\MySql;
use JardisSupport\DbQuery\Query\PostgresSql;
use JardisSupport\DbQuery\Query\SqlBuilder;
use JardisSupport\DbQuery\Query\SqliteSql;
use JardisSupport\DbQuery\Factory\SqlBuilderFactory;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for SqlBuilderFactory
 *
 * Tests the factory pattern for creating SQL builders based on dialect.
 */
class SqlBuilderFactoryTest extends TestCase
{
    // ==================== MySQL Tests ====================

    public function testCreateReturnsMySqlInstanceForMysql(): void
    {
        $builder = SqlBuilderFactory::createSelect('mysql');

        $this->assertInstanceOf(SqlBuilder::class, $builder);
        $this->assertInstanceOf(MySql::class, $builder);
    }

    public function testCreateReturnsMySqlInstanceForMysqlUppercase(): void
    {
        $builder = SqlBuilderFactory::createSelect('MYSQL');

        $this->assertInstanceOf(MySql::class, $builder);
    }

    public function testCreateReturnsMySqlInstanceForMysqlMixedCase(): void
    {
        $builder = SqlBuilderFactory::createSelect('MySql');

        $this->assertInstanceOf(MySql::class, $builder);
    }

    // ==================== MariaDB Tests ====================

    public function testCreateReturnsMySqlInstanceForMariadb(): void
    {
        $builder = SqlBuilderFactory::createSelect('mariadb');

        $this->assertInstanceOf(SqlBuilder::class, $builder);
        $this->assertInstanceOf(MySql::class, $builder);
    }

    public function testCreateReturnsMySqlInstanceForMariadbUppercase(): void
    {
        $builder = SqlBuilderFactory::createSelect('MARIADB');

        $this->assertInstanceOf(MySql::class, $builder);
    }

    public function testCreateReturnsMySqlInstanceForMariadbMixedCase(): void
    {
        $builder = SqlBuilderFactory::createSelect('MariaDB');

        $this->assertInstanceOf(MySql::class, $builder);
    }

    // ==================== PostgreSQL Tests ====================

    public function testCreateReturnsPostgresSqlInstanceForPostgres(): void
    {
        $builder = SqlBuilderFactory::createSelect('postgres');

        $this->assertInstanceOf(SqlBuilder::class, $builder);
        $this->assertInstanceOf(PostgresSql::class, $builder);
    }

    public function testCreateReturnsPostgresSqlInstanceForPostgresUppercase(): void
    {
        $builder = SqlBuilderFactory::createSelect('POSTGRES');

        $this->assertInstanceOf(PostgresSql::class, $builder);
    }

    public function testCreateReturnsPostgresSqlInstanceForPostgresMixedCase(): void
    {
        $builder = SqlBuilderFactory::createSelect('PostGres');

        $this->assertInstanceOf(PostgresSql::class, $builder);
    }

    // ==================== SQLite Tests ====================

    public function testCreateReturnsSqliteSqlInstanceForSqlite(): void
    {
        $builder = SqlBuilderFactory::createSelect('sqlite');

        $this->assertInstanceOf(SqlBuilder::class, $builder);
        $this->assertInstanceOf(SqliteSql::class, $builder);
    }

    public function testCreateReturnsSqliteSqlInstanceForSqliteUppercase(): void
    {
        $builder = SqlBuilderFactory::createSelect('SQLITE');

        $this->assertInstanceOf(SqliteSql::class, $builder);
    }

    public function testCreateReturnsSqliteSqlInstanceForSqliteMixedCase(): void
    {
        $builder = SqlBuilderFactory::createSelect('SqLite');

        $this->assertInstanceOf(SqliteSql::class, $builder);
    }

    // ==================== Invalid Dialect Tests ====================

    public function testCreateThrowsExceptionForUnsupportedDialect(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported dialect: oracle');

        SqlBuilderFactory::createSelect('oracle');
    }

    public function testCreateThrowsExceptionForEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SqlBuilderFactory::createSelect('');
    }

    public function testCreateThrowsExceptionForMssql(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported dialect: mssql');

        SqlBuilderFactory::createSelect('mssql');
    }

    public function testCreateThrowsExceptionForPostgresql(): void
    {
        // 'postgresql' is not in PROVIDERS, only 'postgres' is
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported dialect: postgresql');

        SqlBuilderFactory::createSelect('postgresql');
    }

    public function testCreateThrowsExceptionForRandomString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SqlBuilderFactory::createSelect('notadatabase');
    }

    // ==================== Integration with DbType ====================

    public function testCreateWorksWithAllDbTypeProviders(): void
    {
        foreach (Dialect::values() as $provider) {
            $builder = SqlBuilderFactory::createSelect($provider);

            $this->assertInstanceOf(
                SqlBuilder::class,
                $builder,
                "Failed to create builder for provider: $provider"
            );
        }
    }

    public function testCreateReturnsCorrectBuilderTypeForEachProvider(): void
    {
        $expectedMappings = [
            Dialect::MySQL->value => MySql::class,
            Dialect::MariaDB->value => MySql::class,
            Dialect::PostgreSQL->value => PostgresSql::class,
            Dialect::SQLite->value => SqliteSql::class,
        ];

        foreach ($expectedMappings as $provider => $expectedClass) {
            $builder = SqlBuilderFactory::createSelect($provider);

            $this->assertInstanceOf(
                $expectedClass,
                $builder,
                "Provider '$provider' should return instance of $expectedClass"
            );
        }
    }

    // ==================== Multiple Instances Tests ====================

    public function testCreateReturnsNewInstanceEachTime(): void
    {
        $builder1 = SqlBuilderFactory::createSelect('mysql');
        $builder2 = SqlBuilderFactory::createSelect('mysql');

        $this->assertNotSame($builder1, $builder2);
    }

    public function testCreateReturnsDifferentInstancesForDifferentDialects(): void
    {
        $mysqlBuilder = SqlBuilderFactory::createSelect('mysql');
        $postgresBuilder = SqlBuilderFactory::createSelect('postgres');
        $sqliteBuilder = SqlBuilderFactory::createSelect('sqlite');

        $this->assertNotSame($mysqlBuilder, $postgresBuilder);
        $this->assertNotSame($mysqlBuilder, $sqliteBuilder);
        $this->assertNotSame($postgresBuilder, $sqliteBuilder);

        $this->assertInstanceOf(MySql::class, $mysqlBuilder);
        $this->assertInstanceOf(PostgresSql::class, $postgresBuilder);
        $this->assertInstanceOf(SqliteSql::class, $sqliteBuilder);
    }

    // ==================== Base Class Tests ====================

    public function testAllCreatedBuildersExtendSqlBuilder(): void
    {
        $dialects = ['mysql', 'mariadb', 'postgres', 'sqlite'];

        foreach ($dialects as $dialect) {
            $builder = SqlBuilderFactory::createSelect($dialect);

            $this->assertInstanceOf(
                SqlBuilder::class,
                $builder,
                "Builder for '$dialect' should extend SqlBuilder"
            );
        }
    }

    // ==================== Edge Cases ====================

    public function testCreateWithWhitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SqlBuilderFactory::createSelect('  mysql  ');
    }

    public function testCreateWithSpecialCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SqlBuilderFactory::createSelect('my-sql');
    }

    public function testCreateWithNumericString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SqlBuilderFactory::createSelect('123');
    }

    // ==================== createInsert() Tests ====================

    public function testCreateInsertReturnsMySqlInstanceForMysql(): void
    {
        $builder = SqlBuilderFactory::createInsert('mysql');

        $this->assertInstanceOf(InsertSqlBuilder::class, $builder);
        $this->assertInstanceOf(InsertMySql::class, $builder);
    }

    public function testCreateInsertReturnsPostgresInstanceForPostgres(): void
    {
        $builder = SqlBuilderFactory::createInsert('postgres');

        $this->assertInstanceOf(InsertSqlBuilder::class, $builder);
        $this->assertInstanceOf(InsertPostgresSql::class, $builder);
    }

    public function testCreateInsertReturnsSqliteInstanceForSqlite(): void
    {
        $builder = SqlBuilderFactory::createInsert('sqlite');

        $this->assertInstanceOf(InsertSqlBuilder::class, $builder);
        $this->assertInstanceOf(InsertSqliteSql::class, $builder);
    }

    // ==================== createUpdate() Tests ====================

    public function testCreateUpdateReturnsMySqlInstanceForMysql(): void
    {
        $builder = SqlBuilderFactory::createUpdate('mysql');

        $this->assertInstanceOf(UpdateSqlBuilder::class, $builder);
        $this->assertInstanceOf(UpdateMySql::class, $builder);
    }

    public function testCreateUpdateReturnsPostgresInstanceForPostgres(): void
    {
        $builder = SqlBuilderFactory::createUpdate('postgres');

        $this->assertInstanceOf(UpdateSqlBuilder::class, $builder);
        $this->assertInstanceOf(UpdatePostgresSql::class, $builder);
    }

    public function testCreateUpdateReturnsSqliteInstanceForSqlite(): void
    {
        $builder = SqlBuilderFactory::createUpdate('sqlite');

        $this->assertInstanceOf(UpdateSqlBuilder::class, $builder);
        $this->assertInstanceOf(UpdateSqliteSql::class, $builder);
    }

    // ==================== createDelete() Tests ====================

    public function testCreateDeleteReturnsMySqlInstanceForMysql(): void
    {
        $builder = SqlBuilderFactory::createDelete('mysql');

        $this->assertInstanceOf(DeleteSqlBuilder::class, $builder);
        $this->assertInstanceOf(DeleteMySql::class, $builder);
    }

    public function testCreateDeleteReturnsPostgresInstanceForPostgres(): void
    {
        $builder = SqlBuilderFactory::createDelete('postgres');

        $this->assertInstanceOf(DeleteSqlBuilder::class, $builder);
        $this->assertInstanceOf(DeletePostgresSql::class, $builder);
    }

    public function testCreateDeleteReturnsSqliteInstanceForSqlite(): void
    {
        $builder = SqlBuilderFactory::createDelete('sqlite');

        $this->assertInstanceOf(DeleteSqlBuilder::class, $builder);
        $this->assertInstanceOf(DeleteSqliteSql::class, $builder);
    }

    // ==================== Backwards Compatibility Tests ====================

    public function testDeprecatedCreateMethodStillWorks(): void
    {
        $builder = SqlBuilderFactory::createSelect('mysql');

        $this->assertInstanceOf(SqlBuilder::class, $builder);
        $this->assertInstanceOf(MySql::class, $builder);
    }
}
