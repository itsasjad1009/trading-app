<?php

namespace App\Http\Controllers;

use App\Events\AccountUpdated;
use App\Models\KycApplication;
use App\Models\SupportConversation;
use App\Services\WalletRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function deposit(Request $r, WalletRequests $requests)
    {
        $requests->funding($r->user()->id, $r->only('amount', 'request_key'));

        return back()->with('success', 'Demo funding request submitted for review. No blockchain transfer occurs.');
    }

    public function withdraw(Request $r, WalletRequests $requests)
    {
        $requests->withdrawal($r->user()->id, $r->only('amount', 'request_key', 'method', 'destination', 'password', 'code'));

        return back()->with('success', 'Demo withdrawal reserved for review. No funds will be sent.');
    }

    public function kyc(Request $r)
    {
        $v = $r->validate(['full_name' => 'required|string|max:100', 'national_id' => 'required|string|max:100', 'tax_id' => 'nullable|string|max:100', 'consent' => 'accepted', 'front' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.config('trading.kyc_max_kb')], 'back' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.config('trading.kyc_max_kb')]]);
        $paths = [];
        try {
            DB::transaction(function () use ($r, $v, &$paths) {
                DB::table('users')->where('id', $r->user()->id)->lockForUpdate()->first();
                $latest = DB::table('kyc_applications')->where('user_id', $r->user()->id)->latest('id')->first();
                if ($latest && in_array($latest->status, ['pending', 'under_review', 'approved'])) {
                    throw ValidationException::withMessages(['full_name' => 'Your application is already submitted.']);
                }
                $id = DB::table('kyc_applications')->insertGetId(['user_id' => $r->user()->id, 'full_name' => encrypt($v['full_name']), 'national_id' => encrypt($v['national_id']), 'tax_id' => isset($v['tax_id']) ? encrypt($v['tax_id']) : null, 'consented_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                foreach (['front', 'back'] as $side) {
                    $path = $r->file($side)->store('kyc', 'local');
                    $paths[] = $path;
                    DB::table('kyc_documents')->insert(['kyc_application_id' => $id, 'side' => $side, 'path' => $path, 'mime' => $r->file($side)->getMimeType(), 'created_at' => now(), 'updated_at' => now()]);
                }
            });
        } catch (\Throwable $e) {
            foreach ($paths as $path) {
                Storage::disk('local')->delete($path);
            } throw $e;
        }

        return back()->with('success', 'Identity documents submitted privately for review.');
    }

    public function document(Request $r, int $id)
    {
        $doc = DB::table('kyc_documents')->where('id', $id)->first();
        abort_unless($doc, 404);
        Gate::authorize('view', KycApplication::findOrFail($doc->kyc_application_id));

        return Storage::disk('local')->download($doc->path, 'identity-document', ['Content-Type' => $doc->mime, 'Cache-Control' => 'no-store']);
    }

    public function message(Request $r)
    {
        $v = $r->validate(['body' => 'required|string|max:4000', 'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120']);
        $path = null;
        try {
            DB::transaction(function () use ($r, $v, &$path) {
                DB::table('users')->where('id', $r->user()->id)->lockForUpdate()->first();
                $c = DB::table('support_conversations')->where('user_id', $r->user()->id)->first();
                $id = $c?->id ?? DB::table('support_conversations')->insertGetId(['user_id' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('support_conversations')->where('id', $id)->update(['status' => 'open', 'updated_at' => now()]);
                $path = $r->file('attachment')?->store('support', 'local');
                DB::table('support_messages')->insert(['support_conversation_id' => $id, 'user_id' => $r->user()->id, 'body' => $v['body'], 'attachment' => $path, 'created_at' => now(), 'updated_at' => now()]);
                DB::afterCommit(fn () => AccountUpdated::dispatch($r->user()->id));
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }

        return back()->with('success', 'Message sent.');
    }

    public function attachment(Request $r, int $id)
    {
        $message = DB::table('support_messages')->where('id', $id)->first();
        abort_unless($message && $message->attachment, 404);
        Gate::authorize('view', SupportConversation::findOrFail($message->support_conversation_id));

        return Storage::disk('local')->download($message->attachment, 'support-attachment', ['Cache-Control' => 'no-store']);
    }

    public function settings(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:100', 'phone' => 'nullable|string|max:30']);
        $r->user()->update($v);

        return back()->with('success', 'Profile updated.');
    }
}
