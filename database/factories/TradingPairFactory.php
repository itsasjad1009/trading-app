<?php

namespace Database\Factories;

use App\Models\TradingPair;
use Illuminate\Database\Eloquent\Factories\Factory;

class TradingPairFactory extends Factory
{
    protected $model = TradingPair::class;

    public function definition(): array
    {
        return ['symbol' => strtoupper(fake()->unique()->lexify('???')).'/USDT', 'name' => 'Demo asset', 'base_price_units' => 100000000, 'enabled' => true];
    }
}
