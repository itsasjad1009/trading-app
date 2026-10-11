<?php

namespace App\MarketData;

use App\MarketData\Contracts\HistoricalCandleProvider;
use App\MarketData\Contracts\LiveTradeStreamProvider;
use App\MarketData\Contracts\MarketDataProvider;
use App\MarketData\Contracts\MarketTickerProvider;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class BinanceSpotProvider implements HistoricalCandleProvider, LiveTradeStreamProvider, MarketDataProvider, MarketTickerProvider
{
    public function __construct(private RequestBudget $budget) {}

    public function identity(): string
    {
        return 'binance_spot';
    }

    private function get(string $path, array $query, int $weight): array
    {
        if (! app(AccessGate::class)->workerAllowed()) {
            throw new \RuntimeException('Live feed disabled');
        }

        return $this->budget->request($weight, fn () => Http::acceptJson()->connectTimeout(5)->timeout(15)->get(config('market-data.rest_url').$path, $query))->json();
    }

    public function metadata(): array
    {
        return Cache::remember('market:metadata', 3600, function () {
            $d = $this->get('/api/v3/exchangeInfo', ['symbols' => json_encode(config('market-data.symbols'))], 20);
            if (! isset($d['symbols'])) {
                throw new InvalidArgumentException('Invalid exchange metadata');
            }$this->budget->metadata($d);

            return $d;
        });
    }

    public function validateSymbol(string $symbol): void
    {
        $this->symbol($symbol);
        foreach ($this->metadata()['symbols'] as $s) {
            if ($s['symbol'] === $symbol && $s['status'] === 'TRADING' && ($s['isSpotTradingAllowed'] ?? false)) {
                return;
            }
        }throw new InvalidArgumentException('Symbol not available for Spot');
    }

    private function symbol(string $symbol): void
    {
        if (! in_array($symbol, config('market-data.symbols'), true)) {
            throw new InvalidArgumentException('Unsupported symbol');
        }
    }

    private function timestamp(mixed $value): int
    {
        if (! is_int($value) || $value < 1000000000000 || $value > now()->getTimestampMs() + 60000) {
            throw new InvalidArgumentException('Invalid millisecond timestamp');
        }

        return $value;
    }

    public function serverTime(): array
    {
        $startNs = hrtime(true);
        $sent = now()->getTimestampMs();
        $d = $this->get('/api/v3/time', [], 1);
        $received = now()->getTimestampMs();
        $endNs = hrtime(true);

        $serverTime = $this->timestamp($d['serverTime'] ?? null);
        $midpoint = intdiv($sent + $received, 2);
        $rtt = max(0, $received - $sent);
        $monotonicRttMs = ($endNs - $startNs) / 1e6;

        return [
            'server_time_ms' => $serverTime,
            'local_sent_ms' => $sent,
            'local_received_ms' => $received,
            'midpoint_ms' => $midpoint,
            'offset_ms' => $serverTime - $midpoint,
            'round_trip_ms' => $rtt,
            'monotonic_rtt_ms' => $monotonicRttMs,
            'uncertainty_ms' => (int) ceil($rtt / 2),
        ];
    }

    public function prices(array $symbols): array
    {
        foreach ($symbols as $s) {
            $this->symbol($s);
        }

        return $this->get('/api/v3/ticker/price', ['symbols' => json_encode($symbols)], 4);
    }

    public function tickers(array $symbols): array
    {
        foreach ($symbols as $s) {
            $this->symbol($s);
        }$rows = $this->get('/api/v3/ticker/24hr', ['symbols' => json_encode($symbols)], 2);

        return array_map(fn ($r) => $this->normalize(['e' => '24hrTicker', 's' => $r['symbol'], 'E' => $r['closeTime'], 'c' => $r['lastPrice'], 'P' => $r['priceChangePercent'], 'v' => $r['volume'], 'q' => $r['quoteVolume']], now()->getTimestampMs()), $rows);
    }

    public function streamUrl(array $symbols): string
    {
        $streams = [];
        foreach ($symbols as $s) {
            $this->symbol($s);
            foreach (['trade', 'kline_1m', 'ticker'] as $suffix) {
                $streams[] = strtolower($s).'@'.$suffix;
            }
        }if (! $streams) {
            throw new InvalidArgumentException('No stream symbols');
        }

        return config('market-data.stream_url').'/stream?streams='.implode('/', $streams);
    }

    public function candle(string $symbol, int $interval, array $r, string $source = 'rest', ?bool $closed = null, ?int $updated = null): array
    {
        $this->symbol($symbol);
        if (! isset(config('market-data.intervals')[$interval]) || count($r) < 9) {
            throw new InvalidArgumentException('Invalid candle');
        }
        $open = $this->timestamp($r[0]);
        if ($open % ($interval * 1000) !== 0 || ! is_int($r[6]) || $r[6] !== $open + $interval * 1000 - 1) {
            throw new InvalidArgumentException('Invalid candle boundaries');
        }
        $d = ['provider' => $this->identity(), 'market_type' => 'spot', 'symbol' => $symbol, 'interval_seconds' => $interval, 'open_ms' => $open, 'close_ms' => $r[6], 'open' => Decimal::value($r[1], true), 'high' => Decimal::value($r[2], true), 'low' => Decimal::value($r[3], true), 'close' => Decimal::value($r[4], true), 'volume' => Decimal::value($r[5]), 'quote_volume' => Decimal::value($r[7]), 'closed' => $closed ?? ($r[6] < now()->getTimestampMs()), 'source' => $source, 'updated_ms' => $updated ?? now()->getTimestampMs()];
        if (BigDecimal::of($d['low'])->isGreaterThan($d['high']) || BigDecimal::of($d['open'])->isLessThan($d['low']) || BigDecimal::of($d['close'])->isLessThan($d['low']) || BigDecimal::of($d['open'])->isGreaterThan($d['high']) || BigDecimal::of($d['close'])->isGreaterThan($d['high'])) {
            throw new InvalidArgumentException('Invalid OHLC range');
        }

        return $d;
    }

    public function candles(string $symbol, int $interval, int $startMs, int $endMs): array
    {
        $this->validateSymbol($symbol);
        $name = config('market-data.intervals')[$interval] ?? throw new InvalidArgumentException('Unsupported interval');
        if ($endMs < $startMs || $endMs - $startMs > $interval * 1000 * 5000) {
            throw new InvalidArgumentException('History window exceeds 5000 candles');
        }
        $result = [];
        $cursor = $startMs;
        while ($cursor <= $endMs) {
            $rows = $this->get('/api/v3/klines', ['symbol' => $symbol, 'interval' => $name, 'startTime' => $cursor, 'endTime' => $endMs, 'limit' => 1000, 'timeZone' => '0'], 2);
            if (! $rows) {
                break;
            }$max = $cursor - 1;
            foreach ($rows as $r) {
                $c = $this->candle($symbol, $interval, $r);
                if ($c['open_ms'] < $cursor || $c['open_ms'] > $endMs) {
                    throw new InvalidArgumentException('Candle outside requested window');
                }$result[$c['open_ms']] = $c;
                $max = max($max, $c['open_ms']);
            }if ($max < $cursor) {
                throw new InvalidArgumentException('History pagination did not advance');
            }$cursor = $max + $interval * 1000;
            if (count($rows) < 1000) {
                break;
            }
        }ksort($result);

        return array_values($result);
    }

    public function normalize(array $payload, int $receivedMs): array
    {
        $p = $payload['data'] ?? $payload;
        if (($p['e'] ?? null) === 'serverShutdown') {
            return ['kind' => 'shutdown'];
        }$symbol = $p['s'] ?? '';
        $this->symbol($symbol);
        $event = $this->timestamp($p['E'] ?? null);
        $base = ['provider' => $this->identity(), 'market_type' => 'spot', 'symbol' => $symbol, 'event_ms' => $event, 'received_ms' => $receivedMs, 'source' => 'websocket', 'valid' => true];

        return match ($p['e'] ?? null) {
            'trade' => array_merge($base, ['kind' => 'trade', 'trade_id' => $this->tradeId($p['t'] ?? null), 'price' => Decimal::value($p['p'] ?? null, true), 'quantity' => Decimal::value($p['q'] ?? null, true), 'trade_ms' => $this->timestamp($p['T'] ?? null)]),
            '24hrTicker' => array_merge($base, ['kind' => 'ticker', 'price' => Decimal::value($p['c'] ?? null, true), 'change' => Decimal::signed($p['P'] ?? null), 'volume' => Decimal::value($p['v'] ?? null), 'quote_volume' => Decimal::value($p['q'] ?? null)]),
            'kline' => $this->normalizeKline($p, $base),
            default => throw new InvalidArgumentException('Unknown event type'),
        };
    }

    private function tradeId(mixed $id): int
    {
        if (! is_int($id) || $id < 0) {
            throw new InvalidArgumentException('Invalid trade ID');
        }

        return $id;
    }

    private function normalizeKline(array $p, array $base): array
    {
        $k = $p['k'] ?? [];
        if (($k['s'] ?? null) !== $base['symbol'] || ($k['i'] ?? null) !== '1m' || ! is_bool($k['x'] ?? null)) {
            throw new InvalidArgumentException('Invalid kline');
        }$c = $this->candle($base['symbol'], 60, [$k['t'], $k['o'], $k['h'], $k['l'], $k['c'], $k['v'], $k['T'], $k['q'], $k['n']], 'websocket', $k['x'], $base['event_ms']);

        return array_merge($base, ['kind' => 'candle', 'candle' => $c]);
    }
}
