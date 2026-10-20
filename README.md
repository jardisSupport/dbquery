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
- **Identifier Auto-Quoting** — simple identifiers (`ident`, `alias.ident`) in WHERE/HAVING/ORDER BY/GROUP BY/SELECT are quoted per dialect; expressions stay raw (see [Identifier Auto-Quoting](#identifier-auto-quoting))
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
// $prepared->sql()      → "SELECT `id`, `name`, `email` FROM `users` WHERE `status` = ? AND `created_at` >= ? ORDER BY `name` ASC LIMIT 50"
// $prepared->bindings() → ['active', '2024-01-01']
```

## Identifier Auto-Quoting

Simple identifiers are quoted automatically with the dialect's identifier quoting —
MySQL/MariaDB/SQLite use backticks, PostgreSQL uses double quotes. This makes
case-sensitive column names (e.g. `createdAt` from quoted DDL) work on PostgreSQL,
which folds unquoted identifiers to lowercase (error 42703 before).

**What is quoted** — a string is a *simple identifier* iff it matches
`^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$` (`ident` or `alias.ident`).
Simple identifiers are quoted in these positions:

- `where()` / `and()` / `or()` condition fields
- `having()` fields
- `orderBy()` fields
- `groupBy()` columns
- the `select()` field list (per comma-separated item; for `expr AS alias` the
  `expr` and the `alias` are each quoted when they are simple identifiers)

**What stays raw (byte-identical)** — everything that is not a simple identifier:

- SQL literals and niladic functions that would otherwise match the pattern
  (case-insensitive): `NULL`, `TRUE`, `FALSE`, `DEFAULT`, `CURRENT_TIMESTAMP`,
  `CURRENT_DATE`, `CURRENT_TIME`, `LOCALTIME`, `LOCALTIMESTAMP`,
  `CURRENT_USER`, `SESSION_USER` — so UNION padding like
  `select('id, NULL AS email')` keeps `NULL` raw. A column literally named
  `null` must be passed pre-quoted (`` `null` ``/`"null"`) or via
  `Expression::raw('"null"')`; qualified names (`t.null`) are always
  treated as identifiers.
- expressions and functions (`YEAR(created)`, `price * 1.19`, `COUNT(*)`)
- `*` and `alias.*`
- already quoted strings (`` `createdAt` ``, `"createdAt"`)
- `Expression::raw(...)` — the explicit escape hatch: even a simple identifier
  inside `Expression::raw()` is never quoted
- JOIN ON constraints, window specifications (`partitionBy()`, `windowOrderBy()`)
  and CTE inner SQL other than what the inner builder itself quotes

**Boundary** — quoting makes the written identifier case-significant on PostgreSQL.
The name you pass must match the DDL exactly when the DDL was quoted, or be
all-lowercase when the DDL was unquoted. Declare aliases with an explicit `AS`
(`COUNT(*) AS orderCount`) so alias definition and alias references are quoted
consistently; use `Expression::raw()` where the raw string is required.

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
    ->onConflict('sku')                    // conflict target (used by PostgreSQL/SQLite)
    ->onDuplicateKeyUpdate('name', 'Widget')
    ->onDuplicateKeyUpdate('price', 9.99);

$sql = $insert->sql('mysql', prepared: true);
```

### Upsert in every dialect

`onDuplicateKeyUpdate()` renders the same upsert on every dialect:

| Dialect | Rendered |
|---|---|
| MySQL / MariaDB | `ON DUPLICATE KEY UPDATE col = ?` (the database picks the conflicting unique key) |
| PostgreSQL / SQLite | `ON CONFLICT (target) DO UPDATE SET col = ?` |

PostgreSQL and SQLite need a conflict target: call `onConflict('col', ...)` first.
Without it `sql()` throws an `InvalidArgumentException` instead of silently dropping
the clause. MySQL/MariaDB ignore the target, so one call chain is valid on all four
dialects. `onConflict()->doUpdate([...])` stays available and feeds the same clause
on PostgreSQL/SQLite (both entry points merge; `onDuplicateKeyUpdate()` wins per column).

### Literal substring search (LIKE with ESCAPE)

`like()` / `notLike()` take a pattern as given: `%` and `_` in a user's search term
act as wildcards. For a search for a *literal* term pass a `LikePattern`:

```php
use JardisSupport\DbQuery\Data\LikePattern;

$query->where('name')->like(LikePattern::contains('50%'));    // LIKE ? ESCAPE '!'  with '%50!%%'
$query->where('name')->like(LikePattern::startsWith('a_b'));  // 'a!_b%'
$query->where('name')->like(LikePattern::endsWith('x'));      // '%x'
$query->where('name')->like(LikePattern::literal('a_b'));     // 'a!_b' (no wildcards at all)
$query->where('name')->notLike(LikePattern::contains('spam'));
```

`%`, `_` and the escape character `!` inside the term are escaped in one place
(`LikePattern`); the clause `ESCAPE '!'` is identical on MySQL, MariaDB, PostgreSQL
and SQLite. Works in `where`/`and`/`or`/`having` and for SELECT, UPDATE and DELETE.

`LikePattern` implements `JardisSupport\Contract\DbQuery\LikePatternInterface` from
`jardissupport/contracts`: code typed against the contracts interfaces passes it to
`like()` / `notLike()` without a static-analysis error.

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
