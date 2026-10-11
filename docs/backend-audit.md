# Independent backend audit — 11 October 2026

Incremental review of controllers, services, models, migrations, Fortify, policies, jobs/scheduler, channels and Filament operations. Existing accounts, trades, settled prices/outcomes, ledger entries and private documents were preserved. Only an additive index migration was applied to development MySQL.

| Confirmed problem | Root cause / change | Verification |
|---|---|---|
| Funding and withdrawal retries could reuse a key with changed parameters | Controllers ignored conflicting retry payloads. New WalletRequests service locks the customer, compares amount/method/decrypted destination, rechecks password and status, and reserves funds in one transaction | Changed-payload regression tests; native concurrent withdrawal/funding tests |
| Wallet GETs created accounts and issued repeated queries | Ledger.wallet called account initialization. Replaced with one read-only aggregate | Test verifies zero wallet creation; measured GET query counts |
| Ledger posting could run without a transaction or accept a conflicting key | Ledger.post now requires a transaction and compares existing owner/type/entries | Transaction-boundary regression; existing trading tests and native races |
| Transaction history omitted reserved-only settlement/withdrawal movements | Available-only projection. PlatformController now groups all owned wallet entries and exposes available/reserved deltas with whitelisted type filtering | Approved-withdrawal history regression and browser screenshots |
| Support history grew without bound | Whole conversation was returned and marked read. Fetch newest 50, cursor to older pages, mark only displayed staff messages | 75-message regression, private attachment test |
| Failed support insert could leave an orphan upload | Storage is outside database rollback. Track newly stored path and remove it on exception | Code inspection; existing upload/privacy tests. No existing files deleted |
| Reconciliation only tested transaction sums | Added LedgerAudit to check balance, entry count, negative customer accounts and expected reservations from open trades/pending withdrawals | Deliberately damaged isolated ledger test; native suite and development reconcile |
| Rollback phone-column condition was inverted | Corrected existing migration down guard; new migration adds only history indexes | SQLite migration/test runs. Development rollback was not executed |
| Trading service itself did not check verification | Added verified-email requirement to locked customer, supplementing route middleware | Direct-service unverified authorization regression |
| Staff pagination depended on absent styles and rendered giant arrows | Replaced default Laravel pagination markup with scoped Filament buttons | Final staff screenshots at five widths |
| Staff KYC/support pages fetched attachments/messages once per row | Bulk page-scoped document lookup and group-limited support message lookup | Staff browser workflows and rendered screenshots |

Financial integrity reviewed: integer minor units; server entry/expiry; immutable acceptance payout snapshot; exact expiry observation; ties refund stake; unique request/settlement/ledger keys; user-before-trade locking; atomic reservations; retry-safe settlement. Delayed-worker and process-interruption tests exercise MySQL transaction behavior. Future payout edits do not change accepted trades, confirmed by regression. No administrative edit path changes settled outcomes.

Registration ignores role/status mass assignment, hashes passwords, starts with an unverified account and virtual funding. Verified/status middleware protects customer mutations. Staff permissions and step-up passwords are enforced separately for financial reviews, roles and rules. Policies restrict identity documents to owner/identity reviewers and support attachments to owner/support staff. Database notifications and after-commit account broadcasts remain functional.

Remaining limitations: predictable educational market formula; sampled candle extrema; no real-money execution; no external exchange adapter; reconciliation loads ledger/account aggregates into memory and is a local diagnostic rather than an enterprise audit pipeline. Do not reinterpret these tests as a production financial-system certification.
