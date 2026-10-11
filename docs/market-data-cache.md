# Shared display cache

Uses configured Laravel cache (database locally; Redis supported through CACHE_STORE=redis). Array cache is for isolated tests only and cannot coordinate multiple processes. File/database/Redis stores need all web and worker processes to use the same namespace/store.

Keys: market:price:SYMBOL and market:ticker:SYMBOL retain last display values for 300s, with explicit stale classification after 15s; market:current:SYMBOL 120s; market:history:SYMBOL:INTERVAL:WINDOW 30s; market:metadata 3600s; market:health/budget/clock 86400s; market:heartbeat 60s. A missing or >15s heartbeat reports DISCONNECTED regardless of retained prices.

market:rest:serial lock serializes requests; market:backfill locks prevent duplicate history loads; leader lease renews under market:leader:guard every <=10s with 45s crash expiry. Do not run multiple cache namespaces against the same feed.

Retention: market:prune hourly, minimum24h observation retention / minimum30d candles /30d incidents. Old observations preceding active simulated positions are conservatively retained. Live settlement is refused and prune fails closed for any non-simulated settlement source. DB growth must be sized before longer runs; storing every raw trade can grow quickly. Pruning deletes only expiry-qualified market rows, never users/trades/ledger.

No settlement reads display cache. Charts never manufacture missing real candles. Complete closed stored windows avoid REST; windows with gaps or a current partial candle refresh through shared bounded REST requests.
