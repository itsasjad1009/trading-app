<?php

use App\MarketData\BinanceSpotProvider;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$results = [];
foreach (config('market-data.symbols') as $symbol) {
    $row = DB::table('market_candles')->where('symbol', $symbol)->where('interval_seconds', 60)->where('closed', true)->where('source', 'websocket')->orderByDesc('open_ms')->first();
    if (! $row) {
        $results[$symbol] = ['status' => 'no_closed_websocket_candle'];

        continue;
    }
    $rest = app(BinanceSpotProvider::class)->candles($symbol, 60, (int) $row->open_ms, (int) $row->open_ms)[0] ?? null;
    $matches = [];
    foreach (['open', 'high', 'low', 'close', 'volume', 'quote_volume'] as $field) {
        $matches[$field] = $rest && BigDecimal::of($row->$field)->isEqualTo($rest[$field]);
    }
    $results[$symbol] = ['open_ms' => $row->open_ms, 'matches' => $matches, 'status' => in_array(false, $matches, true) ? 'mismatch' : 'match'];
}
file_put_contents(base_path('test-artifacts/market-data/candle-consistency.json'), json_encode($results, JSON_PRETTY_PRINT));
echo json_encode($results, JSON_PRETTY_PRINT);
