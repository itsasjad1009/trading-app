<?php

namespace App\MarketData\Contracts;

interface MarketDataHealthProvider
{
    public function health(): array;
}
