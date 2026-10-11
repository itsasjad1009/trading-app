# Market-data architecture

Laravel 12 / native PHP / MySQL 8.4 / React-Inertia / Reverb remain the stack. No exchange trading API, execution, custody or real-money processing is added.

Provider contracts live in app/MarketData/Contracts. BinanceSpotProvider implements REST metadata, history, ticker and normalized combined-stream events. FeedHealth implements the health contract. REST uses Laravel HTTP; streaming uses phrity/websocket 4.0 (PHP >=8.2, ISC), with PingResponder echoing server ping payloads and CloseHandler.

Flow: one leased market:stream process -> decimal-preserving normalization -> market_price_observations / market_candles -> shared display cache -> coalesced Reverb events -> React. The existing account channel remains private. History uses a per-symbol/interval backfill lock and UTC-aligned paginated REST windows. Larger intervals use verified direct REST candles, not incomplete one-minute aggregation.

Display delivery runs on the market-display queue through a unique DistributeMarketDisplay job. It reads current cache when processed rather than retaining old event payloads. Reverb failure is tracked separately from invalid provider data and cannot block the ingest loop. Queue workers must consume market-display and default. Reverb HTTP requests have bounded connection and request timeouts.

Pair administration validates the exchange symbol against provider metadata and the display symbol. Provider and market type are recorded separately; mapping changes with active positions are refused.

TradingEngine still calls DemoMarket exclusively. The order ticket shows its separate simulated execution quote when the exchange chart is enabled. config market-data.settlement_source is fixed to simulated; changing it to live_observation rejects all new orders. Existing simulated positions continue settling from the original deterministic engine. ObservationSelector is an audit-only candidate selector (last exchange trade at/before expiry, maximum age 1000ms, trade-ID tie-break). It is not wired to settlement and does not imply adequate coverage or licensing.

Persistence: unique provider/market/symbol/trade ID; decimal(36,18); exchange event_ms, trade_ms and local received_ms. Candles unique by provider/market/symbol/interval/open time. Closed candles cannot be overwritten by later partial candles. Invalid payloads are rejected; out-of-order observations are recorded but cannot rewind display prices.

Trade continuity cannot be reconstructed from candles. Reconnect reconciliation repairs recent candle history only. Live-observation settlement needs a separately reviewed coverage/finalization/gap/refund policy and immutable per-order source/observation references before activation.
