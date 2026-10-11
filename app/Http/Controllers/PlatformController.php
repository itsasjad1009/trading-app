<?php

namespace App\Http\Controllers;

use App\Services\DemoMarket;
use App\Services\Money;
use App\Services\TradingEngine;
use App\Services\TradingRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PlatformController extends Controller
{
    public function page(Request $request, DemoMarket $market)
    {
        $page = $request->route()->getName();
        $id = $request->user()->id;
        $trades = DB::table('trades')->join('trading_pairs', 'trading_pairs.id', '=', 'trades.trading_pair_id')->leftJoin('trade_settlements', 'trade_settlements.trade_id', '=', 'trades.id')->where('trades.user_id', $id);
        $totals = (clone $trades)->selectRaw("COUNT(*) AS total, SUM(trades.status = 'open') AS open, SUM(trades.status = 'won') AS wins, SUM(trades.status = 'lost') AS losses, COALESCE(SUM(profit_minor),0) AS profit")->first();
        $stats = ['open' => (int) $totals->open, 'total' => (int) $totals->total, 'wins' => (int) $totals->wins, 'losses' => (int) $totals->losses, 'profit' => Money::format((int) $totals->profit)];
        $kyc = DB::table('kyc_applications')->where('user_id', $id)->latest('id')->first(['id', 'status', 'review_note', 'created_at']);
        $data = ['page' => $page, 'stats' => $stats, 'kyc' => $kyc, 'markets' => $market->snapshot(), 'server_time' => now()->toIso8601String(), 'payout_rates' => app(TradingRules::class)->payouts()];
        $live = app(\App\MarketData\AccessGate::class)->allows($request);
        $data['live_display'] = $live;
        $data['market_channel_public'] = app(\App\MarketData\AccessGate::class)->publicAllowed();
        $data['simulation_markets'] = $data['markets'];
        if ($live) { $data['markets'] = app(\App\MarketData\Display::class)->snapshot(); }
        if (in_array($page, ['dashboard', 'trade', 'positions'])) {
            $filtered = (clone $trades)->select('trades.*', 'trading_pairs.symbol', 'expiry_price_units', 'profit_minor', 'payout_minor');
            if ($request->filled('search')) {
                $filtered->where('symbol', 'like', '%'.substr($request->string('search'), 0, 30).'%');
            }
            if (in_array($request->input('status'), ['open', 'won', 'lost', 'tie'])) {
                $filtered->where('trades.status', $request->input('status'));
            }
            $data['trades'] = $filtered->orderBy('trades.id', $request->input('sort') === 'oldest' ? 'asc' : 'desc')->paginate(15)->withQueryString();
        }
        if ($page === 'transactions') {
            $history = DB::table('ledger_transactions')->join('ledger_entries', 'ledger_entries.ledger_transaction_id', '=', 'ledger_transactions.id')->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.ledger_account_id')->join('wallets', 'wallets.id', '=', 'ledger_accounts.wallet_id')->where('ledger_transactions.user_id', $id)->where('wallets.user_id', $id)->select('ledger_transactions.*')->selectRaw("SUM(CASE WHEN ledger_accounts.code LIKE '%:available' THEN ledger_entries.amount_minor ELSE 0 END) AS amount_minor, SUM(CASE WHEN ledger_accounts.code LIKE '%:reserved' THEN ledger_entries.amount_minor ELSE 0 END) AS reserved_minor")->groupBy('ledger_transactions.id');
            if (in_array($request->input('type'), ['demo_funding', 'trade_stake', 'trade_settlement', 'withdrawal_reserve', 'demo_withdrawal', 'refund'])) {
                $history->where('ledger_transactions.type', $request->input('type'));
            }
            $data['transactions'] = $history->latest('ledger_transactions.id')->paginate(20)->withQueryString()->through(function ($row) { $row->amount_minor = (int) $row->amount_minor; $row->reserved_minor = (int) $row->reserved_minor; return $row; });
        }
        if ($page === 'support') {
            $request->validate(['messages_before' => 'nullable|integer|min:1']);
            $conversation = DB::table('support_conversations')->where('user_id', $id)->first();
            $data['conversation_status'] = $conversation?->status ?? 'open';
            $data['messages'] = [];
            $data['older_messages_url'] = null;
            if ($conversation) {
                $query = DB::table('support_messages')->where('support_conversation_id', $conversation->id);
                if ($request->filled('messages_before')) {
                    $query->where('id', '<', (int) $request->input('messages_before'));
                }
                $rows = $query->latest('id')->limit(51)->get(['id', 'user_id', 'body', 'attachment', 'read_at', 'created_at']);
                $hasOlder = $rows->count() > 50;
                $rows = $rows->take(50)->reverse()->values();
                if ($hasOlder) {
                    $data['older_messages_url'] = route('support', ['messages_before' => $rows->first()->id]);
                }
                DB::table('support_messages')->whereIn('id', $rows->pluck('id'))->where('user_id', '!=', $id)->whereNull('read_at')->update(['read_at' => now()]);
                foreach ($rows as $message) {
                    if ($message->user_id !== $id) {
                        $message->read_at = $message->read_at ?? now()->toDateTimeString();
                    }
                }
                $data['messages'] = $rows;
            }
        }
        if ($page === 'profile') {
            $data['referrals'] = DB::table('referrals')->where('referrer_id', $id)->count();
        }
        if (in_array($page, ['deposit', 'withdraw'])) {
            $data['requests'] = DB::table($page === 'deposit' ? 'deposit_requests' : 'withdrawal_requests')->where('user_id', $id)->latest()->paginate(10, ['id', 'amount_minor', 'status', 'created_at', 'review_note']);
        }

        return Inertia::render('Platform', $data);
    }

    public function candles(Request $request, DemoMarket $market)
    {
        $input = $request->validate(['pair_id' => 'required|integer|exists:trading_pairs,id', 'interval' => 'required|integer|in:60,300,900,1800,3600,86400']);
        $pair = DB::table('trading_pairs')->where('enabled', true)->find($input['pair_id']);
        abort_unless($pair, 404);

        return response()->json(['candles' => $market->candles($pair, $input['interval']), 'quote' => $market->quote($pair)]);
    }

    public function place(Request $request, TradingEngine $engine)
    {
        $input = $request->validate(['amount' => 'required|string', 'pair_id' => 'required|integer', 'direction' => 'required|in:buy,sell', 'duration' => 'required|integer', 'request_key' => 'required|uuid']);
        $id = $engine->place($request->user()->id, $input);

        return back()->with('success',"Demo trade #$id accepted.");
    }
}
