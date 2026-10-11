<?php

return [
    'mode' => 'demo',
    'minimum_stake' => 10000,
    'maximum_stake' => 1000000,
    'maximum_open_trades' => 10,
    'initial_funding' => 1000000,
    'payout_bps' => [60 => 7000, 90 => 7200, 120 => 7500, 180 => 7800, 360 => 8000],
    'feed_max_age' => 30,
    'kyc_max_kb' => 5120,
    'kyc_retention_days' => 90,
];
