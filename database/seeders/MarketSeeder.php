<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MarketSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['BTC/USDT', 'Bitcoin', 83000], ['ETH/USDT', 'Ethereum', 2500], ['LTC/USDT', 'Litecoin', 64], ['DOGE/USDT', 'Dogecoin', .086], ['XRP/USDT', 'XRP', 1.4], ['ADA/USDT', 'Cardano', .25]] as [$symbol,$name,$base]) {
            DB::table('trading_pairs')->insertOrIgnore(['symbol' => $symbol, 'exchange_symbol' => str_replace('/', '', $symbol), 'data_provider' => 'binance_spot', 'market_type' => 'spot', 'name' => $name, 'base_price_units' => (int) round($base * 1e6), 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
