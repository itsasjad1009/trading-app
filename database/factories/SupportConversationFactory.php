<?php

namespace Database\Factories;

use App\Models\SupportConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupportConversationFactory extends Factory
{
    protected $model = SupportConversation::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'status' => 'open'];
    }
}
