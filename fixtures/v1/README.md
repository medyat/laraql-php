# Fixtures (v1)

Request/response pairs that pin the protocol described in `docs/protocol.md`.
Each file holds `{ "request": …, "response": … }`; the package test suite replays
them, and client adapters (e.g. `@swors/data-table/laraql`) read the same files
from `vendor/medyat/laraql/fixtures/v1`.
