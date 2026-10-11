<?php

namespace App\MarketData\Contracts;

interface MarketTickerProvider
{
    public function tickers(array $symbols): array;
}
