# Laraql protocol v1 (draft)

Laraql is a typed, whitelisted list-query layer. A client sends one or more
**queries** against named **sources**; the server validates them against the
source's column definitions and answers with rows, a count or an error.

This document is the contract. `schema/laraql-v1.schema.json` validates it,
`fixtures/v1/` holds request/response pairs that both this package and client
adapters test against. Change this document before changing code.

## Transport

- `POST {route.path}` (default `laraql`), JSON body, JSON response.
- Authentication is the application's: add middleware in `config/laraql.php`.
- The request is a **batch**: several queries travel together and are
  answered under the same keys. One query failing never fails its siblings.

```json
{ "queries": { "orders": { "source": "order", "fields": ["code", "gross_total"] } } }
```

```json
{ "results": { "orders": { "rows": [ { "id": 1, "code": "SO-1", "gross_total": "120.00" } ], "page": { "number": 1, "size": 50, "hasMore": false } } } }
```

HTTP status is `200` whenever the envelope itself was valid; per-query
problems are reported inside `results`. The envelope is rejected as a whole
only when it is malformed (`422`) or the request is unauthenticated (`401`).

## Query

| Field | Type | Default | Notes |
|---|---|---|---|
| `source` | string | required | Source key, e.g. `order` |
| `kind` | `"rows"` \| `"count"` | `rows` | `count` ignores `fields`, `sort`, `page`, `include` |
| `fields` | string[] | all readable columns | Column ids; key, fixed and filter/sort columns are always added |
| `filters` | Filter[] | `[]` | Combined with AND; at most `limits.filters` |
| `sort` | Sort[] | `[]` | At most `limits.sort`; the primary key is always appended as tie-breaker |
| `page` | `{ number, size }` | `{ 1, limits.page_size.default }` | 1-based; `size ≤ limits.page_size.max` |
| `search` | string | — | Free text over the source's searchable columns |
| `include` | map | — | Laraql extension, see below |
| `options` | string[] | `[]` | Laraql extension: named hooks declared by the source |

### Filter

```json
{ "column": "gross_total", "op": "between", "value": [100, 500] }
```

Operators are the same 18 keys the `@swors/data-table` client emits. What an
operator *means* depends on the column type; the validator rejects
combinations the type does not support with `422`.

| `op` | Value | Types | Semantics |
|---|---|---|---|
| `eq` / `neq` | scalar | string, number, bool, date | Exact match. On `date`/`datetime` the day range in the request timezone |
| `gt` `gte` `lt` `lte` | number | number, money, star | Numeric comparison |
| `between` | `[min, max]` (either may be `null`) | number, money | Inclusive; a `null` edge makes it one-sided |
| `contains` / `not_contains` | string | string, text, id, email, url, phone, tags | Case-insensitive substring; `%` and `_` are escaped |
| `starts_with` / `ends_with` | string | string, text, id, email, phone | Case-insensitive prefix / suffix |
| `in` / `not_in` | scalar[] (≤ `limits.in_values`) | select, id, user, color, multiselect, tags | Multi-valued columns: "contains any of" / "contains none of" |
| `before` / `after` | ISO date or `{ "preset": "today" }` | date, datetime | Strict: `before 2026-10-05` excludes Oct 5 |
| `within` | `{ "preset": … }` or `{ "from", "to" }` | date, datetime | Inclusive. Presets: `today`, `this_week`, `next_7_days`, `this_month`, `last_30_days`, `this_quarter`, `overdue` |
| `is_empty` / `is_not_empty` | — | all | `NULL`; for text types also `''`; for lists also `[]` |

On `user` columns the value `"@me"` (with `eq`, `neq`, `in`, `not_in`) stands
for the authenticated user's id, so a saved filter "owner is me" works for
every user; without an authenticated user it is a `422`.

Presets and day ranges are resolved on the server in the **request
timezone**, so saved filters stay relative. The timezone travels as the
`X-Timezone` header (name configurable, `laraql.timezone.header`); without it
the application's `app.timezone` applies. An unknown zone name is an
envelope-level `422 invalid_request` (`details.timezone`). All queries in a
batch share the same timezone.

### Search

`search` is free text over the columns the source declares `searchable()`
(text types only; the client cannot pick columns). The text is split on
whitespace into tokens; **every token must match at least one searchable
column** (tokens AND, columns OR), order does not matter, matching is a
case-insensitive substring with `%` and `_` escaped:

```sql
WHERE (code ILIKE '%ege%'  OR customer ILIKE '%ege%')
  AND (code ILIKE '%1004%' OR customer ILIKE '%1004%')
```

Blank search is ignored. More than `limits.search_tokens` tokens is `422`
(`details.search`). A source without searchable columns rejects `search` with
`422`. `search` and `filters` combine with AND.

### Sort

```json
{ "column": "due_date", "dir": "desc" }
```

### Result

| Field | Present when | Shape |
|---|---|---|
| `rows` | `kind: rows` | `object[]`; dotted column ids are nested (`company.title` → `{ "company": { "title" } }`) |
| `page` | `kind: rows` | `{ number, size, hasMore }`; `hasMore` is computed by fetching `size + 1` rows, no count query runs |
| `total` | `kind: count` | integer |
| `error` | the query failed | `{ status, code, message, details? }` with `status` 403 / 404 / 422 |

A client that never asks for `kind: count` never pays for a count.

## Include (Laraql extension)

```json
{ "source": "order", "include": { "lines": { "fields": ["product_code", "qty"], "sort": [{ "column": "position", "dir": "asc" }], "limit": 50 } } }
```

- A relation must be declared on the source; the related source is itself a
  laraql source, so its column whitelist and authorization apply.
- `limit` per parent row, default `limits.include.limit`, max
  `limits.include.max_limit`; no pagination inside includes.
- Nesting depth at most `limits.include.depth`.
- Included rows land on the parent row under the relation key.
- Missing authorization on the related source is a `403` for the whole
  query, never a silently empty relation.

## Options (Laraql extension)

Named, server-side behaviours a source declares; the client only sends names:

```json
{ "source": "product", "options": ["only_orderable", "with_photo"] }
```

A source lists its options in `options(): array`; each entry is one of three
kinds, so the glossary can say what it does:

| Kind | Effect | Example |
|---|---|---|
| `Option::scope(fn)` | narrows the row set in the sub-query | `only_orderable` → `where('type', …)` |
| `Option::load(relation)` | eager-loads a relation for the page of rows | `with_photo` |
| `Option::decorate(fn)` | adds computed keys to each row | `allowed_units` → `{ "allowed_units": [...] }` |

Unknown names are `422` (`options.N`). Options are the replacement for ad-hoc
"function" names: they are part of the contract and show up in the glossary.

## Errors

Per-query errors live inside `results` and never change the HTTP status:

| `status` | `code` | When | `details` |
|---|---|---|---|
| 404 | `unknown_source` | unknown source key | — |
| 403 | `forbidden` | the source's policy denies `viewAny`, or an included source does | — |
| 422 | `invalid_query` | unknown column / operator / option / relation, type–operator mismatch, limit exceeded | path → messages, e.g. `{ "filters.2.op": ["…"] }`, `{ "page.size": ["…"] }` |

Envelope-level problems are the only non-200 answers and carry a single
`error` object instead of `results`:

| HTTP | `code` | When |
|---|---|---|
| 401 | `unauthenticated` | the application's auth middleware rejected the request |
| 422 | `invalid_request` | no `queries`, an empty map, more than `limits.queries_per_request` queries, or a body that is not an object |

Messages come from `lang/en/messages.php` and are publishable.

## Value representation

- `int` → JSON integer; `float`, `money`, `percentage` → JSON number (the
  server casts database decimals); `bool` → JSON boolean.
- `date` → `YYYY-MM-DD`; `datetime` → ISO 8601 in UTC with `Z`.
- Empty values are `null`; text columns may also hold `''`, list columns `[]`.
- Dotted column ids are nested in rows: `company.title` → `{ "company": { "title": "…" } }`.

## Fixtures

`fixtures/v1/*.json` are the executable form of this document, computed
against `fixtures/v1/dataset.json`; see the README there for the file format.
A behaviour that is not pinned by a fixture is not part of the contract.

## Limits (defaults)

See `config/laraql.php`: page size 50 / max 500, 20 filters, 500 values per
`in`, 3 sort keys, include limit 100 / max 500 / depth 2, 20 queries per
request.

## Reserved

- `page.cursor`: keyset pagination is not part of v1. The name is reserved so
  it can be added in a minor version next to `page.number`; offset paging stays.
