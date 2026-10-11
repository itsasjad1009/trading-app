<?php

namespace App\MarketData\Contracts;

interface HistoricalCandleProvider
{
    public function candles(string $symbol, int $interval, int $startMs, int $endMs): array;
}
