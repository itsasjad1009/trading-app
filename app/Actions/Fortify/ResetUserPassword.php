<?php

namespace App\Actions\Fortify;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    public function reset($user, array $input): void
    {
        Validator::make($input, ['password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]])->validate();
        $user->forceFill(['password' => $input['password']])->save();
    }
}
