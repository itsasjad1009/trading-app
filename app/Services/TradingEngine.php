<?php

namespace App\Services;

use App\Events\AccountUpdated;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TradingEngine
{
    public function __construct(private Ledger $ledger, private DemoMarket $market) {}

    public function place(int $userId, array $input): int
    {
        abort_unless(config('market-data.settlement_source') === 'simulated', 503, 'Live-observation settlement is not enabled.');
        return DB::transaction(function () use ($userId, $input) {
            $user = DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            if (! $user || $user->status !== 'active' || ! $user->email_verified_at) {
                abort(403);
            }
            $amount = Money::parse((string) $input['amount']);
            $hash = hash('sha256', json_encode([$input['pair_id'], $input['direction'], $amount, $input['duration']]));
            $existing = DB::table('trades')->where('user_id', $userId)->where('request_key', $input['request_key'])->first();
            if ($existing) {
                if ($existing->request_hash !== $hash) {
                    throw ValidationException::withMessages(['request_key' => 'This request key was already used with different parameters.']);
                }

                return $existing->id;
            }
            if ($amount < config('trading.minimum_stake') || $amount > config('trading.maximum_stake')) {
                throw ValidationException::withMessages(['amount' => 'Stake must be between 100 and 10,000 demo USDT.']);
            }
            $pair = DB::table('trading_pairs')->where('id', $input['pair_id'])->where('enabled', true)->first();
            if (! $pair) {
                throw ValidationException::withMessages(['pair_id' => 'Unsupported pair.']);
            }
            $rates = app(TradingRules::class)->payouts();
            $duration = (int) $input['duration'];
            if (! isset($rates[$duration])) {
                throw ValidationException::withMessages(['duration' => 'Unsupported expiry duration.']);
            }
            if (! in_array($input['direction'], ['buy', 'sell'], true)) {
                throw ValidationException::withMessages(['direction' => 'Invalid direction.']);
            }
            if (DB::table('trades')->where('user_id', $userId)->where('status', 'open')->count() >= config('trading.maximum_open_trades')) {
                throw ValidationException::withMessages(['amount' => 'Maximum open positions reached.']);
            }
            // Demo adapter computes observations synchronously using the authoritative server clock.
            $quote = $this->market->quote($pair);
            if (now()->diffInSeconds(Carbon::parse($quote['observed_at']), true) > config('trading.feed_max_age')) {
                throw ValidationException::withMessages(['pair_id' => 'Market data is stale.']);
            }
            $a = $this->ledger->accounts($userId);
            $this->ledger->requireBalance($a['available'], $amount);
            $opened = now();
            $id = DB::table('trades')->insertGetId(['user_id' => $userId, 'trading_pair_id' => $pair->id, 'request_key' => $input['request_key'], 'request_hash' => $hash, 'direction' => $input['direction'], 'stake_minor' => $amount, 'entry_price_units' => $this->market->price($pair, $opened->timestamp), 'duration' => $duration, 'payout_bps' => $rates[$duration], 'opened_at' => $opened, 'expires_at' => $opened->copy()->addSeconds($duration), 'created_at' => $opened, 'updated_at' => $opened]);
            $this->ledger->post($userId, "trade:$id:stake", 'trade_stake', "Reserved stake for trade #$id", [$a['available'] => -$amount, $a['reserved'] => $amount]);
            DB::afterCommit(fn () => AccountUpdated::dispatch($userId));

            return $id;
        }, 5);
    }

    public function settle(int $tradeId): void
    {
        $owner = DB::table('trades')->where('id', $tradeId)->value('user_id');
        if (! $owner) {
            return;
        }
        DB::transaction(function () use ($tradeId, $owner) {
            DB::table('users')->where('id', $owner)->lockForUpdate()->first();
            $trade = DB::table('trades')->where('id', $tradeId)->lockForUpdate()->first();
            if ($trade->status !== 'open' || Carbon::parse($trade->expires_at)->isFuture()) {
                return;
            }
            $pair = DB::table('trading_pairs')->find($trade->trading_pair_id);
            $expiry = $this->market->price($pair, Carbon::parse($trade->expires_at)->timestamp);
            $tie = $expiry === $trade->entry_price_units;
            $won = ($trade->direction === 'buy' && $expiry > $trade->entry_price_units) || ($trade->direction === 'sell' && $expiry < $trade->entry_price_units);
            $profit = $tie ? 0 : ($won ? intdiv($trade->stake_minor * $trade->payout_bps, 10000) : -$trade->stake_minor);
            $payout = $trade->stake_minor + $profit;
            $a = $this->ledger->accounts($owner);
            $this->ledger->post($owner, "trade:$tradeId:settlement", 'trade_settlement', "Expiry settlement for trade #$tradeId", [$a['reserved'] => -$trade->stake_minor, $a['available'] => $payout, $a['treasury'] => -$profit]);
            DB::table('trade_settlements')->insert(['trade_id' => $tradeId, 'expiry_price_units' => $expiry, 'profit_minor' => $profit, 'payout_minor' => $payout, 'observed_at' => $trade->expires_at, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('trades')->where('id', $tradeId)->update(['status' => $tie ? 'tie' : ($won ? 'won' : 'lost'), 'updated_at' => now()]);
            DB::afterCommit(fn () => AccountUpdated::dispatch($owner));
        }, 5);
    }
}
