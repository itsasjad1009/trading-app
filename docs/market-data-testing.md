# Market-data testing

Evidence and final results are recorded below after verification. The initial180.013s local soak connected for173.097s:2392 events (1367 trades,298 candles,727 tickers),0 invalid,0 duplicates,0 out-of-order; peak PHP46,137,344bytes. Total receipt-event latency6,543,909ms (mean2735.75ms), max9161ms; provider clock estimate-894ms, RTT825ms. Single connection, no spontaneous reconnect. This is not a24h test or evidence of settlement-grade latency. Report: test-artifacts/market-data/soak.json.

All six one-minute historical windows loaded180candles with0gaps before the isolation incident. Failed runs are not counted as passing; the initial isolation report claiming the dev database was untouched was incorrect and superseded by the recovery record.

Automated coverage includes decimal strings and UTC ms; invalid symbols/types/ranges; OHLC validation; dedupe and out-of-order storage; metadata limits and429/418Retry-After; shared locks/cache expiry/staleness; singleton crash expiry; private channel/account isolation; historical gaps/pagination; deterministic audit-only expiry candidate selection; simulated ledger/idempotent settlement regression; injected disconnect/resubscription and queued history repair. MySQL fixture IDs are queried rather than assumed because auto-increment does not rewind on rollback.

Live observation selection is not live settlement. No accepted exchange-based financial outcome is claimed.

## Final verification, 2026-10-11

- Backend: 63 tests / 289 assertions passed on SQLite, and the same 63 tests / 289 assertions passed on the guarded isolated MySQL 8.4 instance. Frontend: 4 Vitest tests and TypeScript/Vite build passed.
- Playwright: 2 tests passed at 1440×900 and 390×844. Two authenticated subscribers received private Reverb updates; other-account authorization returned 403; 1m/5m history switching, loaded chart screenshots, navigation listener cleanup and no horizontal overflow were verified. Evidence is under test-artifacts/market-data/browser-1440 and browser-390.
- Final queued soak: 240.088 seconds, connected 236.019 seconds, one connection, 7,754 events: 6,204 trades, 416 candles and 1,134 tickers. Zero invalid, duplicate or out-of-order events. All six symbols received events. PHP peak memory 46,137,344 bytes. Receipt minus event latency averaged 9,246.37 ms across events, maximum 17,979 ms; clock estimate −1,241 ms with 1,382 ms RTT. This observed delay is material: the run does not prove low-latency or settlement suitability. See queued-soak.json.
- One closed WebSocket candle per symbol matched its independently fetched REST candle on all six decimal OHLC/volume fields. See candle-consistency.json. Final sampled database had 8,519 observations and 1,398 candles, no invalid OHLC rows and zero ledger discrepancies (metrics-final.json). These counts continue growing while the worker runs.
- OS working-set samples were about 73.8 MB. Samples lack explicit capture timestamps, so no CPU utilization percentage is claimed. information_schema storage estimates were cached; they are not a measured storage-growth rate. Production capacity remains unverified.

An earlier 300-second run with Reverb unavailable exposed synchronous broadcast blocking and incorrectly classified distribution failures as invalid data. It is retained as failed evidence in verified-soak.json despite that filename. Delivery was moved to a bounded, coalesced queue before the final passing run. A later browser attempt ran after the bounded worker had stopped and received no events; it was rerun successfully with the continuous worker running.

The database isolation failure was recovered using binary logs. All 16 checked user/financial tables matched the recovered pre-test snapshot, including 124 users, 60 trades, 249 ledger transactions and 558 entries. All four ledger reconciliation counters were zero. recovery-verification.json records the comparison. Subsequent browser registrations added explicitly named example.test fixtures. The test runner now sets explicit child-process environment; Tests/TestCase rejects any database outside the isolated test instance before migrations. The original claim that development data was untouched was false.

Not verified: 24-hour stream rotation, production concurrent load, sustained regional latency, real 418 ban recovery, commercial data rights, or financial settlement from live observations. Disconnect/resubscription and rate-limit handling have deterministic automated coverage, not long-duration production evidence.
