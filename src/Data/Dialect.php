<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Data;

/**
 * Represents supported database dialects
 *
 * This enum provides type-safe database dialect handling with version information.
 * Modern PHP 8.1+ enum for compile-time type safety and IDE autocomplete support.
 */
enum Dialect: string
{
    case MySQL = 'mysql';
    case MariaDB = 'mariadb';
    case PostgreSQL = 'postgres';
    case SQLite = 'sqlite';

    /**
     * Get the default/baseline version for this dialect
     *
     * These represent the baseline versions that the query builder targets.
     * The version-aware system is designed primarily for forward compatibility.
     *
     * @return string Default version number
     */
    public function defaultVersion(): string
    {
        return match ($this) {
            self::MySQL => '8.0',
            self::PostgreSQL => '14',
            self::SQLite => '3.39',
            self::MariaDB => '10.6',
        };
    }

    /**
     * Get supported versions for this dialect
     *
     * This list includes current and future versions. The version-aware builder
     * system allows implementing new SQL features with fallbacks to older syntax.
     *
     * @return array<int, string> List of supported version strings
     */
    public function supportedVersions(): array
    {
        return match ($this) {
            self::MySQL => ['8.0', '8.4', '9.0'],
            self::PostgreSQL => ['14', '15', '16', '17'],
            self::MariaDB => ['10.6', '11.0', '11.4'],
            self::SQLite => ['3.39', '3.40', '3.45'],
        };
    }

    /**
     * Check if a version is supported for this dialect
     *
     * @param string $version Version number to check
     * @return bool True if version is supported
     */
    public function supportsVersion(string $version): bool
    {
        return in_array($version, $this->supportedVersions(), true);
    }

    /**
     * Create Dialect from string value (case-insensitive)
     *
     * Note: Cannot override built-in from() method, so we use fromString() instead.
     * This method provides case-insensitive parsing for user input.
     *
     * @param string $value Dialect name (mysql, postgres, sqlite, mariadb)
     * @return self The matching dialect enum
     * @throws \ValueError If dialect is not supported
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom(strtolower($value))
            ?? throw new \ValueError("Unsupported database dialect: {$value}");
    }

    /**
     * Try to create Dialect from string value (case-insensitive)
     *
     * @param string $value Dialect name
     * @return self|null The matching dialect enum or null
     */
    public static function tryFromString(string $value): ?self
    {
        return self::tryFrom(strtolower($value));
    }

    /**
     * Get all dialect values as strings
     *
     * @return array<int, string> List of dialect string values
     */
    public static function values(): array
    {
        return array_map(
            fn(self $dialect) => $dialect->value,
            self::cases()
        );
    }
}
