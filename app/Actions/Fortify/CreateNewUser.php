<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[+\d ()-]+$/'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'referral_code' => ['nullable', 'exists:users,referral_code'],
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create(['name' => $input['name'], 'email' => strtolower($input['email']), 'phone' => $input['phone'] ?? null, 'password' => $input['password'], 'referral_code' => strtoupper(Str::random(10))]);
            app(Ledger::class)->fund($user->id);
            if (! empty($input['referral_code'])) {
                DB::table('referrals')->insert(['referrer_id' => User::where('referral_code', $input['referral_code'])->value('id'), 'referred_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $user;
        });
    }
}
