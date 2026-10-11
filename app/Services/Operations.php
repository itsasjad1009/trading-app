<?php

namespace App\Services;

use App\Events\AccountUpdated;
use App\Models\User;
use App\Notifications\ReviewCompleted;
use Illuminate\Support\Facades\DB;

class Operations
{
    public function authorize(User $actor, array $roles): void
    {
        abort_unless($actor->status === 'active' && $actor->hasVerifiedEmail() && in_array($actor->role, $roles, true), 403);
    }

    public function review(User $actor, string $kind, int $id, string $decision, string $note): void
    {
        $this->authorize($actor, $kind === 'kyc' ? ['super_admin', 'kyc_officer'] : ['super_admin', 'operations_admin']);
        abort_unless(in_array($kind, ['kyc', 'deposit', 'withdrawal']) && in_array($decision, ['approved', 'rejected', 'resubmission_required', 'under_review']), 422);
        abort_if($kind !== 'kyc' && in_array($decision, ['resubmission_required', 'under_review']), 422);
        $table = ['kyc' => 'kyc_applications', 'deposit' => 'deposit_requests', 'withdrawal' => 'withdrawal_requests'][$kind];
        $owner = DB::table($table)->where('id', $id)->value('user_id');
        abort_unless($owner, 404);
        DB::transaction(function () use ($actor, $kind, $id, $decision, $note, $table, $owner) {
            DB::table('users')->where('id', $owner)->lockForUpdate()->first();
            $row = DB::table($table)->where('id', $id)->lockForUpdate()->first();
            abort_unless(in_array($row->status, ['pending', 'under_review']), 409, 'Request already reviewed.');
            if ($kind !== 'kyc') {
                $ledger = app(Ledger::class);
                $a = $ledger->accounts($owner);
                $amount = $row->amount_minor;
                if ($kind === 'deposit' && $decision === 'approved') {
                    $ledger->post($owner, "deposit:$id:approved", 'demo_funding', "Approved demo funding #$id", [$a['available'] => $amount, $a['treasury'] => -$amount]);
                }
                if ($kind === 'withdrawal') {
                    $ledger->post($owner, "withdrawal:$id:review", $decision === 'approved' ? 'demo_withdrawal' : 'refund', "Demo withdrawal #$id $decision", [$a['reserved'] => -$amount, $a[$decision === 'approved' ? 'treasury' : 'available'] => $amount]);
                }
            }
            $update = ['status' => $decision, 'review_note' => $note, 'reviewer_id' => $actor->id, 'updated_at' => now()];
            if ($kind !== 'kyc') {
                $update['reviewed_at'] = now();
            }
            DB::table($table)->where('id', $id)->update($update);
            $this->audit($actor, 'review.'.$decision, $table, $id, ['note' => $note]);
            DB::afterCommit(fn () => AccountUpdated::dispatch($owner));
            $user = User::find($owner);
            $user->notify(new ReviewCompleted($kind, $decision, $id));
        }, 5);
    }

    public function audit(User $actor, string $action, string $table, int $id, array $metadata = []): void
    {
        DB::table('audit_logs')->insert(['actor_id' => $actor->id, 'action' => $action, 'subject_type' => $table, 'subject_id' => $id, 'metadata' => json_encode($metadata), 'created_at' => now(), 'updated_at' => now()]);
    }
}
