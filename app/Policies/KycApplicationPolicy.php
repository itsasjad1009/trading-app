<?php

namespace App\Policies;

use App\Models\KycApplication;
use App\Models\User;

class KycApplicationPolicy
{
    public function view(User $user, KycApplication $application): bool
    {
        return $user->status === 'active' && $user->hasVerifiedEmail()
            && ($user->id === $application->user_id || in_array($user->role, ['super_admin', 'kyc_officer'], true));
    }
}
