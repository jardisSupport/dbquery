# Changelog

All notable changes to `jardissupport/dbquery` are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning follows [SemVer](https://semver.org/).

## [1.3.2] - Unreleased

### Added

- **Tests only, no change to `src/`: the recursive "chain" query form is proven on MySQL, MariaDB,
  PostgreSQL and SQLite.** `WITH RECURSIVE chain AS (anchor UNION step)` with the step joined to the
  CTE name, `key IN (SELECT node FROM chain)` on the outer query with a join on a real table and
  `GROUP BY`, a correlated `EXISTS` subquery behind it, the whole query as `from($q, 'matching')` under
  `COUNT(*)` and as `select('1')->limit(n)` probe. Pinned: `bindings()` in placeholder order (anchor,
  step depth limit and condition, outer condition, `EXISTS`), `UNION` (never `UNION ALL`) plus a depth
  limit ends on a cycle in the data, the CTE name is quoted the same way in `WITH`, `JOIN` and `FROM`.
- Documented server limits for deep chains (tests pin them): MySQL aborts a recursive query after 1001
  iterations (`cte_max_recursion_depth` 1000, error 3636); MariaDB (`max_recursive_iterations` 1000)
  stops silently at 1001 rows without an error; PostgreSQL and SQLite have no such limit. A depth limit
  up to 999 is safe on all four. Bind the depth limit as an integer: on SQLite a limit bound as text
  never stops the chain.

## [1.3.1] - 2026-11-18

### Fixed

- **A subquery used as a value or operand is now rendered and bound at its own place in prepared
  mode - in every condition, not only the first.** Subquery placeholders were resolved from the
  start of the statement's binding list for every condition, so
  `where('tenant')->equals('t1')->and('id')->in($sub)` and
  `->and(Expression::integer(1))->equals($sub)` left `IN ?` / `= ?` in the SQL with the `DbQuery`
  object as binding. Conditions now resolve their subqueries from their own position in the
  bindings, in placeholder order: later conditions, `or`, bracket groups, `having`, two or more
  subqueries in one statement, nested subqueries and `exists()` / `notExists()` in the middle of a
  statement (their bindings were appended at the end) - on MySQL, MariaDB, PostgreSQL and SQLite.
- **`DbDelete` and `DbUpdate` inline subqueries in `WHERE` in prepared mode** (they left `IN ?`
  with a `DbQuery` object as binding), and `DbDelete` binds JOIN subqueries before the `WHERE`
  bindings, as they stand in the SQL.
- **Binding order with a CTE and a SELECT subquery in one query**: the SELECT subquery's bindings
  were placed before the CTE's bindings although the CTE stands first in the SQL.

## [1.3.0] - 2026-11-17

### Added

- **`Expression::integer(int)` and `Expression::column(string)` - typed expressions.**
  `Expression::integer(1)` renders the integer literal (never a binding);
  `Expression::column('u.id')` renders a column reference that is quoted per
  dialect (backtick for MySQL/MariaDB/SQLite, double quote for PostgreSQL), on the
  left side of a condition as well as on the right side of its operator. `column()`
  accepts only a simple identifier (`ident` or `alias.ident`,
  `SimpleIdentifierQuoter::PATTERN`); any other value throws `InvalidArgumentException`.
  `raw()` and the constructor are unchanged. Single-value form without raw text:
  `where(Expression::integer(1))->equals($subQuery)`, with the subquery correlated via
  `->where('o.user_id')->equals(Expression::column('u.id'))->limit(1)`. The signature of
  `where()` is unchanged.

## [1.2.0] - 2026-10-20

### Added

- **`LikePattern` - literal substring search.** `like()` / `notLike()` accept
  `LikePattern::contains() / startsWith() / endsWith() / literal()`; `%`, `_` and
  the escape character are escaped in one place and the condition renders
  `LIKE ? ESCAPE '!'` with the same effect on MySQL, MariaDB, PostgreSQL and SQLite.
  Plain `like('...')` is unchanged.
  `LikePattern` implements the contract type `JardisSupport\Contract\DbQuery\LikePatternInterface`
  (`jardissupport/contracts` ^2.14, `pattern()` / `escapeClause()`): callers typed against the
  contracts interfaces pass `like(LikePattern::contains('x'))` through phpstan, and the condition
  builders evaluate the interface, so any implementation of it is rendered the same way.
  `tests/Static/` holds a phpstan-analysed contract-type check (`make phpstan` covers it).

- **Identifier auto-quoting for simple identifiers.** Fields passed to
  `where()`/`and()`/`or()`, `having()`, `orderBy()`, `groupBy()` and the
  `select()` field list are now quoted with the dialect's identifier quoting
  (MySQL/MariaDB/SQLite: backtick, PostgreSQL: double quote) when they are
  SIMPLE identifiers — `ident` or `alias.ident`, boundary regex
  `^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$`. Everything else
  (functions, operators, `*`, already quoted strings, `Expression::raw()`)
  is emitted byte-identical raw. `Expression::raw()` is the explicit escape
  hatch and is never quoted, even for a bare identifier.
  SQL literals and niladic functions matching the pattern are excluded
  case-insensitively (`SimpleIdentifierQuoter::KEYWORD_EXCEPTIONS`): NULL,
  TRUE, FALSE, DEFAULT, CURRENT_TIMESTAMP, CURRENT_DATE, CURRENT_TIME,
  LOCALTIME, LOCALTIMESTAMP, CURRENT_USER, SESSION_USER — UNION padding
  (`select('id, NULL AS email')`) keeps `NULL` raw; a column literally named
  `null` must be passed pre-quoted or via `Expression::raw()`.
  New internals: `Query\Formatter\SimpleIdentifierQuoter`,
  `Query\Formatter\IdentifierMarkerReplacer`,
  `Query\Formatter\FieldListIdentifierQuoter`.

### Fixed

- **`onDuplicateKeyUpdate()` rendered nothing on PostgreSQL and SQLite** although
  the contract promises all dialects. It now renders `ON CONFLICT (target) DO UPDATE SET ...`
  there (target from `onConflict()`; without a target `sql()` throws
  `InvalidArgumentException` instead of silently dropping the clause). On
  MySQL/MariaDB `onConflict()->doUpdate()` now renders `ON DUPLICATE KEY UPDATE`
  and `onConflict(col)->doNothing()` the side-effect-free no-op `col = col`
  (no `INSERT IGNORE`, which would also mute NOT NULL/FK errors); `doNothing()`
  without a target throws. The three duplicated SET-clause renderers are now one.

- camelCase columns created with quoted DDL (e.g. `"createdAt"`) failed on
  PostgreSQL with error 42703 when referenced in WHERE/ORDER BY/GROUP BY/
  SELECT, while the same column arrived correctly quoted in INSERT/UPDATE SET.
  The INSERT → WHERE roundtrip on quoted camelCase DDL now works on all
  supported dialects (covered by integration tests on MySQL, PostgreSQL and
  SQLite).

### Changed

- **Signatures of internal clause builders extended** (technically public
  classes, resolved via `BuilderRegistry`): the `__invoke()` methods of
  `Query\Builder\Clause\ConditionBuilder` (new `callable
  $quoteMarkedIdentifiers` parameter), `SelectBuilder` (new `callable
  $quoteFieldList`), `OrderByBuilder` and `GroupByBuilder` (new `callable
  $quoteSimpleIdentifier`) take additional quoting callbacks. Custom code
  or version overrides calling these builders directly must pass the new
  parameters. Known accepted duplication: the quoting helper methods exist
  once per SQL builder base class (`Query\SqlBuilder`,
  `Command\Update\UpdateSqlBuilder`, `Command\Delete\DeleteSqlBuilder`),
  following the existing structure of the JSON/subquery placeholder helpers.
- **Emitted SQL now contains quoted identifiers** in the positions listed
  above (semantics unchanged for lowercase/snake_case identifiers on all
  supported dialects). Code that string-compares generated SQL must be
  adjusted. On PostgreSQL the written identifier is now case-significant:
  it must match quoted DDL exactly, or be all-lowercase for unquoted DDL.
  Declare aliases with an explicit `AS` so alias definition and references
  stay consistent.
