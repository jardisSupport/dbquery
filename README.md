# Jardis DbQuery

![Build Status](https://github.com/jardisSupport/dbquery/actions/workflows/ci.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](phpstan.neon)
[![PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg)](phpcs.xml)
[![Coverage](https://img.shields.io/badge/Coverage-96.65%25-brightgreen.svg)](https://github.com/jardisSupport/dbquery)

> Part of **[Jardis](https://jardis.io)** — the Domain-Driven Design platform for PHP. You model your domain; Jardis generates the production-ready hexagonal code (DTOs, Command/Query handlers, repositories, persistence). This package is part of the open-source foundation that generated code runs on.

A fluent SQL query builder for PHP that generates dialect-aware SQL for MySQL, MariaDB, PostgreSQL, and SQLite. Full support for CTEs, window functions, subqueries, JSON columns, and prepared statements. SQL injection protection built in.

---

## Features

- **Dialect-Aware SQL** — generates correct syntax for MySQL, MariaDB, PostgreSQL, and SQLite from a single builder
- **CTEs** — `with()` and `withRecursive()` for common table expressions
- **Window Functions** — `selectWindow()`, `window()`, and `selectWindowRef()` for analytics queries
- **Subqueries** — subqueries in FROM, JOIN constraints, SELECT columns, and WHERE EXISTS / NOT EXISTS
- **JSON Column Support** — `whereJson()`, `andJson()`, `orJson()`, `havingJson()` for structured JSON field conditions
- **Union / Union All** — `union()` and `unionAll()` compose multiple SELECT statements
- **Prepared Statements** — `sql($dialect, prepared: true)` returns a `DbPreparedQueryInterface` with bound parameters
- **SQL Injection Validation** — bracket and expression validation built into `sql()` before generation
- **INSERT Conflict Handling** — `DbInsert` supports ON DUPLICATE KEY (MySQL) and ON CONFLICT (PostgreSQL)

---

## Installation

```bash
composer require jardissupport/dbquery
```

## Quick Start

```php
use JardisSupport\DbQuery\DbQuery;

$query = (new DbQuery())
    ->select('id, name, email')
    ->from('users')
    ->where('status')->equals('active')
    ->and('created_at')->greaterEquals('2024-01-01')
    ->orderBy('name')
    ->limit(50);

// Generate prepared SQL for MySQL
$prepared = $query->sql('mysql', prepared: true);
// $prepared->sql()      → "SELECT id, name, email FROM users WHERE status = ? AND created_at >= ? ORDER BY name ASC LIMIT 50"
// $prepared->bindings() → ['active', '2024-01-01']
```

## Advanced Usage

```php
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\DbQuery\DbInsert;

// CTE with recursive traversal
$cte = (new DbQuery())
    ->select('id, parent_id, name, 0 AS depth')
    ->from('categories')
    ->where('parent_id')->isNull()
    ->union(
        (new DbQuery())
            ->select('c.id, c.parent_id, c.name, r.depth + 1')
            ->from('categories', 'c')
            ->innerJoin('category_tree', 'c.parent_id = r.id', 'r')
    );

$query = (new DbQuery())
    ->withRecursive('category_tree', $cte)
    ->select('id, name, depth')
    ->from('category_tree')
    ->orderBy('depth')
    ->orderBy('name');

// Window function for ranking
$ranked = (new DbQuery())
    ->select('id, customer_id, total')
    ->selectWindow('ROW_NUMBER', 'row_num')
        ->over()
        ->partitionBy('customer_id')
        ->orderBy('total', 'DESC')
        ->end()
    ->from('orders');

// JSON column condition (PostgreSQL)
$query = (new DbQuery())
    ->select('id, payload')
    ->from('events')
    ->whereJson('payload')->path('$.type')->equals('order.created')
    ->andJson('payload')->path('$.amount')->greaterEquals(100);

// INSERT with conflict resolution
$insert = (new DbInsert())
    ->into('products')
    ->fields('sku', 'name', 'price')
    ->values('ABC-001', 'Widget', 9.99)
    ->onDuplicateKey(['name', 'price']);

$sql = $insert->sql('mysql', prepared: true);
```

## Documentation

Full documentation, guides, and API reference:

**[docs.jardis.io/en/support/dbquery](https://docs.jardis.io/en/support/dbquery)**

## License

This package is licensed under the [MIT License](LICENSE.md).

---

**[Jardis](https://jardis.io)** · [Documentation](https://docs.jardis.io) · [Headgent](https://headgent.com)

<!-- BEGIN jardis/dev-skills README block — do not edit by hand -->
## AI-Assisted Development

This package ships with a skill for Claude Code, Cursor, Continue, and Aider. Install it in your consuming project:

```bash
composer require --dev jardis/dev-skills
```

More details: <https://docs.jardis.io/en/skills>
<!-- END jardis/dev-skills README block -->
