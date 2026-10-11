<?php

namespace App\Services;

use App\Events\AccountUpdated;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class WalletRequests
{
    public function funding(int $userId, array $input): int
    {
        Validator::make($input, ['amount' => 'required|string', 'request_key' => 'required|uuid'])->validate();
        $amount = Money::parse($input['amount']);
        if ($amount < 10000 || $amount > 1000000) {
            throw ValidationException::withMessages(['amount' => 'Request between 100 and 10,000 demo USDT.']);
        }

        return DB::transaction(function () use ($userId, $input, $amount) {
            $this->customer($userId);
            $existing = DB::table('deposit_requests')->where('user_id', $userId)->where('request_key', $input['request_key'])->first();
            if ($existing) {
                $this->same($existing->amount_minor === $amount);

                return $existing->id;
            }

            return DB::table('deposit_requests')->insertGetId(['user_id' => $userId, 'request_key' => $input['request_key'], 'amount_minor' => $amount, 'created_at' => now(), 'updated_at' => now()]);
        }, 5);
    }

    public function withdrawal(int $userId, array $input): int
    {
        Validator::make($input, ['amount' => 'required|string', 'request_key' => 'required|uuid', 'method' => 'required|in:crypto,bank', 'destination' => 'required|string|min:8|max:250', 'password' => 'required|string'])->validate();
        $amount = Money::parse($input['amount']);
        if ($amount < 100) {
            throw ValidationException::withMessages(['amount' => 'Minimum demo withdrawal is 1 USDT.']);
        }

        return DB::transaction(function () use ($userId, $input, $amount) {
            $user = $this->customer($userId);
            if (! Hash::check($input['password'], $user->password)) {
                throw ValidationException::withMessages(['password' => 'Authorization password is incorrect.']);
            }
            $existing = DB::table('withdrawal_requests')->where('user_id', $userId)->where('request_key', $input['request_key'])->first();
            if ($existing) {
                $this->same($existing->amount_minor === $amount && $existing->method === $input['method'] && decrypt($existing->destination) === $input['destination']);

                return $existing->id;
            }
            if ($user->two_factor_confirmed_at && ! app(TwoFactorAuthenticationProvider::class)->verify(decrypt($user->two_factor_secret), (string) ($input['code'] ?? ''))) {
                throw ValidationException::withMessages(['code' => 'Authenticator code is incorrect or already used.']);
            }
            $ledger = app(Ledger::class);
            $a = $ledger->accounts($userId);
            $ledger->requireBalance($a['available'], $amount);
            $id = DB::table('withdrawal_requests')->insertGetId(['user_id' => $userId, 'request_key' => $input['request_key'], 'amount_minor' => $amount, 'method' => $input['method'], 'destination' => encrypt($input['destination']), 'created_at' => now(), 'updated_at' => now()]);
            $ledger->post($userId, "withdrawal:$id:reserve", 'withdrawal_reserve', "Reserved demo withdrawal #$id", [$a['available'] => -$amount, $a['reserved'] => $amount]);
            DB::afterCommit(fn () => AccountUpdated::dispatch($userId));

            return $id;
        }, 5);
    }

    private function customer(int $id): User
    {
        $user = User::whereKey($id)->lockForUpdate()->firstOrFail();
        abort_unless($user->status === 'active' && $user->hasVerifiedEmail(), 403);

        return $user;
    }

    private function same(bool $same): void
    {
        if (! $same) {
            throw ValidationException::withMessages(['request_key' => 'This request key was already used with different parameters.']);
        }
    }
}
