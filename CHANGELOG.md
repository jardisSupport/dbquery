# Changelog

All notable changes to `jardissupport/dbquery` are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning follows [SemVer](https://semver.org/).

## [Unreleased]

### Added

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
