<?php

namespace Tests\Feature;

use App\Filament\Pages\OperationsCenter;
use App\Models\KycApplication;
use App\Models\SupportConversation;
use App\Models\User;
use App\Services\Operations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_policy_allows_owner_and_kyc_officer_only(): void
    {
        $customer = User::factory()->create();
        $application = KycApplication::factory()->create(['user_id' => $customer->id]);
        $this->assertTrue(Gate::forUser($customer)->allows('view', $application));
        foreach (['customer', 'support_agent', 'operations_admin', 'auditor'] as $role) {
            $this->assertFalse(Gate::forUser(User::factory()->create(['role' => $role]))->allows('view', $application));
        }
        $officer = User::factory()->create(['role' => 'kyc_officer']);
        $this->assertTrue(Gate::forUser($officer)->allows('view', $application));
        $officer->status = 'suspended';
        $this->assertFalse(Gate::forUser($officer)->allows('view', $application));
    }

    public function test_kyc_review_and_resubmission_are_audited(): void
    {
        $application = KycApplication::factory()->create();
        $officer = User::factory()->create(['role' => 'kyc_officer']);
        app(Operations::class)->review($officer, 'kyc', $application->id, 'under_review', 'Review started');
        $this->assertDatabaseHas('kyc_applications', ['id' => $application->id, 'status' => 'under_review']);
        app(Operations::class)->review($officer, 'kyc', $application->id, 'resubmission_required', 'Please provide a clearer demo image');
        $this->assertDatabaseHas('kyc_applications', ['id' => $application->id, 'status' => 'resubmission_required']);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_staff_support_reply_assignment_read_state_and_closure(): void
    {
        $customer = User::factory()->create();
        $agent = User::factory()->create(['role' => 'support_agent']);
        $this->actingAs($customer)->post('/support', ['body' => 'Demo question']);
        $conversation = SupportConversation::where('user_id', $customer->id)->firstOrFail();
        Livewire::actingAs($agent)->test(OperationsCenter::class)
            ->set('module', 'support')->set('reply', 'Demo response')->call('respond', $conversation->id)
            ->assertHasNoErrors()->call('setConversationStatus', $conversation->id, 'closed')->assertHasNoErrors();
        $this->assertDatabaseHas('support_conversations', ['id' => $conversation->id, 'agent_id' => $agent->id, 'status' => 'closed']);
        $this->assertNotNull(DB::table('support_messages')->where('user_id', $customer->id)->value('read_at'));
        $this->assertDatabaseHas('support_messages', ['user_id' => $agent->id, 'body' => 'Demo response']);
        $this->actingAs($customer)->post('/support', ['body' => 'Another question'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('support_conversations', ['id' => $conversation->id, 'status' => 'open']);
        $this->assertFalse(Gate::forUser(User::factory()->create(['role' => 'kyc_officer']))->allows('view', $conversation));
    }

    public function test_auditor_cannot_send_support_reply(): void
    {
        $conversation = SupportConversation::factory()->create();
        Livewire::actingAs(User::factory()->create(['role' => 'auditor']))->test(OperationsCenter::class)
            ->set('reply', 'Unauthorized')->call('respond', $conversation->id)->assertForbidden();
        $this->assertDatabaseCount('support_messages', 0);
    }

    public function test_financial_review_requires_step_up_password(): void
    {
        $staff = User::factory()->create(['role' => 'operations_admin']);
        $customer = User::factory()->create();
        $this->actingAs($customer)->post('/deposit', ['amount' => '100', 'request_key' => (string) Str::uuid()]);
        $id = DB::table('deposit_requests')->value('id');
        Livewire::actingAs($staff)->test(OperationsCenter::class)->set('module', 'deposits')
            ->set('note', 'Review demo request')->set('password', 'wrong')->call('review', $id, 'approved')->assertForbidden();
        $this->assertDatabaseHas('deposit_requests', ['id' => $id, 'status' => 'pending']);
        $this->assertDatabaseCount('ledger_transactions', 0);
    }
}
