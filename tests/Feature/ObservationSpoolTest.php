<?php

namespace Tests\Feature;

use App\MarketData\BinanceSpotProvider;
use App\MarketData\Ingestor;
use App\MarketData\ObservationSpool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ObservationSpoolTest extends TestCase
{
    use RefreshDatabase;

    private string $testDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDirectory = storage_path('app/private/test-spool-'.uniqid());
        if (! is_dir($this->testDirectory)) {
            mkdir($this->testDirectory, 0700, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testDirectory)) {
            $files = glob($this->testDirectory.'/*');
            if (is_array($files)) {
                foreach ($files as $f) {
                    if (is_file($f)) {
                        @unlink($f);
                    }
                }
            }
            @rmdir($this->testDirectory);
        }
        parent::tearDown();
    }

    private function sampleEvent(int $tradeId = 100): array
    {
        return [
            'kind' => 'trade',
            'symbol' => 'BTCUSDT',
            'market_type' => 'spot',
            'provider' => 'binance_spot',
            'trade_id' => $tradeId,
            'price' => '80000.000000000000000000',
            'quantity' => '1.000000000000000000',
            'trade_ms' => now()->getTimestampMs(),
            'event_ms' => now()->getTimestampMs(),
            'received_ms' => now()->getTimestampMs(),
            'source' => 'websocket',
            'valid' => true,
        ];
    }

    public function test_observation_spool_appends_and_reads_safely(): void
    {
        $spool = new ObservationSpool($this->testDirectory);
        $event = $this->sampleEvent(101);
        $timing = ['frame_ms' => now()->getTimestampMs(), 'trace_id' => 'BTCUSDT:trade:101'];

        $spool->append($event, $timing);

        $files = $spool->files();
        $this->assertCount(1, $files);
        $this->assertTrue(is_file($files[0]));

        $content = file_get_contents($files[0]);
        $row = json_decode(trim($content), true);
        $this->assertSame(101, $row['event']['trade_id']);
        $this->assertSame('BTCUSDT', $row['event']['symbol']);
        $this->assertSame('BTCUSDT:trade:101', $row['timing']['trace_id']);
    }

    public function test_observation_spool_tolerates_concurrently_unlinked_file_without_warning(): void
    {
        $spool = new ObservationSpool($this->testDirectory);

        // Create an initial file
        $spool->append($this->sampleEvent(201), ['trace_id' => '201']);
        $files = $spool->files();
        $this->assertCount(1, $files);

        // Simulate another process deleting the file
        @unlink($files[0]);
        clearstatcache(true, $files[0]);

        // Appending must safely detect the missing active file and create a new one without throwing stat failed
        $spool->append($this->sampleEvent(202), ['trace_id' => '202']);

        $newFiles = $spool->files();
        $this->assertCount(1, $newFiles);
        $this->assertNotSame($files[0], $newFiles[0]);

        $content = file_get_contents($newFiles[0]);
        $row = json_decode(trim($content), true);
        $this->assertSame(202, $row['event']['trade_id']);
    }

    public function test_market_persist_processes_offset_and_does_not_delete_active_latest_file(): void
    {
        $spool = new ObservationSpool($this->testDirectory);
        $this->app->instance(ObservationSpool::class, $spool);
        Cache::flush();
        config(['market-data.enabled' => true]);

        // Add 2 events into spool
        $spool->append($this->sampleEvent(301), ['trace_id' => '301']);
        $spool->append($this->sampleEvent(302), ['trace_id' => '302']);

        $files = $spool->files();
        $this->assertCount(1, $files);
        $activeFile = $files[0];

        $this->artisan('market:persist', ['--seconds' => 1])->assertSuccessful();

        // The only file is also the latest active file: it must NOT be deleted even after full consumption
        $this->assertTrue(is_file($activeFile), 'Active spool file must not be deleted while it is the latest file');

        // Check offset was recorded in cache
        $key = 'market:spool:offset:'.basename($activeFile);
        $offset = (int) Cache::get($key, 0);
        $this->assertSame(filesize($activeFile), $offset);
    }

    public function test_market_persist_deletes_only_fully_consumed_historical_files(): void
    {
        $spool = new ObservationSpool($this->testDirectory);
        $this->app->instance(ObservationSpool::class, $spool);
        config(['market-data.enabled' => true]);

        // Create file 1 (historical)
        $file1 = $this->testDirectory.'/00000000000000000001-test.jsonl';
        $line1 = json_encode(['event' => $this->sampleEvent(401), 'timing' => ['trace_id' => '401']])."\n";
        file_put_contents($file1, $line1);

        // Create file 2 (latest active target)
        $file2 = $this->testDirectory.'/00000000000000000002-test.jsonl';
        $line2 = json_encode(['event' => $this->sampleEvent(402), 'timing' => ['trace_id' => '402']])."\n";
        file_put_contents($file2, $line2);

        $this->assertCount(2, $spool->files());

        // Run persistence
        $this->artisan('market:persist', ['--seconds' => 1])->assertSuccessful();

        // File 1 was fully read and is not latest -> should be unlinked
        $this->assertFileDoesNotExist($file1);

        // File 2 is the latest file -> must be retained
        $this->assertFileExists($file2);
    }

    public function test_clock_probe_measures_monotonic_rtt_and_reports_uncertainty(): void
    {
        $serverTimeMs = now()->getTimestampMs() - 500;
        Http::fake([
            '*time*' => Http::response(['serverTime' => $serverTimeMs]),
            '*exchangeInfo*' => Http::response([
                'rateLimits' => [['rateLimitType' => 'REQUEST_WEIGHT', 'interval' => 'MINUTE', 'intervalNum' => 1, 'limit' => 6000]],
                'symbols' => [['symbol' => 'BTCUSDT', 'status' => 'TRADING', 'isSpotTradingAllowed' => true]],
            ]),
        ]);

        config(['market-data.enabled' => true]);
        $provider = app(BinanceSpotProvider::class);
        $clock = $provider->serverTime();

        $this->assertArrayHasKey('server_time_ms', $clock);
        $this->assertArrayHasKey('local_sent_ms', $clock);
        $this->assertArrayHasKey('local_received_ms', $clock);
        $this->assertArrayHasKey('midpoint_ms', $clock);
        $this->assertArrayHasKey('offset_ms', $clock);
        $this->assertArrayHasKey('round_trip_ms', $clock);
        $this->assertArrayHasKey('monotonic_rtt_ms', $clock);
        $this->assertArrayHasKey('uncertainty_ms', $clock);

        $this->assertSame($serverTimeMs, $clock['server_time_ms']);
        $this->assertGreaterThanOrEqual(0, $clock['round_trip_ms']);
        $this->assertGreaterThanOrEqual(0, $clock['monotonic_rtt_ms']);
        $this->assertSame((int) ceil($clock['round_trip_ms'] / 2), $clock['uncertainty_ms']);
    }
}
