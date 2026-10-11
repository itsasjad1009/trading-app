# Native MySQL concurrency results — 11 October 2026

Executed scripts/mysql-audit.php against a separate MySQL 8.4.11 instance at 127.0.0.1:3308. Each run creates a uniquely named aster_audit_YYYYMMDD_HHMMSS database, migrates/seeds it once, and launches separate PHP processes. Development MySQL remains on port 3307 with trading_app. No development reset, truncation or record deletion occurred. The original port-3306 MariaDB installation was untouched.

Final machine-readable database identity, version and results: test-artifacts/mysql-audit-results.json.

| Scenario | Observed result |
|---|---|
| Two concurrent 7,000-USDT trades from 10,000 | One accepted, one insufficient-balance rejection; 3,000 available |
| Four concurrent identical trade keys | All callers receive accepted retry; exactly one trade |
| Four distinct simultaneous 2,500-USDT debits | Four accepted; available exactly zero |
| Two simultaneous 7,000-USDT withdrawals | One accepted, one rejected; 7,000 reserved |
| 4,000-USDT trade while 7,000 withdrawal pending | Rejected; remaining 3,000 unchanged |
| Mixed trade/withdrawal contention | One accepted, one rejected |
| Three delayed duplicate settlement workers | Exactly one settlement with price/observation at stored expiry |
| Worker exits after settlement insert before commit | MySQL rolls back settlement and its ledger posting; trade remains open |
| Retry after process interruption | Exactly one committed settlement |
| Four identical funding request keys | One pending request; no automatic credit |
| Repeated approved funding review | Second review rejected; credit applied once |
| Wrong withdrawal password | Rejected; wallet unchanged |
| Suspended funding request | Forbidden |
| Final reconciliation | Zero unbalanced/empty transactions, negative customer balances or reservation mismatches |

All 14 checks passed. An initial suspended-fixture assertion failed because status is intentionally excluded from mass assignment; the fixture now uses forceFill. No application authorization bypass was found in that failure. Tests were rerun successfully after correction. The crash test closes a live connection mid-transaction; it does not simulate host power loss, disk corruption, replication failover or sustained load.

Reproduction (local only): start scripts/start-audit-mysql.ps1, then php -d extension=intl scripts/mysql-audit.php. The isolated server is loopback-only and has an intentionally passwordless test root, so stop that specific instance after testing. Its data directory is .runtime/mysql-audit-data; audit data is preserved for inspection. Do not run against an application database or expose this instance on a network.
