<?php

namespace App\Policies;

use App\Models\SupportConversation;
use App\Models\User;

class SupportConversationPolicy
{
    public function view(User $user, SupportConversation $conversation): bool
    {
        return $user->status === 'active' && $user->hasVerifiedEmail()
            && ($user->id === $conversation->user_id || in_array($user->role, ['super_admin', 'support_agent'], true));
    }
}
