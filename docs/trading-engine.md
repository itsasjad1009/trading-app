# Paper trading rules

Virtual credits have no real monetary value. Initial signup receives 10,000 demo USDT through balanced ledger funding. Minimum stake is 100, maximum 10,000; at most ten open trades. Duration/profit basis points: 60s/7000, 90s/7200, 120s/7500, 180s/7800, 360s/8000. These are demo rules, not advertised returns. Configuration affects newly accepted trades; the accepted payout is copied to each trade.

Price units equal 0.000001 USDT. For Unix second t and pair phase crc32(symbol)%1000 / 100, the simulation is base × [1 + .012 sin(t/270+phase) + .005 sin(t/53+phase) + .002 sin(t/13)]. Round to an integer price unit. Pair bases are seeded and not editable in the admin interface. The formula is public, predictable and educational; it is not suitable for real money. There are no user-specific price changes.

Acceptance uses the server timestamp, validates the fresh synchronous server quote, reserves stake, records exact expiry, request UUID and normalized payload hash. Replaying the same UUID returns the existing trade; reusing it with changed parameters fails. Frontend execution-price/outcome fields are never consumed.

Settlement computes the mathematical observation at the exact stored expiry second, even if workers run late. BUY wins when expiry exceeds entry; SELL wins when expiry is lower. Equality refunds the stake. On win, payout = stake + floor(stake × payout_bps / 10000); on loss payout = 0. Reserved stake is released, available receives payout, treasury balances profit/loss. Trade status and unique settlement are committed in the same transaction. Run php -d extension=intl artisan schedule:work or trades:settle manually. Events broadcast only after commit. Admins can inspect outcomes but cannot alter them.

This demo feed is always synchronous. A future external adapter must preserve an independently timestamped immutable price history, reject stale quotes, and define missing-expiry observation handling before use. Do not substitute a client timestamp or submitted price.
