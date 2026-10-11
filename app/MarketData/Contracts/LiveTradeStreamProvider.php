<?php

namespace App\MarketData\Contracts;

interface LiveTradeStreamProvider
{
    public function streamUrl(array $symbols): string;

    public function normalize(array $payload, int $receivedMs): array;
}
