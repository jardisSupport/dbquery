<?php

declare(strict_types=1);

namespace JardisSupport\DbQuery\Tests\Integration;

use PDO;
use PDOException;

/**
 * Database Connection Helper for Integration Tests
 *
 * Provides PDO connections to all supported databases and helper methods
 * for table setup/teardown and test data management.
 */
class DatabaseConnection
{
    private static ?PDO $mysqlConnection = null;
    private static ?PDO $mariadbConnection = null;
    private static ?PDO $postgresConnection = null;
    private static ?PDO $sqliteConnection = null;

    /**
     * Get MySQL connection
     */
    public function getMysqlConnection(): PDO
    {
        if (self::$mysqlConnection === null) {
            $host = getenv('MYSQL_HOST') ?: 'mysql';
            $port = getenv('MYSQL_PORT') ?: '3306';
            $database = getenv('MYSQL_DATABASE') ?: 'test_db';
            $user = getenv('MYSQL_USER') ?: 'test_user';
            $password = getenv('MYSQL_PASSWORD') ?: 'test_password';

            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            self::$mysqlConnection = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        return self::$mysqlConnection;
    }

    /**
     * Get MariaDB connection
     */
    public function getMariaDbConnection(): PDO
    {
        if (self::$mariadbConnection === null) {
            $host = getenv('MARIADB_HOST') ?: 'mariadb';
            $port = getenv('MARIADB_PORT') ?: '3306';
            $database = getenv('MARIADB_DATABASE') ?: 'test_db';
            $user = getenv('MARIADB_USER') ?: 'test_user';
            $password = getenv('MARIADB_PASSWORD') ?: 'test_password';

            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            self::$mariadbConnection = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        return self::$mariadbConnection;
    }

    /**
     * Get PostgreSQL connection
     */
    public function getPostgresConnection(): PDO
    {
        if (self::$postgresConnection === null) {
            $host = getenv('POSTGRES_HOST') ?: 'postgres';
            $port = getenv('POSTGRES_PORT') ?: '5432';
            $database = getenv('POSTGRES_DATABASE') ?: 'test_db';
            $user = getenv('POSTGRES_USER') ?: 'test_user';
            $password = getenv('POSTGRES_PASSWORD') ?: 'test_password';

            $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
            self::$postgresConnection = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        return self::$postgresConnection;
    }

    /**
     * Get SQLite connection
     */
    public function getSqliteConnection(): PDO
    {
        if (self::$sqliteConnection === null) {
            self::$sqliteConnection = new PDO('sqlite::memory:', null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        return self::$sqliteConnection;
    }

    /**
     * Create a test table for integration tests
     *
     * @param PDO $pdo Database connection
     * @param string $dbType Database type (mysql, mariadb, postgres, sqlite)
     * @param string $tableName Table name
     */
    public function createTestTable(PDO $pdo, string $dbType, string $tableName = 'users'): void
    {
        $this->dropTestTable($pdo, $tableName);

        $sql = match ($dbType) {
            'mysql', 'mariadb' => "CREATE TABLE `{$tableName}` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) NOT NULL,
                `status` VARCHAR(50) NOT NULL DEFAULT 'active',
                `age` INT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY `email_unique` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'postgres' => "CREATE TABLE {$tableName} (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                status VARCHAR(50) NOT NULL DEFAULT 'active',
                age INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            'sqlite' => "CREATE TABLE {$tableName} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                status TEXT NOT NULL DEFAULT 'active',
                age INTEGER NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            default => throw new PDOException("Unsupported database type: {$dbType}")
        };

        $pdo->exec($sql);
    }

    /**
     * Create a secondary test table for JOIN tests
     *
     * @param PDO $pdo Database connection
     * @param string $dbType Database type
     * @param string $tableName Table name
     */
    public function createOrdersTable(PDO $pdo, string $dbType, string $tableName = 'orders'): void
    {
        $this->dropTestTable($pdo, $tableName);

        $sql = match ($dbType) {
            'mysql', 'mariadb' => "CREATE TABLE `{$tableName}` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `product` VARCHAR(255) NOT NULL,
                `amount` DECIMAL(10,2) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'postgres' => "CREATE TABLE {$tableName} (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL,
                product VARCHAR(255) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            'sqlite' => "CREATE TABLE {$tableName} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                product TEXT NOT NULL,
                amount REAL NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            default => throw new PDOException("Unsupported database type: {$dbType}")
        };

        $pdo->exec($sql);
    }

    /**
     * Create a users table with JSON metadata column
     *
     * @param PDO $pdo Database connection
     * @param string $dbType Database type
     */
    public function createUsersWithJsonTable(PDO $pdo, string $dbType): void
    {
        $this->dropTestTable($pdo, 'users');

        $sql = match ($dbType) {
            'mysql', 'mariadb' => "CREATE TABLE `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `email` VARCHAR(255) NOT NULL UNIQUE,
                `status` VARCHAR(50) NOT NULL DEFAULT 'active',
                `age` INT NULL,
                `metadata` JSON NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'postgres' => "CREATE TABLE users (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                status VARCHAR(50) NOT NULL DEFAULT 'active',
                age INT NULL,
                metadata JSONB NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            'sqlite' => "CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                status TEXT NOT NULL DEFAULT 'active',
                age INTEGER NULL,
                metadata TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            default => throw new PDOException("Unsupported database type: {$dbType}")
        };

        $pdo->exec($sql);
    }

    /**
     * Create an orders table with status and order_date
     *
     * @param PDO $pdo Database connection
     * @param string $dbType Database type
     */
    public function createOrdersWithStatusTable(PDO $pdo, string $dbType): void
    {
        $this->dropTestTable($pdo, 'orders');

        $sql = match ($dbType) {
            'mysql', 'mariadb' => "CREATE TABLE `orders` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `product` VARCHAR(255) NOT NULL,
                `amount` DECIMAL(10,2) NOT NULL,
                `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
                `order_date` DATE NOT NULL,
                `notes` TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'postgres' => "CREATE TABLE orders (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL,
                product VARCHAR(255) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                order_date DATE NOT NULL,
                notes TEXT NULL
            )",
            'sqlite' => "CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                product TEXT NOT NULL,
                amount REAL NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                order_date TEXT NOT NULL,
                notes TEXT NULL
            )",
            default => throw new PDOException("Unsupported database type: {$dbType}")
        };

        $pdo->exec($sql);
    }

    /**
     * Create a categories table for recursive CTE tests
     *
     * @param PDO $pdo Database connection
     * @param string $dbType Database type
     */
    public function createCategoriesTable(PDO $pdo, string $dbType): void
    {
        $this->dropTestTable($pdo, 'categories');

        $sql = match ($dbType) {
            'mysql', 'mariadb' => "CREATE TABLE `categories` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `parent_id` INT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'postgres' => "CREATE TABLE categories (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                parent_id INT NULL
            )",
            'sqlite' => "CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                parent_id INTEGER NULL
            )",
            default => throw new PDOException("Unsupported database type: {$dbType}")
        };

        $pdo->exec($sql);
    }

    /**
     * Create a products table with JSON tags
     *
     * @param PDO $pdo Database connection
     * @param string $dbType Database type
     */
    public function createProductsTable(PDO $pdo, string $dbType): void
    {
        $this->dropTestTable($pdo, 'products');

        $sql = match ($dbType) {
            'mysql', 'mariadb' => "CREATE TABLE `products` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(255) NOT NULL,
                `category_id` INT NOT NULL,
                `price` DECIMAL(10,2) NOT NULL,
                `stock` INT NOT NULL DEFAULT 0,
                `tags` JSON NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'postgres' => "CREATE TABLE products (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                category_id INT NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                stock INT NOT NULL DEFAULT 0,
                tags JSONB NULL
            )",
            'sqlite' => "CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                category_id INTEGER NOT NULL,
                price REAL NOT NULL,
                stock INTEGER NOT NULL DEFAULT 0,
                tags TEXT NULL
            )",
            default => throw new PDOException("Unsupported database type: {$dbType}")
        };

        $pdo->exec($sql);
    }

    /**
     * Insert comprehensive test data for full verification tests
     *
     * @param PDO $pdo Database connection
     * @param string $dbType Database type
     */
    public function insertComprehensiveTestData(PDO $pdo, string $dbType): void
    {
        // Users with JSON metadata
        $this->insertTestData($pdo, 'users', [
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'status' => 'active',
                'age' => 30,
                'metadata' => '{"country":"DE","roles":["admin","user"],"settings":{"theme":"dark","lang":"de"}}',
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'status' => 'active',
                'age' => 25,
                'metadata' => '{"country":"US","roles":["user"],"settings":{"theme":"light","lang":"en"}}',
            ],
            [
                'name' => 'Bob Johnson',
                'email' => 'bob@example.com',
                'status' => 'inactive',
                'age' => 35,
                'metadata' => '{"country":"DE","roles":["user"],"settings":{"theme":"dark","lang":"de"}}',
            ],
            [
                'name' => 'Alice Brown',
                'email' => 'alice@example.com',
                'status' => 'active',
                'age' => 28,
                'metadata' => '{"country":"FR","roles":["editor","user"],"settings":{"theme":"light","lang":"fr"}}',
            ],
            [
                'name' => "O'Brien",
                'email' => 'obrien@example.com',
                'status' => 'active',
                'age' => 40,
                'metadata' => '{"country":"US","roles":["admin"],"settings":{"theme":"dark","lang":"en"}}',
            ],
            [
                'name' => 'Charlie\\Path',
                'email' => 'charlie@example.com',
                'status' => 'deleted',
                'age' => 22,
                'metadata' => null,
            ],
        ]);

        // Orders
        $this->insertTestData($pdo, 'orders', [
            ['user_id' => 1, 'product' => 'Laptop', 'amount' => 1200.00, 'status' => 'completed', 'order_date' => '2024-01-15', 'notes' => null],
            ['user_id' => 1, 'product' => 'Mouse', 'amount' => 25.50, 'status' => 'completed', 'order_date' => '2024-01-20', 'notes' => 'Express shipping'],
            ['user_id' => 2, 'product' => 'Keyboard', 'amount' => 75.00, 'status' => 'pending', 'order_date' => '2024-02-01', 'notes' => null],
            ['user_id' => 2, 'product' => 'Monitor', 'amount' => 450.00, 'status' => 'completed', 'order_date' => '2024-02-10', 'notes' => null],
            ['user_id' => 3, 'product' => 'Headphones', 'amount' => 150.00, 'status' => 'cancelled', 'order_date' => '2024-01-25', 'notes' => 'Wrong address'],
            ['user_id' => 4, 'product' => 'Webcam', 'amount' => 89.99, 'status' => 'pending', 'order_date' => '2024-03-01', 'notes' => null],
            ['user_id' => 5, 'product' => 'SSD', 'amount' => 120.00, 'status' => 'completed', 'order_date' => '2024-02-15', 'notes' => "Customer note: it's urgent"],
            ['user_id' => 1, 'product' => 'USB Hub', 'amount' => 35.00, 'status' => 'pending', 'order_date' => '2024-03-10', 'notes' => null],
        ]);

        // Categories (tree: Electronics > Laptops, Phones; Clothing > Shirts)
        $this->insertTestData($pdo, 'categories', [
            ['name' => 'Electronics', 'parent_id' => null],
            ['name' => 'Laptops', 'parent_id' => 1],
            ['name' => 'Phones', 'parent_id' => 1],
            ['name' => 'Clothing', 'parent_id' => null],
            ['name' => 'Shirts', 'parent_id' => 4],
            ['name' => 'Accessories', 'parent_id' => 1],
        ]);

        // Products with JSON tags
        $this->insertTestData($pdo, 'products', [
            ['name' => 'ThinkPad X1', 'category_id' => 2, 'price' => 1299.99, 'stock' => 15, 'tags' => '["business","portable","sale"]'],
            ['name' => 'MacBook Pro', 'category_id' => 2, 'price' => 2499.00, 'stock' => 8, 'tags' => '["premium","portable"]'],
            ['name' => 'iPhone 15', 'category_id' => 3, 'price' => 999.00, 'stock' => 50, 'tags' => '["premium","5g"]'],
            ['name' => 'Galaxy S24', 'category_id' => 3, 'price' => 849.00, 'stock' => 30, 'tags' => '["android","5g","sale"]'],
            ['name' => 'USB-C Hub', 'category_id' => 6, 'price' => 45.00, 'stock' => 100, 'tags' => '["accessory"]'],
            ['name' => 'Polo Shirt', 'category_id' => 5, 'price' => 39.99, 'stock' => 200, 'tags' => '["casual","sale"]'],
            ['name' => 'Gaming Mouse', 'category_id' => 6, 'price' => 79.99, 'stock' => 0, 'tags' => '["gaming","sale"]'],
        ]);
    }

    /**
     * Drop a test table
     *
     * @param PDO $pdo Database connection
     * @param string $tableName Table name
     */
    public function dropTestTable(PDO $pdo, string $tableName): void
    {
        try {
            $pdo->exec("DROP TABLE IF EXISTS {$tableName}");
        } catch (PDOException $e) {
            // Ignore errors if table doesn't exist
        }
    }

    /**
     * Insert test data into a table
     *
     * @param PDO $pdo Database connection
     * @param string $tableName Table name
     * @param array<int, array<string, mixed>> $rows Array of rows to insert
     */
    public function insertTestData(PDO $pdo, string $tableName, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $fields = array_keys($rows[0]);
        $placeholders = array_fill(0, count($fields), '?');

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $tableName,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );

        $stmt = $pdo->prepare($sql);

        foreach ($rows as $row) {
            $stmt->execute(array_values($row));
        }
    }

    /**
     * Get all rows from a table
     *
     * @param PDO $pdo Database connection
     * @param string $tableName Table name
     * @return array<int, array<string, mixed>>
     */
    public function getAllRows(PDO $pdo, string $tableName): array
    {
        $stmt = $pdo->query("SELECT * FROM {$tableName}");
        return $stmt->fetchAll();
    }

    /**
     * Count rows in a table
     *
     * @param PDO $pdo Database connection
     * @param string $tableName Table name
     */
    public function countRows(PDO $pdo, string $tableName): int
    {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$tableName}");
        $result = $stmt->fetch();
        return (int)$result['count'];
    }

    /**
     * Truncate a table
     *
     * @param PDO $pdo Database connection
     * @param string $tableName Table name
     */
    public function truncateTable(PDO $pdo, string $tableName): void
    {
        try {
            $pdo->exec("TRUNCATE TABLE {$tableName}");
        } catch (PDOException $e) {
            // For SQLite, use DELETE instead
            $pdo->exec("DELETE FROM {$tableName}");
        }
    }
}
