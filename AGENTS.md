# jardissupport/dbquery

Fluent SQL query builder for MySQL/MariaDB/PostgreSQL/SQLite — four builders (`DbQuery` SELECT + CTE + Window, `DbInsert`, `DbUpdate`, `DbDelete`), state-separated (Builder → State → dialect-specific generator → Prepared SQL).

## Usage essentials

- **Dialect via Enum:** `Dialect::MySQL|MariaDB|PostgreSQL|SQLite` with `value`, `defaultVersion()` (8.0 / 10.6 / 14 / 3.39) and `supportsVersion()`. `sql($dialect, prepared: true, version: '...')` is the only output path — string dialects are parsed internally via `Dialect::tryFromString()`. **Always use `prepared: true`**, no raw concatenation.
- **Prepared output via `DbPreparedQuery`:** `->sql()` returns SQL with `?` placeholders, `->bindings()` returns the matching parameter array, `->type()` returns the dialect string; `(string)$prepared` equals `->sql()`. Ready to use as-is with `PDO::prepare()`/`execute($bindings)`.
- **Dialect limits are hard-validated:** `FULL JOIN` throws `InvalidArgumentException` on MySQL/SQLite (PostgreSQL only). `UPDATE`/`DELETE` + `JOIN`/`ORDER BY`/`LIMIT` only on MySQL/MariaDB — PostgreSQL and SQLite throw `InvalidArgumentException`. No silent fallback behavior.
- **Conflict handling is dialect-specific:** MySQL/MariaDB `->onDuplicateKeyUpdate('field', $value)`, PostgreSQL `->onConflict('email')->doUpdate([...])` or `->doNothing()`, SQLite `->orIgnore()` / `->replace()`. `DbInsert::fromSelect($selectQuery)` for `INSERT...SELECT`.
- **Raw SQL only via `Expression::raw()`** (not escaped, not validated) — usable in WHERE, SET, JSON paths. JSON ops are dialect-aware: `->whereJson('settings')->extract('$.theme')->equals('dark')`, `->length()`, `->contains/notContains`. Condition chaining with `->and()`/`->or()` + optional bracket param `('(' / ')')` for grouping.
- **Version-aware SQL via `BuilderRegistry`** (instance-based, **not static** — multi-dialect usage in parallel within the same request is possible). Pattern: `namespace\method\mysql\v80\FullJoin` (dots removed from version), fallback to base class. Layer rule: builders live in the Infrastructure/Repository Layer, **Domain never imports** the builders.

## Full reference

https://docs.jardis.io/en/support/dbquery
