<?php

namespace Database\Factories;

use App\Models\KycApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class KycApplicationFactory extends Factory
{
    protected $model = KycApplication::class;

    public function definition(): array
    {
        return ['user_id' => User::factory(), 'full_name' => 'Sample Identity', 'national_id' => 'DEMO-'.fake()->numerify('######'), 'status' => 'pending', 'consented_at' => now()];
    }
}
