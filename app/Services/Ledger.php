<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class Ledger
{
    public function accounts(int $userId): array
    {
        $wallet = DB::table('wallets')->where('user_id', $userId)->first();
        if (! $wallet) {
            $id = DB::table('wallets')->insertGetId(['user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);
        } else {
            $id = $wallet->id;
        }
        $accounts = [];
        foreach (['available', 'reserved'] as $kind) {
            $code = "wallet:$id:$kind";
            DB::table('ledger_accounts')->insertOrIgnore(['wallet_id' => $id, 'code' => $code, 'created_at' => now(), 'updated_at' => now()]);
            $accounts[$kind] = DB::table('ledger_accounts')->where('code', $code)->value('id');
        }
        DB::table('ledger_accounts')->insertOrIgnore(['code' => 'demo:treasury', 'created_at' => now(), 'updated_at' => now()]);
        $accounts['treasury'] = DB::table('ledger_accounts')->where('code', 'demo:treasury')->value('id');

        return $accounts;
    }

    public function balance(int $accountId): int
    {
        return (int) DB::table('ledger_entries')->where('ledger_account_id', $accountId)->sum('amount_minor');
    }

    public function wallet(int $userId): array
    {
        $balances = DB::table('ledger_accounts')->join('wallets', 'wallets.id', '=', 'ledger_accounts.wallet_id')->leftJoin('ledger_entries', 'ledger_entries.ledger_account_id', '=', 'ledger_accounts.id')->where('wallets.user_id', $userId)->groupBy('ledger_accounts.code')->selectRaw('ledger_accounts.code, COALESCE(SUM(ledger_entries.amount_minor),0) AS total')->get();
        $wallet = ['available' => '0.00', 'reserved' => '0.00'];
        foreach ($balances as $row) {
            $kind = str_ends_with($row->code, ':reserved') ? 'reserved' : 'available';
            $wallet[$kind] = Money::format((int) $row->total);
        }

        return $wallet;
    }

    // Caller locks the user row before posting; all monetary mutation paths use that mutex.
    public function post(int $userId, string $key, string $type, string $description, array $entries): int
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Ledger posting requires a database transaction');
        }
        if (array_sum(array_values($entries)) !== 0) {
            throw new \LogicException('Unbalanced ledger transaction');
        }
        $existing = DB::table('ledger_transactions')->where('idempotency_key', $key)->first();
        if ($existing) {
            $posted = DB::table('ledger_entries')->where('ledger_transaction_id', $existing->id)->pluck('amount_minor', 'ledger_account_id')->all();
            ksort($posted);
            ksort($entries);
            if ($existing->user_id !== $userId || $existing->type !== $type || $posted != $entries) {
                throw new \LogicException('Ledger idempotency payload mismatch');
            }

            return $existing->id;
        }
        $id = DB::table('ledger_transactions')->insertGetId(['user_id' => $userId, 'reference' => (string) Str::uuid(), 'idempotency_key' => $key, 'type' => $type, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($entries as $account => $amount) {
            DB::table('ledger_entries')->insert(['ledger_transaction_id' => $id, 'ledger_account_id' => $account, 'amount_minor' => $amount, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $id;
    }

    public function fund(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            $a = $this->accounts($userId);
            $amount = config('trading.initial_funding');
            $this->post($userId, "initial:$userId", 'demo_funding', 'Initial virtual demo credits', [$a['available'] => $amount, $a['treasury'] => -$amount]);
        }, 5);
    }

    public function requireBalance(int $account, int $amount): void
    {
        if ($this->balance($account) < $amount) {
            throw ValidationException::withMessages(['amount' => 'Insufficient available demo balance.']);
        }
    }
}
