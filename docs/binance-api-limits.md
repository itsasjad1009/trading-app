# Request and connection limits

Verified against [official REST documentation](https://github.com/binance/binance-spot-api-docs/blob/master/rest-api.md) and [stream documentation](https://github.com/binance/binance-spot-api-docs/blob/master/web-socket-streams.md) on 2026-10-11. These can change.

Endpoint weights used: exchangeInfo 20, time 1, klines 2 (maximum 1000 rows/page), ticker/price symbols batch 4, ticker/24hr six-symbol batch 2. The app does not poll per browser. Metadata caches for one hour; identical chart windows share cache and a backfill lock.

RequestBudget reads REQUEST_WEIGHT metadata windows and X-MBX-USED-WEIGHT-1M. Requests serialize via a shared cache lock. Conservative default cap is 600 weight/minute with 80% margin, additionally bounded by every discovered window. This is an application budget, not a claim of the provider's universal limit. Published metadata and IP usage may include other programs on the same IP. Only the 1M usage header is currently synchronized; other windows use app reservations, so shared-IP coordination still needs deployment review.

429/418 set a shared blocked-until timestamp; Retry-After seconds honored with conservative minimum 60/120 seconds. No retries occur during that cooldown. HTTP 5xx blocks for 30 seconds; command/history failures are visible and do not fabricate data.

Documented WS constraints: 1024 streams/connection, 5 client messages/second including ping/pong/control frames, 300 connection attempts/5 minutes/IP, expected 24h disconnect, server ping every 20s and pong required within one minute. We use 18 combined URL subscriptions on one connection and respond to ping frames. Rotate after 23h50m. Reconnect jitter/backoff caps at 63 seconds. These duration and rate-boundary cases were not proven by the short soak.
