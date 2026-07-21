<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Factory;

use InvalidArgumentException;
use JardisSupport\DbQuery\Command\Delete\DeleteMySql;
use JardisSupport\DbQuery\Command\Delete\DeletePostgresSql;
use JardisSupport\DbQuery\Command\Delete\DeleteSqlBuilder;
use JardisSupport\DbQuery\Command\Delete\DeleteSqliteSql;
use JardisSupport\DbQuery\Command\Insert\InsertMySql;
use JardisSupport\DbQuery\Command\Insert\InsertPostgresSql;
use JardisSupport\DbQuery\Command\Insert\InsertSqlBuilder;
use JardisSupport\DbQuery\Command\Insert\InsertSqliteSql;
use JardisSupport\DbQuery\Command\Update\UpdateMySql;
use JardisSupport\DbQuery\Command\Update\UpdatePostgresSql;
use JardisSupport\DbQuery\Command\Update\UpdateSqlBuilder;
use JardisSupport\DbQuery\Command\Update\UpdateSqliteSql;
use JardisSupport\DbQuery\Data\Dialect;
use JardisSupport\DbQuery\Query\MySql;
use JardisSupport\DbQuery\Query\PostgresSql;
use JardisSupport\DbQuery\Query\SqlBuilder;
use JardisSupport\DbQuery\Query\SqliteSql;

/**
 * Factory for creating SQL builders based on database dialect
 *
 * Provides methods for creating SELECT, INSERT, UPDATE, and DELETE SQL builders
 * for different database dialects (MySQL/MariaDB, PostgreSQL, SQLite).
 *
 * Each created builder receives its own BuilderRegistry instance with the
 * appropriate dialect and version context, enabling multi-dialect usage
 * within the same request.
 */
class SqlBuilderFactory
{
    /**
     * Create a SELECT SQL builder for the specified dialect
     *
     * @param string $dialect Database dialect: 'mysql', 'mariadb', 'postgres', 'sqlite'
     * @param string|null $version Database version (e.g., '8.0', '14'). Uses default if null.
     * @return SqlBuilder
     * @throws InvalidArgumentException If dialect is not supported
     */
    public static function createSelect(string $dialect, ?string $version = null): SqlBuilder
    {
        $dialectEnum = self::parseDialect($dialect);
        $registry = self::createRegistry($dialectEnum, $version);

        $builder = match ($dialectEnum) {
            Dialect::PostgreSQL => new PostgresSql($registry),
            Dialect::SQLite => new SqliteSql($registry),
            Dialect::MySQL, Dialect::MariaDB => new MySql($registry),
        };

        return $builder;
    }

    /**
     * Create an INSERT SQL builder for the specified dialect
     *
     * @param string $dialect Database dialect: 'mysql', 'mariadb', 'postgres', 'sqlite'
     * @param string|null $version Database version (e.g., '8.0', '14'). Uses default if null.
     * @return InsertSqlBuilder
     * @throws InvalidArgumentException If dialect is not supported
     */
    public static function createInsert(string $dialect, ?string $version = null): InsertSqlBuilder
    {
        $dialectEnum = self::parseDialect($dialect);
        $registry = self::createRegistry($dialectEnum, $version);

        return match ($dialectEnum) {
            Dialect::PostgreSQL => new InsertPostgresSql($registry),
            Dialect::SQLite => new InsertSqliteSql($registry),
            Dialect::MySQL, Dialect::MariaDB => new InsertMySql($registry),
        };
    }

    /**
     * Create an UPDATE SQL builder for the specified dialect
     *
     * @param string $dialect Database dialect: 'mysql', 'mariadb', 'postgres', 'sqlite'
     * @param string|null $version Database version (e.g., '8.0', '14'). Uses default if null.
     * @return UpdateSqlBuilder
     * @throws InvalidArgumentException If dialect is not supported
     */
    public static function createUpdate(string $dialect, ?string $version = null): UpdateSqlBuilder
    {
        $dialectEnum = self::parseDialect($dialect);
        $registry = self::createRegistry($dialectEnum, $version);

        return match ($dialectEnum) {
            Dialect::PostgreSQL => new UpdatePostgresSql($registry),
            Dialect::SQLite => new UpdateSqliteSql($registry),
            Dialect::MySQL, Dialect::MariaDB => new UpdateMySql($registry),
        };
    }

    /**
     * Create a DELETE SQL builder for the specified dialect
     *
     * @param string $dialect Database dialect: 'mysql', 'mariadb', 'postgres', 'sqlite'
     * @param string|null $version Database version (e.g., '8.0', '14'). Uses default if null.
     * @return DeleteSqlBuilder
     * @throws InvalidArgumentException If dialect is not supported
     */
    public static function createDelete(string $dialect, ?string $version = null): DeleteSqlBuilder
    {
        $dialectEnum = self::parseDialect($dialect);
        $registry = self::createRegistry($dialectEnum, $version);

        return match ($dialectEnum) {
            Dialect::PostgreSQL => new DeletePostgresSql($registry),
            Dialect::SQLite => new DeleteSqliteSql($registry),
            Dialect::MySQL, Dialect::MariaDB => new DeleteMySql($registry),
        };
    }

    /**
     * Parse dialect string to Dialect enum
     *
     * @param string $dialect Database dialect string
     * @return Dialect The parsed dialect enum
     * @throws InvalidArgumentException If dialect is not supported
     */
    private static function parseDialect(string $dialect): Dialect
    {
        return Dialect::tryFromString($dialect)
            ?? throw new InvalidArgumentException("Unsupported dialect: {$dialect}");
    }

    /**
     * Create a BuilderRegistry instance with dialect and version context
     *
     * @param Dialect $dialect Database dialect enum
     * @param string|null $version Database version, uses default if null
     * @return BuilderRegistry
     */
    private static function createRegistry(Dialect $dialect, ?string $version): BuilderRegistry
    {
        $resolvedVersion = $version ?? $dialect->defaultVersion();
        return new BuilderRegistry($dialect->value, $resolvedVersion);
    }
}
