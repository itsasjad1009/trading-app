<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Ledger;
use App\Services\LedgerAudit;
use App\Services\Operations;
use App\Services\WalletRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_read_does_not_create_accounts(): void
    {
        $u = User::factory()->create();
        $this->assertSame(['available' => '0.00', 'reserved' => '0.00'], app(Ledger::class)->wallet($u->id));
        $this->assertDatabaseCount('wallets', 0);
    }

    public function test_funding_key_cannot_be_reused_with_changed_amount(): void
    {
        $u = User::factory()->create();
        $key = (string) Str::uuid();
        app(WalletRequests::class)->funding($u->id, ['amount' => '100', 'request_key' => $key]);
        $this->expectException(ValidationException::class);
        app(WalletRequests::class)->funding($u->id, ['amount' => '200', 'request_key' => $key]);
    }

    public function test_withdrawal_key_cannot_be_reused_with_changed_destination(): void
    {
        $u = User::factory()->create();
        app(Ledger::class)->fund($u->id);
        $input = ['amount' => '100', 'request_key' => (string) Str::uuid(), 'method' => 'crypto', 'destination' => 'Demo destination', 'password' => 'password'];
        app(WalletRequests::class)->withdrawal($u->id, $input);
        $this->assertSame(0, array_sum(app(LedgerAudit::class)->inspect()));
        $this->expectException(ValidationException::class);
        app(WalletRequests::class)->withdrawal($u->id, [...$input, 'destination' => 'Changed destination']);
    }

    public function test_approved_withdrawal_remains_visible_in_transaction_history(): void
    {
        $u = User::factory()->create();
        app(Ledger::class)->fund($u->id);
        $id = app(WalletRequests::class)->withdrawal($u->id, ['amount' => '100', 'request_key' => (string) Str::uuid(), 'method' => 'crypto', 'destination' => 'Demo destination', 'password' => 'password']);
        app(Operations::class)->review(User::factory()->create(['role' => 'operations_admin']), 'withdrawal', $id, 'approved', 'Demo review');
        $this->actingAs($u)->get('/transactions?type=demo_withdrawal')->assertInertia(fn ($p) => $p->has('transactions.data', 1)->where('transactions.data.0.amount_minor', 0)->where('transactions.data.0.reserved_minor', -10000));
    }

    public function test_support_history_is_bounded_and_older_messages_are_reachable(): void
    {
        $u = User::factory()->create();
        $v = User::factory()->create(['role' => 'support_agent']);
        $id = DB::table('support_conversations')->insertGetId(['user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
        for ($i = 0; $i < 75; $i++) {
            DB::table('support_messages')->insert(['support_conversation_id' => $id, 'user_id' => $v->id, 'body' => 'Sample '.$i, 'created_at' => now(), 'updated_at' => now()]);
        }
        $before = DB::table('support_messages')->where('support_conversation_id',$id)->where('body','Sample 25')->value('id');
        $this->actingAs($u)->get('/support')->assertInertia(fn ($p) => $p->has('messages', 50)->where('messages.0.body', 'Sample 25')->where('older_messages_url', route('support', ['messages_before' => $before])));
        $this->get('/support?messages_before='.$before)->assertInertia(fn ($p) => $p->has('messages', 25)->where('older_messages_url', null));
    }

    public function test_support_attachment_is_private_and_owner_can_download(): void
    {
        Storage::fake('local');
        $u = User::factory()->create();
        $v = User::factory()->create();
        $id = DB::table('support_conversations')->insertGetId(['user_id' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
        Storage::disk('local')->put('support/sample.pdf', 'sample');
        $message = DB::table('support_messages')->insertGetId(['support_conversation_id' => $id, 'user_id' => $u->id, 'body' => 'Sample', 'attachment' => 'support/sample.pdf', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($v)->get('/support/attachment/'.$message)->assertForbidden();
        $this->actingAs($u)->get('/support/attachment/'.$message)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_staff_login_uses_the_fortify_entry_point(): void
    {
        $this->get('/admin/login')->assertRedirect('/login');
        $this->assertSame(url('/admin'), session('url.intended'));
    }

    public function test_sensitive_validation_input_is_not_flashed(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post('/kyc', ['full_name' => 'Sample', 'national_id' => 'PRIVATE-SAMPLE', 'tax_id' => 'PRIVATE-TAX', 'code' => '123456'])->assertSessionHasErrors();
        $this->assertNull(session('_old_input.national_id'));
        $this->assertNull(session('_old_input.tax_id'));
    }

    public function test_mfa_recovery_code_authenticates_once_and_is_replaced(): void
    {
        $u = User::factory()->create();
        $u->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => encrypt(json_encode(['sample-recovery-code']))])->save();
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['recovery_code' => 'sample-recovery-code'])->assertRedirect();
        $this->assertAuthenticatedAs($u);
        $this->assertNotContains('sample-recovery-code', $u->fresh()->recoveryCodes());
    }

    public function test_reconciliation_detects_damaged_transaction(): void
    {
        $u = User::factory()->create();
        app(Ledger::class)->fund($u->id);
        $this->assertSame(0, array_sum(app(LedgerAudit::class)->inspect()));
        DB::table('ledger_entries')->where('id', DB::table('ledger_entries')->min('id'))->increment('amount_minor', 1);
        $this->assertSame(1, app(LedgerAudit::class)->inspect()['unbalanced']);
    }

    public function test_ledger_requires_transaction_boundary(): void
    {
        // RefreshDatabase holds a surrounding test transaction; close it before exercising this guard.
        DB::rollBack();
        $this->expectException(\LogicException::class);
        app(Ledger::class)->post(1,'bad','bad','Bad',[1 => 100, 2 => -100]);
    }
}
