<?php
return [
 'enabled' => env('MARKET_DATA_ENABLED', false),
 'public_redistribution' => env('MARKET_DATA_PUBLIC_REDISTRIBUTION', false),
 'rights_verified' => env('MARKET_DATA_RIGHTS_VERIFIED', false),
 'provider' => 'binance_spot',
 'rest_url' => 'https://data-api.binance.vision',
 'stream_url' => 'wss://data-stream.binance.vision:443',
 'symbols' => ['BTCUSDT','ETHUSDT','LTCUSDT','DOGEUSDT','XRPUSDT','ADAUSDT'],
 'intervals' => [60=>'1m',300=>'5m',900=>'15m',1800=>'30m',3600=>'1h',86400=>'1d'],
 'stale_seconds' => 15, 'display_ttl' => 300, 'history_ttl' => 30,
 'request_weight_limit' => 600, 'safety_margin' => 0.8,
 'observation_retention_hours' => 24, 'candle_retention_days' => 30,
 'settlement_source' => 'simulated', // Live settlement cannot be enabled in this phase.
];
