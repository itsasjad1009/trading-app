<?php

namespace App\Console\Commands;

use App\Jobs\DistributeMarketDisplay;
use App\Jobs\ReconcileMarketHistory;
use App\MarketData\AccessGate;
use App\MarketData\BinanceSpotProvider;
use App\MarketData\FeedHealth;
use App\MarketData\Ingestor;
use App\MarketData\StreamConnection;
use App\MarketData\WorkerLease;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use WebSocket\Exception\ConnectionTimeoutException;

final class MarketStream extends Command
{
    protected $signature = 'market:stream {--seconds=0 : Stop after a bounded development soak} {--report= : Workspace JSON report path} {--diagnostic= : Exclusive raw JSONL timing path} {--async : Persist through the durable bounded spool}';

    protected $description = 'Singleton public Spot ingestion for local paper-trading display';

    public static function retryDelay(int $attempt): int
    {
        return min(60, 2 ** min($attempt, 6)) + random_int(0, 3);
    }

    public function handle(BinanceSpotProvider $provider, Ingestor $ingest, FeedHealth $health, WorkerLease $lease, StreamConnection $connection): int
    {
        if (! app(AccessGate::class)->workerAllowed()) {
            $this->error('Feed is disabled or environment is not authorized.');

            return 1;
        }
        $owner = (string) Str::uuid();
        if (! $lease->renew($owner)) {
            $this->error('Another ingestion worker owns the lease.');

            return 1;
        }
        $stop = false;
        if (function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler(function () use (&$stop) {
                $stop = true;

                return true;
            });
        }
        $diagnostics = new \App\MarketData\PipelineDiagnostics;
        $diagnostics->open($this->option('diagnostic'));
        $started = microtime(true);
        $limit = (int) $this->option('seconds');
        $attempt = 0;
        $connections = 0;
        $lastBroadcast = 0;
        $lastLease = 0;
        $lastHealth = '';
        $clock = null;
        $error = null;
        $connectionSeconds = 0;
        $startRows = DB::table('market_price_observations')->count();
        try {
            $health->transition('CONNECTING');
            foreach (config('market-data.symbols') as $s) {
                $provider->validateSymbol($s);
            }$clock = $provider->serverTime();
            Cache::put('market:clock', $clock, 86400);
            $diagnostics->record(['kind'=>'clock','wall_ms'=>now()->getTimestampMs(),'clock'=>$clock]);
            foreach ($provider->tickers(config('market-data.symbols')) as $ticker) {
                $ingest->ingest($ticker);
            }
            $symbols = DB::table('trading_pairs')->where('enabled', true)->where('data_provider', $provider->identity())->where('market_type', 'spot')->whereIn('exchange_symbol', config('market-data.symbols'))->pluck('exchange_symbol')->all();
            while (! $stop && (! $limit || microtime(true) - $started < $limit)) {
                try {
                    $health->transition($connections ? 'RECOVERING' : 'CONNECTING');
                    $connection->connect($provider->streamUrl($symbols));
                    $connections++;
                    if ($connections > 1) {
                        ReconcileMarketHistory::dispatch($symbols, now()->getTimestampMs());
                    }$connected = microtime(true);
                    $lastValid = microtime(true);
                    $health->beat();
                    while (! $stop && (! $limit || microtime(true) - $started < $limit)) {
                        if (microtime(true) - $lastLease >= 10) {
                            if (! $lease->renew($owner)) {
                                throw new \RuntimeException('Worker lease lost');
                            }$lastLease = microtime(true);
                        }
                        if (microtime(true) - $connected >= 23 * 3600 + 50 * 60) {
                            throw new \RuntimeException('Scheduled connection rotation');
                        }
                        try {
                            $raw = $connection->receive();
                        } catch (ConnectionTimeoutException) {
                            $raw = null;
                        }
                        if ($raw !== null) {
                            try {
                                $frameNs=hrtime(true);$frameMs=now()->getTimestampMs();
                                $parsed=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$parsedNs=hrtime(true);$parsedMs=now()->getTimestampMs();
                                $event=$provider->normalize($parsed,$frameMs);$normalizedNs=hrtime(true);$normalizedMs=now()->getTimestampMs();
                                if ($event['kind'] === 'shutdown') {
                                    throw new \RuntimeException('Provider scheduled shutdown');
                                }$writeStart=hrtime(true);$timing=['frame_ms'=>$frameMs,'parsed_ms'=>$parsedMs,'normalized_ms'=>$normalizedMs,'trace_id'=>$event['symbol'].':'.$event['kind'].':'.$event['event_ms']];if($this->option('async')){app(\App\MarketData\ObservationSpool::class)->append($event,$timing);$ingest->counts['received']++;$ingest->counts['by_symbol'][$event['symbol']]=($ingest->counts['by_symbol'][$event['symbol']]??0)+1;$age=$event['received_ms']-$event['event_ms'];$ingest->counts['latency_total_ms']+=$age;$ingest->counts['latency_max_ms']=max($ingest->counts['latency_max_ms'],$age);$ingest->counts['exchange_age_total_ms']=($ingest->counts['exchange_age_total_ms']??0)+$age;$ingest->counts['exchange_age_max_ms']=max($ingest->counts['exchange_age_max_ms']??0,$age);}else{$ingest->ingest($event,$timing);}$writeEnd=hrtime(true);
                                $diagnostics->record(['kind'=>'event','symbol'=>$event['symbol'],'event_type'=>$event['kind'],'exchange_ms'=>$event['event_ms'],'frame_ms'=>$frameMs,'parse_ms'=>($parsedNs-$frameNs)/1e6,'normalize_ms'=>($normalizedNs-$parsedNs)/1e6,'write_ms'=>($writeEnd-$writeStart)/1e6,'internal_ms'=>($writeEnd-$frameNs)/1e6,'memory_bytes'=>memory_get_usage(true)]);
                                $lastValid = microtime(true);
                                if (microtime(true) - $connected > 60) {
                                    $attempt = 0;
                                }
                            } catch (\InvalidArgumentException|\JsonException $e) {
                                $ingest->invalid($e->getMessage());
                            }
                        }
                        if(!$this->option('async') || microtime(true)-$lastBroadcast>=1){
                        $health->beat();
                        $healthStart=hrtime(true);
                        $states = array_map(fn ($s) => $health->symbol($s), $symbols);
                        $allFresh = true;
                        foreach ($states as $state) {
                            if ($state['age_ms'] === null || $state['age_ms'] > config('market-data.stale_seconds') * 1000) {
                                $allFresh = false;
                            }
                        }
                        $state = microtime(true) - $lastValid > 15 ? 'STALE' : ($allFresh ? 'LIVE' : 'DEGRADED');
                        if ($state !== $lastHealth) {
                            $health->transition($state);
                            $lastHealth = $state;
                        }
                        $diagnostics->record(['kind'=>'loop','wall_ms'=>now()->getTimestampMs(),'health_ms'=>(hrtime(true)-$healthStart)/1e6]);
                        }
                        if (microtime(true) - $lastValid > 45) {
                            throw new \RuntimeException('No valid provider messages');
                        }
                        if (microtime(true) - $lastBroadcast >= 1) {
                            $lastBroadcast = microtime(true);
                            try {
                                DistributeMarketDisplay::dispatch($symbols);
                            } catch (\Throwable $e) {
                                Cache::put('market:distribution', ['state' => 'DISCONNECTED', 'reason' => 'Queue unavailable'], 60);
                            }
                        }
                    }
                    $connectionSeconds += microtime(true) - $connected;
                } catch (\Throwable $e) {
                    if (isset($connected)) {
                        $connectionSeconds += microtime(true) - $connected;
                    }$error = $e->getMessage();
                    $health->transition('DISCONNECTED', $error);
                    $attempt++;
                    $delay = self::retryDelay($attempt);
                    $this->warn('Stream disconnected; retry in '.$delay.'s: '.$error);
                    $until = microtime(true) + $delay;
                    while (! $stop && microtime(true) < $until && (! $limit || microtime(true) - $started < $limit)) {
                        if (! $lease->renew($owner)) {
                            $stop = true;
                            break;
                        }usleep(250000);
                    }
                } finally {
                    $connection->close();
                }
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $this->error($error);
        } finally {
            $diagnostics->close();
            $connection->close();
            $health->transition('DISCONNECTED', $error ?? 'Worker stopped');
            Cache::forget('market:heartbeat');
            $lease->release($owner);
        }
        $report = ['persistence_mode'=>$this->option('async')?'durable_spool':'synchronous','duration_seconds' => round(microtime(true) - $started, 3), 'connected_seconds' => round($connectionSeconds, 3), 'connections' => $connections, 'counts' => $ingest->counts, 'clock' => $clock, 'budget' => Cache::get('market:budget'), 'observation_rows_added' => DB::table('market_price_observations')->count() - $startRows, 'peak_memory_bytes' => memory_get_peak_usage(true), 'error' => $error];
        $this->line(json_encode($report, JSON_PRETTY_PRINT));
        if ($path = $this->option('report')) {
            if (! str_starts_with(realpath(dirname($path)) ?: '', base_path())) {
                throw new \RuntimeException('Report must be inside workspace');
            }file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT));
        }

        return $connections ? 0 : 1;
    }
}
