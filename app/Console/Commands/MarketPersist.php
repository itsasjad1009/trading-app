<?php

namespace App\Console\Commands;

use App\MarketData\Ingestor;
use App\MarketData\ObservationSpool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class MarketPersist extends Command
{
    protected $signature = 'market:persist {--seconds=0} {--diagnostic=}';

    protected $description = 'Consume durable bounded market observations independently of the WebSocket reader';

    public function handle(ObservationSpool $spool, Ingestor $ingest): int
    {
        if (! app(\App\MarketData\AccessGate::class)->workerAllowed()) {
            return 1;
        }
        $lock = new \App\MarketData\WorkerLease('market:persist:leader');
        $owner = bin2hex(random_bytes(16));
        if (! $lock->renew($owner)) {
            $this->error('Persistence worker already running');

            return 1;
        }
        $diagnostics = new \App\MarketData\PipelineDiagnostics;
        $diagnostics->open($this->option('diagnostic'));
        $start = microtime(true);
        $renew = $start;
        $stop = false;

        if (function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler(function () use (&$stop) {
                $stop = true;

                return true;
            });
        }

        try {
            while (! $stop && (! $this->option('seconds') || microtime(true) - $start < (int) $this->option('seconds'))) {
                if (microtime(true) - $renew > 10) {
                    if (! $lock->renew($owner)) {
                        throw new \RuntimeException('Persistence lease lost');
                    }
                    $renew = microtime(true);
                }

                $worked = false;
                $files = $spool->files();
                $lastFile = ! empty($files) ? $files[array_key_last($files)] : null;

                foreach ($files as $path) {
                    if (! is_file($path)) {
                        continue;
                    }

                    $key = 'market:spool:offset:'.basename($path);
                    $offset = (int) Cache::get($key, 0);

                    clearstatcache(true, $path);
                    $fileSize = @filesize($path);
                    if ($fileSize === false) {
                        continue;
                    }

                    $file = @fopen($path, 'rb');
                    if (! $file) {
                        continue;
                    }

                    if ($offset > 0) {
                        fseek($file, $offset);
                    }

                    try {
                        while (($line = fgets($file)) !== false) {
                            if (! str_ends_with($line, "\n")) {
                                break;
                            }
                            if (microtime(true) - $renew > 10) {
                                if (! $lock->renew($owner)) {
                                    throw new \RuntimeException('Persistence lease lost');
                                }
                                $renew = microtime(true);
                            }

                            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                            $begin = hrtime(true);
                            $ingest->ingest($row['event'], $row['timing']);
                            $done = hrtime(true);
                            $storedMs = now()->getTimestampMs();

                            $diagnostics->record([
                                'kind' => 'persistence',
                                'symbol' => $row['event']['symbol'],
                                'event_type' => $row['event']['kind'],
                                'frame_ms' => $row['event']['received_ms'],
                                'stored_ms' => $storedMs,
                                'frame_to_stored_ms' => $storedMs - $row['event']['received_ms'],
                                'write_ms' => ($done - $begin) / 1e6,
                                'trace_id' => $row['timing']['trace_id'] ?? null,
                            ]);

                            $currentOffset = ftell($file);
                            Cache::forever($key, $currentOffset);
                            Cache::put('market:persistence', [
                                'state' => 'CONNECTED',
                                'last_stored_ms' => now()->getTimestampMs(),
                                'frame_to_stored_ms' => now()->getTimestampMs() - $row['event']['received_ms'],
                                'write_ms' => ($done - $begin) / 1e6,
                            ], 60);

                            $worked = true;
                        }
                    } finally {
                        fclose($file);
                    }

                    // Only delete fully processed files that are NOT the current latest writing target
                    if ($path !== $lastFile && is_file($path)) {
                        clearstatcache(true, $path);
                        $finalSize = @filesize($path);
                        $processedOffset = (int) Cache::get($key, 0);

                        if ($finalSize !== false && $processedOffset >= $finalSize) {
                            @unlink($path);
                            Cache::forget($key);
                        }
                    }
                }

                if (! $worked) {
                    usleep(20000);
                }
            }
        } catch (\Throwable $e) {
            Cache::put('market:persistence', [
                'state' => 'DISCONNECTED',
                'error' => get_class($e),
                'message' => $e->getMessage(),
            ], 60);
            $this->error('Persistence stopped: '.get_class($e).' - '.$e->getMessage());

            return 1;
        } finally {
            $diagnostics->close();
            $lock->release($owner);
        }

        return 0;
    }
}
