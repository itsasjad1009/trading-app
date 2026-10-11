# Phase 1B frontend measurements — 11 October 2026

The final responsive build passed TypeScript and Vite. Output: CSS 67.32 kB (15.67 gzip), Platform 100.99 kB (31.75 gzip), lazy chart 179.08 kB (58.09 gzip), shared app 437.47 kB (142.13 gzip). These are individual build artifacts, not initial-route totals or physical-device speed claims.

Nine-size captures measured chart heights of 280 px at 320 px, 320 px at 375–430 px, 400 px at 768 px, 420 px at 1024/1366 px, 423 px at 1440 px, and 507.59 px at 1920 px. Phone auth inputs measure 46 px with 16 px text. All 126 measured public/customer views had no page-wide overflow and no uncaught browser exceptions.

Pages and charts remain lazy loaded. Chart creation/cleanup and Echo cleanup are retained; chart-supported horizontal touch drag/pinch is enabled, while vertical drag allows page scrolling. Small-phone decoration is reduced and reduced motion is respected. Measurements: `test-artifacts/responsive-review/comparisons/measurements-summary.json`. Physical mobile keyboards, gestures and low-end-device rendering still require native-device verification. Backend measurements below are historical and were not repeated for frontend-only changes.

---
# Measured performance review — 11 October 2026

Local measurements below are diagnostics, not production capacity claims. The query probe uses the HTTP kernel, array sessions/cache and the dedicated MySQL 8.4 database with five sequential requests per route. Its first request includes framework warming. These are backend response times without browser/network/render costs. No equivalent pre-change timing capture exists, so no measured speedup percentage is claimed.

| Route | SQL queries/request | Median request ms | Median SQL ms | HTTP statuses |
|---|---|---|---|---|
| /dashboard | 7 | 7.00 | 3.17 | 200, 200, 200, 200, 200 |
| /trade | 7 | 5.96 | 2.49 | 200, 200, 200, 200, 200 |
| /positions | 7 | 5.66 | 2.33 | 200, 200, 200, 200, 200 |
| /transactions | 7 | 5.57 | 2.40 | 200, 200, 200, 200, 200 |
| /support | 6 | 4.70 | 1.80 | 200, 200, 200, 200, 200 |
| /profile | 6 | 5.24 | 2.18 | 200, 200, 200, 200, 200 |

Source improvements: wallet reads use one aggregate without initialization; trade stats use one aggregate rather than separate counters; support history is capped at 50; staff attachments/messages load in page-scoped batches; history indexes added. Trading mutation locking is preserved. Global stats still run for all customer pages, leaving a modest future opportunity for page-specific payload optimization.

Production build: app entry 437.47 kB (142.14 gzip), Platform 97.28 kB (31.00 gzip), lazy MarketChart 179.01 kB (58.08 gzip), CSS 53.94 kB (13.23 gzip). Echo 31.51 kB and Pusher 62.31 kB load dynamically. Vite/TypeScript pass; lazy chart/pages remain split. These figures are build output sizes, not an initial-route total or a before/after reduction.

Browser ready measurements from final screenshot runs:

| Viewport group | Homepage ready ms | Terminal ready ms | Terminal resource entries |
|---|---|---|---|
| large | 594 | 1608 | 12 |
| laptop | 716 | 3337 | 12 |
| small-laptop | 951 | 2541 | 12 |
| tablet | 616 | 1649 | 12 |
| mobile | 601 | 1677 | 12 |

Ready means page navigation plus heading/chart-connected assertion. Each route is visited once per viewport in a local browser session; later pages may use warm HTTP cache. Runs overlap other local QA, so these values are observations, not controlled p95/Lighthouse/network benchmarks. Resource entries include scripts/styles/fetches and should not be interpreted as React render counts.

Chart creation cost reduced structurally: one chart per mounted terminal, view changes only visibility; browser test confirms original canvas remains connected. Fetches have an 8-second timeout, do not overlap, and pause when hidden. Existing candles remain visible with a disconnected status on failure. No profiler trace of chart CPU cost or React render frequency was collected.

Visible customer data refresh remains every 15 seconds because demo quotes are computed on demand; private broadcasts are coalesced and share an in-flight reload guard. Hidden pages pause polling. Chart refresh remains 10 seconds; normal terminal has these two distinct data paths. This is explicit polling overhead, not a claim of a push-only live feed. Reverb import failure has a handled fallback and deployment TLS scheme is respected.

No new heavy animation or remote icon/font dependency. Existing particles respect reduced motion. Future work: controlled slow-network tests, browser CPU/memory profiles, request-specific stats elimination and sustained database load/large-ledger reconciliation benchmarks.

