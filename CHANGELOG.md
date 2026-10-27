# Release Notes

## [Unreleased](https://github.com/medyat/laraql/commits/main)

Everything below ships as the first pre-release, `v0.1.0`.

### Protocol v1

- One JSON query language for lists: `source`, `fields`, `filters` (18 typed operators), `sort`, `page`, `search`, `kind: count`, plus the laraql extensions `include` and `options`.
- Batch envelope `{ queries }` → `{ results }`; a failing query answers an `error` under its own key and never fails its siblings.
- `docs/protocol.md` is the contract, `schema/laraql-v1.schema.json` validates it, `fixtures/v1` holds 71 request/response pairs computed against a canonical dataset.
- Date presets and day ranges resolve in the request timezone (`X-Timezone` header); `"@me"` on user columns resolves to the authenticated user.

### Package

- Typed sources: `Column::{int,float,money,…,date,datetime,json}` with per-type operators, `searchable()`, `expression()`, `from()` through declared `joins()`, `describe()`.
- `Source` with relations (`include`), options (`Option::scope|load|decorate`), scope and decorator hooks, policy-based authorization; integer and string/UUID keys.
- Validator reporting every problem under its path (`filters.2.op`), with configurable limits (page size, filters, sort keys, `in` values, search tokens, include limit/depth, queries per request).
- Two-layer compiler: real columns are filtered and searched inside the sub-query, expression columns on the outer query; deterministic ordering with the primary key as tie-breaker; `hasMore` without a count query.
- `laraql:glossary` renders LLM-facing pages (protocol + one per source) from the definitions; `--check` for CI.
- Runs on SQLite, MySQL and PostgreSQL (CI replays every fixture on all three). Requires PHP 8.4 and Laravel 13.
