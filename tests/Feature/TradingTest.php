<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DemoMarket;
use App\Services\Ledger;
use App\Services\Operations;
use App\Services\TradingEngine;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TradingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarketSeeder::class);
        config(['broadcasting.default' => 'null']);
    }

    private function customer(): User
    {
        $u = User::factory()->create();
        app(Ledger::class)->fund($u->id);

        return $u;
    }

    private function order(array $extra = []): array
    {
        return [...['amount' => '100', 'pair_id' => DB::table('trading_pairs')->where('symbol','BTC/USDT')->value('id'), 'direction' => 'buy', 'duration' => 60, 'request_key' => (string) Str::uuid()], ...$extra];
    }

    public function test_stake_is_reserved_atomically_and_retry_does_not_debit_twice(): void
    {
        $u = $this->customer();
        $input = $this->order();
        $e = app(TradingEngine::class);
        $id = $e->place($u->id, $input);
        $this->assertSame($id, $e->place($u->id, $input));
        $this->assertSame(['available' => '9900.00', 'reserved' => '100.00'], app(Ledger::class)->wallet($u->id));
        $this->assertDatabaseCount('trades', 1);
    }

    public function test_invalid_orders_are_rejected(): void
    {
        $u = $this->customer();
        foreach ([['amount' => '99'], ['amount' => '10000.01'], ['amount' => 'not money'], ['pair_id' => 999], ['duration' => 61]] as $bad) {
            $this->actingAs($u)->post('/trade', $this->order($bad))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('trades', 0);
    }

    public function test_reusing_key_with_different_payload_is_rejected(): void
    {
        $u = $this->customer();
        $input = $this->order();
        app(TradingEngine::class)->place($u->id, $input);
        $this->actingAs($u)->post('/trade', [...$input, 'amount' => '500'])->assertSessionHasErrors('request_key');
    }

    public function test_settlement_is_idempotent_and_ledger_balances(): void
    {
        $this->freezeTime();
        $u = $this->customer();
        $e = app(TradingEngine::class);
        $id = $e->place($u->id, $this->order());
        $this->travel(61)->seconds();
        $e->settle($id);
        $e->settle($id);
        $this->assertDatabaseCount('trade_settlements', 1);
        $this->assertSame('0.00', app(Ledger::class)->wallet($u->id)['reserved']);
        foreach (DB::table('ledger_entries')->selectRaw('ledger_transaction_id, SUM(amount_minor) AS total')->groupBy('ledger_transaction_id')->get() as $row) {
            $this->assertEquals(0, $row->total);
        }
    }

    public function test_early_settlement_does_not_close_position(): void
    {
        $u = $this->customer();
        $e = app(TradingEngine::class);
        $id = $e->place($u->id, $this->order());
        $e->settle($id);
        $this->assertDatabaseHas('trades', ['id' => $id, 'status' => 'open']);
    }

    public function test_future_payout_changes_do_not_change_accepted_trade(): void
    {
        $this->freezeTime();
        $start = now()->timestamp;
        $adapter = \Mockery::mock(DemoMarket::class)->makePartial();
        $adapter->shouldReceive('price')->andReturnUsing(fn ($pair, $at) => $at === $start ? 100000000 : 101000000);
        $this->app->instance(DemoMarket::class, $adapter);
        $u = $this->customer();
        $engine = app(TradingEngine::class);
        $id = $engine->place($u->id, $this->order());
        DB::table('platform_settings')->updateOrInsert(['key' => 'payout_bps'], ['value' => json_encode([60 => 100, 90 => 100, 120 => 100, 180 => 100, 360 => 100])]);
        $this->travel(500)->seconds();
        $engine->settle($id);
        $this->assertDatabaseHas('trade_settlements', ['trade_id' => $id, 'profit_minor' => 7000]);
        $this->assertSame('10070.00', app(Ledger::class)->wallet($u->id)['available']);
    }

    public function test_trade_and_transaction_records_are_isolated(): void
    {
        $first = $this->customer();
        $second = $this->customer();
        app(TradingEngine::class)->place($first->id, $this->order());
        $this->actingAs($second)->get('/positions')->assertOk()->assertInertia(fn ($p) => $p->component('Platform')->where('stats.total', 0)->has('trades.data', 0));
    }

    public function test_protected_routes_require_authentication(): void
    {
        foreach (['dashboard', 'trade', 'positions', 'transactions', 'deposit', 'withdraw', 'kyc', 'support', 'profile', 'settings'] as $page) {
            $this->get('/'.$page)->assertRedirect('/login');
        }
    }

    public function test_service_rejects_unverified_trade_authorization(): void
    {
        $u = User::factory()->unverified()->create();
        app(Ledger::class)->fund($u->id);
        $this->expectException(HttpException::class);
        app(TradingEngine::class)->place($u->id, $this->order());
    }

    public function test_withdrawal_requires_password_and_reserves_then_refunds(): void
    {
        $u = $this->customer();
        $admin = User::factory()->create(['role' => 'operations_admin']);
        $input = ['amount' => '100', 'request_key' => (string) Str::uuid(), 'method' => 'crypto', 'destination' => 'DEMO-DESTINATION', 'password' => 'bad'];
        $this->actingAs($u)->post('/withdraw', $input)->assertSessionHasErrors('password');
        $this->actingAs($u)->post('/withdraw', [...$input, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame('100.00', app(Ledger::class)->wallet($u->id)['reserved']);
        $id = DB::table('withdrawal_requests')->value('id');
        app(Operations::class)->review($admin, 'withdrawal', $id, 'rejected', 'Test refund');
        $this->assertSame('10000.00', app(Ledger::class)->wallet($u->id)['available']);
    }

    public function test_customer_cannot_review_requests(): void
    {
        $u = $this->customer();
        $this->expectException(HttpException::class);
        app(Operations::class)->review($u, 'deposit', 1, 'approved', 'Unauthorized');
    }

    public function test_winning_losing_and_tied_positions_use_server_prices(): void
    {
        foreach (['won' => 101000000, 'lost' => 99000000, 'tie' => 100000000] as $outcome => $expiry) {
            $this->freezeTime();
            $start = now()->timestamp;
            $adapter = \Mockery::mock(DemoMarket::class)->makePartial();
            $adapter->shouldReceive('price')->andReturnUsing(fn ($pair, $at) => $at === $start ? 100000000 : $expiry);
            $this->app->instance(DemoMarket::class, $adapter);
            $u = $this->customer();
            $engine = $this->app->make(TradingEngine::class);
            $id = $engine->place($u->id, $this->order());
            $this->travel(61)->seconds();
            $engine->settle($id);
            $this->assertDatabaseHas('trades', ['id' => $id, 'status' => $outcome]);
            $expected = ['won' => '10070.00', 'lost' => '9900.00', 'tie' => '10000.00'][$outcome];
            $this->assertSame($expected, app(Ledger::class)->wallet($u->id)['available']);
            $this->travelBack();
        }
    }

    public function test_stale_quote_is_rejected(): void
    {
        $u = $this->customer();
        $adapter = \Mockery::mock(DemoMarket::class)->makePartial();
        $adapter->shouldReceive('quote')->andReturn(['observed_at' => now()->subMinute()->toIso8601String()]);
        $this->app->instance(DemoMarket::class, $adapter);
        $this->actingAs($u)->post('/trade', $this->order())->assertSessionHasErrors('pair_id');
        $this->assertDatabaseCount('trades', 0);
    }

    public function test_open_position_limit(): void
    {
        $u = $this->customer();
        config(['trading.maximum_open_trades' => 1]);
        app(TradingEngine::class)->place($u->id, $this->order());
        $this->actingAs($u)->post('/trade', $this->order())->assertSessionHasErrors('amount');
    }

    public function test_suspended_user_cannot_place_trade_or_message(): void
    {
        $u = $this->customer();
        $u->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($u)->post('/trade', $this->order())->assertForbidden();
        $this->post('/support', ['body' => 'message'])->assertForbidden();
    }

    public function test_duplicate_deposit_is_one_request_and_review_credits_once(): void
    {
        $u = $this->customer();
        $input = ['amount' => '100', 'request_key' => (string) Str::uuid()];
        $this->actingAs($u)->post('/deposit', $input);
        $this->post('/deposit', $input);
        $this->assertDatabaseCount('deposit_requests', 1);
        $staff = User::factory()->create(['role' => 'operations_admin']);
        $id = DB::table('deposit_requests')->value('id');
        app(Operations::class)->review($staff, 'deposit', $id, 'approved', 'Approved demo');
        $this->assertSame('10100.00', app(Ledger::class)->wallet($u->id)['available']);
        try {
            app(Operations::class)->review($staff, 'deposit', $id, 'approved', 'Duplicate demo');
            $this->fail('Duplicate review accepted');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }
}
