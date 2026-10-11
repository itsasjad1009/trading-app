<?php

namespace App\MarketData;

final class ObservationSpool
{
    private string $directory;
    private ?string $active = null;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? storage_path('app/private/market-spool');
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0700, true);
        }
    }

    public function append(array $event, array $timing): void
    {
        $files = $this->files();
        $totalBytes = 0;
        foreach ($files as $path) {
            if (is_file($path)) {
                $size = @filesize($path);
                if ($size !== false && $size !== null) {
                    $totalBytes += $size;
                }
            }
        }
        if ($totalBytes > 64 * 1024 * 1024) {
            throw new \RuntimeException('Authoritative observation spool capacity exceeded; feed degraded');
        }

        $activeValid = false;
        if ($this->active !== null && is_file($this->active)) {
            clearstatcache(true, $this->active);
            $activeSize = @filesize($this->active);
            if ($activeSize !== false && $activeSize < 1024 * 1024) {
                $activeValid = true;
            }
        }

        if (! $activeValid) {
            $this->active = $this->directory . '/' . sprintf('%020d', hrtime(true)) . '-' . bin2hex(random_bytes(4)) . '.jsonl';
            @touch($this->active);
        }

        $file = @fopen($this->active, 'ab');
        if (! $file) {
            $this->active = $this->directory . '/' . sprintf('%020d', hrtime(true)) . '-' . bin2hex(random_bytes(4)) . '.jsonl';
            $file = fopen($this->active, 'ab');
        }

        try {
            flock($file, LOCK_EX);
            $line = json_encode(['event' => $event, 'timing' => $timing], JSON_THROW_ON_ERROR) . "\n";
            if (fwrite($file, $line) !== strlen($line) || ! fflush($file)) {
                throw new \RuntimeException('Observation spool write failed');
            }
            if (function_exists('fsync') && ! fsync($file)) {
                throw new \RuntimeException('Observation spool sync failed');
            }
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
            if ($this->active !== null) {
                clearstatcache(true, $this->active);
            }
        }
    }

    public function files(): array
    {
        $files = glob($this->directory . '/*.jsonl');
        if ($files === false || ! is_array($files)) {
            return [];
        }
        sort($files);

        return array_values(array_filter($files, 'is_file'));
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function getActive(): ?string
    {
        return $this->active;
    }
}
