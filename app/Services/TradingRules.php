<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TradingRules
{
    public function payouts(): array
    {
        $value = DB::table('platform_settings')->where('key', 'payout_bps')->value('value');

        return $value ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : config('trading.payout_bps');
    }
}
