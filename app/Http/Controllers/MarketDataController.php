<?php

namespace App\Http\Controllers;

use App\MarketData\AccessGate;
use App\MarketData\Display;
use App\MarketData\FeedHealth;
use App\MarketData\History;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class MarketDataController extends Controller
{
    public function snapshot(Request $request, AccessGate $gate, Display $display)
    {
        abort_unless($gate->allows($request), 403);

        return response()->json(['markets' => $display->snapshot(), 'health' => app(FeedHealth::class)->health()]);
    }

    public function candles(Request $request, AccessGate $gate, History $history)
    {
        abort_unless($gate->allows($request), 403);
        $data = $request->validate(['pair_id' => 'required|integer', 'interval' => 'required|integer|in:60,300,900,1800,3600,86400']);
        $p = DB::table('trading_pairs')->where('enabled', true)->where('data_provider', config('market-data.provider'))->where('market_type', 'spot')->find($data['pair_id']);
        abort_unless($p && $p->exchange_symbol, 404);
        try {
            $result = $history->window($p->exchange_symbol, $data['interval']);

            return response()->json($result + ['source' => config('market-data.provider'), 'market_type' => 'spot', 'health' => app(FeedHealth::class)->symbol($p->exchange_symbol)]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['candles' => [], 'source' => config('market-data.provider'), 'health' => ['state' => 'DISCONNECTED'], 'error' => 'Exchange history unavailable'], 503);
        }
    }

    public function health(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['super_admin', 'operations_admin', 'auditor']), 403);

        return response()->json(['health' => app(FeedHealth::class)->health(), 'symbols' => array_map(fn ($s) => ['symbol' => $s] + app(FeedHealth::class)->symbol($s), config('market-data.symbols')), 'observations' => DB::table('market_price_observations')->count(), 'candles' => DB::table('market_candles')->count(), 'recent_incidents' => DB::table('market_data_incidents')->latest('id')->limit(20)->get()]);
    }
}
