<?php

namespace App\Jobs;

use App\MarketData\History;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ReconcileMarketHistory implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function __construct(public array $symbols, public int $endMs) {}

    public function handle(History $history): void
    {
        foreach ($this->symbols as $symbol) {
            $end = intdiv($this->endMs, 60000) * 60000 - 60000;
            $history->backfill($symbol, 60, $end - 9 * 60000, $end);
        }
    }
}
