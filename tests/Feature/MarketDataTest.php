<?php

namespace Tests\Feature;

use App\Console\Commands\MarketStream;
use App\Events\MarketUpdated;
use App\MarketData\AccessGate;
use App\MarketData\BinanceSpotProvider;
use App\MarketData\FeedHealth;
use App\MarketData\History;
use App\MarketData\Ingestor;
use App\MarketData\ObservationSelector;
use App\MarketData\RequestBudget;
use App\MarketData\WorkerLease;
use App\Models\User;
use App\Services\Ledger;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class MarketDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarketSeeder::class);
        config(['market-data.enabled' => true]);
        Http::preventStrayRequests();
    }

    private function trade(array $extra = []): array
    {
        return array_replace(['e' => 'trade', 'E' => now()->getTimestampMs(), 's' => 'BTCUSDT', 't' => 123, 'p' => '82985.123456789123456789', 'q' => '0.000000010000000001', 'T' => now()->getTimestampMs()], $extra);
    }

    private function event(array $extra = []): array
    {
        return app(BinanceSpotProvider::class)->normalize(['data' => $this->trade($extra)], now()->getTimestampMs());
    }

    private function metadata(): array
    {
        return ['rateLimits' => [['rateLimitType' => 'REQUEST_WEIGHT', 'interval' => 'MINUTE', 'intervalNum' => 1, 'limit' => 6000]], 'symbols' => [['symbol' => 'BTCUSDT', 'status' => 'TRADING', 'isSpotTradingAllowed' => true]]];
    }

    private function kline(int $at): array
    {
        return [$at, '10.123456789123456789', '12.123456789123456789', '9.123456789123456789', '11.123456789123456789', '4.123456789123456789', $at + 59999, '41.123456789123456789', 12];
    }

    public function test_trade_precision_times_and_provenance(): void
    {
        $this->freezeTime();
        $e = $this->event();
        $this->assertSame('82985.123456789123456789', $e['price']);
        $this->assertSame('0.000000010000000001', $e['quantity']);
        $this->assertSame('spot', $e['market_type']);
        $this->assertSame('binance_spot', $e['provider']);
        $this->assertSame(now()->getTimestampMs(), $e['event_ms']);
        app(Ingestor::class)->ingest($e);
        $this->assertDatabaseHas('market_price_observations', ['trade_id' => 123, 'source' => 'websocket']);
    }

    public function test_invalid_payloads_rejected(): void
    {
        foreach ([['p' => 1.2], ['p' => 'NaN'], ['p' => '-1'], ['q' => '0'], ['s' => 'BTCUSD'], ['E' => 12], ['E' => now()->addMinutes(2)->getTimestampMs()], ['t' => '123'], ['e' => 'unknown']] as $extra) {
            try {
                $this->event($extra);
                $this->fail('Invalid payload accepted');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_duplicates_and_order_do_not_rewind_cache(): void
    {
        $i = app(Ingestor::class);
        $first = $this->event();
        $i->ingest($first);
        $i->ingest($first);
        $i->ingest($this->event(['t' => 122, 'T' => now()->getTimestampMs() - 5, 'p' => '1.1']));
        $this->assertDatabaseCount('market_price_observations', 2);
        $this->assertSame($first['price'], Cache::get('market:price:BTCUSDT')['price']);
        $this->assertSame(1, $i->counts['duplicates']);
        $this->assertSame(1, $i->counts['out_of_order']);
    }

    public function test_candle_validation_and_late_updates(): void
    {
        $p = app(BinanceSpotProvider::class);
        $at = intdiv(now()->getTimestampMs(), 60000) * 60000 - 60000;
        $c = $p->candle('BTCUSDT', 60, $this->kline($at));
        $i = app(Ingestor::class);
        $i->storeCandle($c);
        $late = $c;
        $late['updated_ms']--;
        $late['close'] = '10.1';
        $i->storeCandle($late);
        $this->assertDatabaseCount('market_candles', 1);
        $this->assertEquals($c['close'], DB::table('market_candles')->value('close'));
        $r = $this->kline($at);
        $r[2] = '1.1';
        $this->expectException(InvalidArgumentException::class);
        $p->candle('BTCUSDT', 60, $r);
    }

    public function test_ws_candle_and_ticker_normalize(): void
    {
        $at = intdiv(now()->getTimestampMs(), 60000) * 60000;
        $p = app(BinanceSpotProvider::class);
        $c = $p->normalize(['e' => 'kline', 'E' => now()->getTimestampMs(), 's' => 'BTCUSDT', 'k' => ['s' => 'BTCUSDT', 'i' => '1m', 'x' => false, 't' => $at, 'T' => $at + 59999, 'o' => '10', 'h' => '12', 'l' => '9', 'c' => '11', 'v' => '1', 'q' => '11', 'n' => 1]], now()->getTimestampMs());
        $this->assertSame('candle', $c['kind']);
        $this->assertFalse($c['candle']['closed']);
        $t = $p->normalize(['e' => '24hrTicker', 'E' => now()->getTimestampMs(), 's' => 'BTCUSDT', 'c' => '11', 'P' => '-1.23', 'v' => '1', 'q' => '11'], now()->getTimestampMs());
        $this->assertSame('-1.23', $t['change']);
    }

    public function test_rest_history_cached_deduplicated_and_gaps_reported(): void
    {
        $at = intdiv(now()->getTimestampMs(), 60000) * 60000 - 180000;
        Http::fake(['*exchangeInfo*' => Http::response($this->metadata()), '*klines*' => Http::response([$this->kline($at + 120000), $this->kline($at), $this->kline($at)])]);
        $h = app(History::class);
        $r = $h->backfill('BTCUSDT', 60, $at, $at + 120000);
        $this->assertCount(2, $r['candles']);
        $this->assertSame(1, $r['missing_count']);
        $this->assertDatabaseHas('market_data_incidents', ['type' => 'history_gap']);
        $this->assertDatabaseCount('market_candles', 2);
        $this->assertSame(intdiv($at, 1000), $r['candles'][0]['time']);
    }

    public function test_complete_closed_history_uses_database_without_http(): void
    {
        $at = intdiv(now()->getTimestampMs(), 60000) * 60000 - 120000;
        $p = app(BinanceSpotProvider::class);
        foreach ([$at, $at + 60000] as $t) {
            app(Ingestor::class)->storeCandle($p->candle('BTCUSDT', 60, $this->kline($t)));
        }$r = app(History::class)->backfill('BTCUSDT', 60, $at, $at + 60000);
        $this->assertCount(2, $r['candles']);
        Http::assertNothingSent();
    }

    public function test_invalid_exchange_symbol_and_closed_market_rejected(): void
    {
        Http::fake(['*exchangeInfo*' => Http::response(['symbols' => [['symbol' => 'BTCUSDT', 'status' => 'HALT', 'isSpotTradingAllowed' => true]]])]);
        $this->expectException(InvalidArgumentException::class);
        app(BinanceSpotProvider::class)->validateSymbol('BTCUSDT');
    }

    public function test_rate_limit_retry_after_and_ban_block_requests(): void
    {
        foreach ([429, 418] as $status) {
            Cache::flush();
            Http::fake(['*' => Http::response(['code' => -1003], $status, ['Retry-After' => '180'])]);
            $b = app(RequestBudget::class);
            try {
                $b->request(2, fn () => Http::get('https://data-api.binance.vision/api/v3/time'));
            } catch (RequestException) {
            }$this->assertGreaterThanOrEqual(now()->timestamp + 180, Cache::get('market:budget')['blocked_until']);
            try {
                $b->request(2, fn () => throw new \RuntimeException('Should not reach HTTP'));
                $this->fail();
            } catch (\RuntimeException $e) {
                $this->assertSame('Provider backoff active', $e->getMessage());
            }
        }
    }

    public function test_budget_uses_metadata_and_conservative_margin(): void
    {
        $b = app(RequestBudget::class);
        $b->metadata(['rateLimits' => [['rateLimitType' => 'REQUEST_WEIGHT', 'interval' => 'MINUTE', 'intervalNum' => 1, 'limit' => 10]]]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Conservative request budget exhausted');
        $b->request(9, fn () => Http::get('https://data-api.binance.vision/api/v3/time'));
    }

    public function test_stale_feed_cache_expiry_and_crashed_worker(): void
    {
        $i = app(Ingestor::class);
        $i->ingest($this->event());
        $i->ingest(app(BinanceSpotProvider::class)->normalize(['e' => '24hrTicker', 'E' => now()->getTimestampMs(), 's' => 'BTCUSDT', 'c' => '11', 'P' => '1', 'v' => '1', 'q' => '11'], now()->getTimestampMs()));
        $h = app(FeedHealth::class);
        $h->beat();
        $h->transition('LIVE');
        $this->assertSame('LIVE', $h->symbol('BTCUSDT')['state']);
        $this->travel(16)->seconds();
        $this->assertSame('DISCONNECTED', $h->symbol('BTCUSDT')['state']);
        $h->beat();
        $this->assertSame('STALE', $h->symbol('BTCUSDT')['state']);
        $this->travel(301)->seconds();
        $this->assertNull(Cache::get('market:price:BTCUSDT'));
    }

    public function test_singleton_worker_crash_restart(): void
    {
        $l = app(WorkerLease::class);
        $this->assertTrue($l->renew('first'));
        $this->assertFalse($l->renew('second'));
        $l->release('second');
        $this->assertFalse($l->renew('second'));
        $this->travel(46)->seconds();
        $this->assertTrue($l->renew('second'));
        $this->assertFalse($l->renew('first'));
        $l->release('second');
        $this->assertTrue($l->renew('first'));
    }

    public function test_live_display_local_gate_and_staff_endpoint(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->getJson('http://127.0.0.1/market/live/snapshot')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.10'])->getJson('http://127.0.0.1/market/live/snapshot')->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->getJson('/market/health')->assertForbidden();
        $u->forceFill(['role' => 'auditor'])->save();
        $this->actingAs($u)->getJson('/market/health')->assertOk();
        config(['market-data.enabled' => false]);
        $this->getJson('http://127.0.0.1/market/live/snapshot')->assertForbidden();
    }

    public function test_market_channel_is_private_until_both_permission_flags_set(): void
    {
        $e = new MarketUpdated('ticker', []);
        $this->assertSame('private-market.local.ticker', (string) $e->broadcastOn()[0]);
        config(['market-data.public_redistribution' => true]);
        $this->assertFalse(app(AccessGate::class)->publicAllowed());
        config(['market-data.rights_verified' => true]);
        $this->assertSame('market.ticker', (string) $e->broadcastOn()[0]);
    }

    public function test_observation_expiry_selection_is_deterministic_and_never_future_or_cache(): void
    {
        $at = now()->getTimestampMs();
        $i = app(Ingestor::class);
        foreach ([[1, $at - 10, '10'], [2, $at - 10, '11'], [3, $at + 1, '99']] as [$id,$t,$p]) {
            $i->ingest($this->event(['t' => $id, 'T' => $t, 'p' => $p]));
        }$selector = app(ObservationSelector::class);
        $row = $selector->atExpiry('binance_spot', 'BTCUSDT', $at);
        $this->assertEquals(2, $row->trade_id);
        $this->assertNull($selector->atExpiry('binance_spot', 'BTCUSDT', $at + 2000));
        $this->assertNull($selector->atExpiry('other', 'BTCUSDT', $at));
    }

    public function test_optional_live_settlement_is_fail_closed(): void
    {
        config(['market-data.settlement_source' => 'live_observation']);
        $u = User::factory()->create();
        app(Ledger::class)->fund($u->id);
        $this->actingAs($u)->post('/trade', ['amount' => '100', 'pair_id' => 1, 'direction' => 'buy', 'duration' => 60, 'request_key' => (string) Str::uuid()])->assertStatus(503);
        $this->assertDatabaseCount('trades', 0);
    }

    public function test_stream_subscriptions_are_combined_six_pairs_and_retry_is_bounded(): void
    {
        $url = app(BinanceSpotProvider::class)->streamUrl(config('market-data.symbols'));
        $this->assertStringStartsWith('wss://data-stream.binance.vision:443/stream?streams=', $url);
        $this->assertSame(18,substr_count($url,'@'));
        $this->assertStringContainsString('btcusdt@trade',$url);
        $delay = MarketStream::retryDelay(20);
        $this->assertGreaterThanOrEqual(60,$delay);
        $this->assertLessThanOrEqual(63,$delay);
    }

    public function test_market_private_channel_authorization_and_account_isolation(): void
    {
        config(['broadcasting.default'=>'reverb']);
        require base_path('routes/channels.php');
        $u=User::factory()->create();$other=User::factory()->create();
        $this->actingAs($u)->postJson('/broadcasting/auth',['socket_id'=>'1.2','channel_name'=>'private-market.local.ticker'])->assertOk();
        $this->postJson('/broadcasting/auth',['socket_id'=>'1.2','channel_name'=>'private-account.'.$other->id])->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR'=>'192.168.1.8'])->postJson('/broadcasting/auth',['socket_id'=>'1.2','channel_name'=>'private-market.local.ticker'])->assertForbidden();
    }
    public function test_rest_history_paginates_and_cache_prevents_repeat_fetch(): void
    {
        $at=intdiv(now()->getTimestampMs(),60000)*60000-1001*60000;
        $rows=[];for($n=0;$n<1000;$n++)$rows[]=$this->kline($at+$n*60000);
        Http::fake(['*exchangeInfo*'=>Http::response($this->metadata()),'*klines*'=>Http::sequence()->push($rows)->push([$this->kline($at+1000*60000)])]);
        $result=app(BinanceSpotProvider::class)->candles('BTCUSDT',60,$at,$at+1000*60000);
        $this->assertCount(1001,$result);$this->assertSame($at+1000*60000,$result[1000]['open_ms']);Http::assertSentCount(3);
    }
    public function test_worker_reconnect_resubscribe_and_reports_interruption(): void
    {
        \Illuminate\Support\Facades\Event::fake();config(['market-data.symbols'=>['BTCUSDT']]);
        Http::fake(['*exchangeInfo*'=>Http::response($this->metadata()),'*time*'=>Http::response(['serverTime'=>now()->getTimestampMs()]),'*ticker/24hr*'=>Http::response([['symbol'=>'BTCUSDT','closeTime'=>now()->getTimestampMs(),'lastPrice'=>'10','priceChangePercent'=>'1','volume'=>'1','quoteVolume'=>'10']])]);
        $stream=\Mockery::mock(\App\MarketData\StreamConnection::class);$stream->shouldReceive('connect')->atLeast()->twice()->withArgs(fn($url)=>str_contains($url,'btcusdt@trade')&&str_contains($url,'btcusdt@kline_1m')&&str_contains($url,'btcusdt@ticker'));
        $stream->shouldReceive('receive')->once()->andThrow(new \RuntimeException('Injected disconnect'));
        $id=500;$stream->shouldReceive('receive')->andReturnUsing(function()use(&$id){usleep(10000);return json_encode($this->trade(['t'=>$id++]));});$stream->shouldReceive('close');$this->app->instance(\App\MarketData\StreamConnection::class,$stream);
        \Illuminate\Support\Facades\Queue::fake();$this->artisan('market:stream',['--seconds'=>8])->assertSuccessful();$this->assertDatabaseHas('market_data_incidents',['type'=>'DISCONNECTED']);$this->assertNull(Cache::get('market:leader'));\Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ReconcileMarketHistory::class);
    }
}
