# Fixtures (v1)

Request/response pairs that pin the protocol described in `docs/protocol.md`.
The package replays them against the workbench sources; client adapters
(a data-table adapter, for example) read the same files from
`vendor/medyat/laraql/fixtures/v1`.

Every fixture is computed against `dataset.json` (two sources, 6 orders,
7 lines) and validates against `#/$defs/Fixture` in
`schema/laraql-v1.schema.json`:

```json
{
  "name": "filter-eq-date",            // equals the file name
  "description": "…",
  "context": { "now": "2026-10-27T10:00:00+03:00", "timezone": "Europe/Istanbul", "user": "guest" },
  "request": { "queries": { … } },
  "response": { "status": 200, "body": { "results": { … } } }
}
```

`context.now` and `context.timezone` make date presets deterministic;
`context.user` (optional) names a dataset user whose policy outcome the fixture
expects. Rows are listed in the exact order the server must return them.
