<?php

namespace App\Filament\Pages;

use App\Events\AccountUpdated;
use App\Models\User;
use App\Services\Operations;
use App\Services\TradingRules;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\WithPagination;

class OperationsCenter extends Page
{
    protected string $view = 'filament.pages.operations-center';

    protected static ?string $navigationLabel = 'Operations Center';

    public string $module = 'users';

    public string $note = '';

    public string $password = '';

    public string $reply = '';

    public array $roles = [];

    public array $exchangeSymbols = [];

    public array $payouts = [60 => 7000, 90 => 7200, 120 => 7500, 180 => 7800, 360 => 8000];

    use WithPagination;

    public function modules(): array
    {
        $role = auth()->user()->role;
        if ($role === 'super_admin') {
            return ['users', 'kyc', 'deposits', 'withdrawals', 'pairs', 'rules', 'trades', 'transactions', 'support', 'referrals', 'audit', 'settings'];
        }

        return match ($role) {
            'operations_admin' => ['deposits', 'withdrawals', 'trades', 'transactions'],'kyc_officer' => ['kyc'],'support_agent' => ['support'],'auditor' => ['trades', 'transactions', 'referrals', 'audit'],default => []
        };
    }

    public function records()
    {
        if (! in_array($this->module, $this->modules())) {
            $this->module = $this->modules()[0] ?? 'audit';
        }
        $tables = ['users' => 'users', 'kyc' => 'kyc_applications', 'deposits' => 'deposit_requests', 'withdrawals' => 'withdrawal_requests', 'pairs' => 'trading_pairs', 'rules' => 'platform_settings', 'trades' => 'trades', 'transactions' => 'ledger_transactions', 'support' => 'support_conversations', 'referrals' => 'referrals', 'audit' => 'audit_logs', 'settings' => 'platform_settings'];
        $columns = match ($this->module) {
            'users' => ['id', 'name', 'email', 'role', 'status'],'kyc' => ['id', 'user_id', 'status', 'review_note'],'deposits','withdrawals' => ['id', 'user_id', 'amount_minor', 'status', 'review_note'],'pairs' => ['id', 'symbol', 'name', 'exchange_symbol', 'data_provider', 'market_type', 'enabled'],'trades' => ['id', 'user_id', 'direction', 'stake_minor', 'status', 'expires_at'],'support' => ['id', 'user_id', 'agent_id', 'status'],default => ['*']
        };

        $records = DB::table($tables[$this->module])->latest('id')->paginate(20, $columns);
        if ($this->module === 'users') {
            foreach ($records as $record) {
                $this->roles[$record->id] ??= $record->role;
            }
        }

        if ($this->module === 'pairs') { foreach ($records as $record) { $this->exchangeSymbols[$record->id] ??= $record->exchange_symbol; } }
        return $records;
    }

    public function updatedModule(): void
    {
        $this->resetPage();
        $this->payouts = app(TradingRules::class)->payouts();
    }

    public function saveRole(int $id): void
    {
        app(Operations::class)->authorize(auth()->user(), ['super_admin']);
        abort_unless(Hash::check($this->password, auth()->user()->password), 403, 'Step-up password required');
        $role = $this->roles[$id] ?? '';
        abort_unless(in_array($role, ['customer', 'super_admin', 'operations_admin', 'kyc_officer', 'support_agent', 'auditor']), 422);
        abort_if($id === auth()->id(), 422, 'Use another administrator for your own role');
        DB::transaction(function () use ($id, $role) {
            User::whereKey($id)->update(['role' => $role]);
            app(Operations::class)->audit(auth()->user(), 'user.role', 'users', $id, ['role' => $role]);
        });
        $this->reset('password');
    }

    public function savePayouts(): void
    {
        app(Operations::class)->authorize(auth()->user(), ['super_admin']);
        abort_unless(Hash::check($this->password, auth()->user()->password), 403);
        foreach ([60, 90, 120, 180, 360] as $duration) {
            $this->validate(["payouts.$duration" => 'required|integer|min:0|max:10000']);
        }
        $value = array_intersect_key($this->payouts, array_flip([60, 90, 120, 180, 360]));
        DB::transaction(function () use ($value) {
            DB::table('platform_settings')->updateOrInsert(['key' => 'payout_bps'], ['value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]);
            $id = DB::table('platform_settings')->where('key', 'payout_bps')->value('id');
            app(Operations::class)->audit(auth()->user(), 'rules.payout', 'platform_settings', $id, $value);
        });
        $this->reset('password');
    }

    public function review(int $id, string $decision): void
    {
        abort_unless(in_array($this->module, ['kyc', 'deposits', 'withdrawals']), 403);
        $this->validate(['note' => 'required|string|min:5|max:1000', 'password' => 'required|string']);
        abort_unless(Hash::check($this->password, auth()->user()->password), 403, 'Step-up password incorrect');
        app(Operations::class)->review(auth()->user(), ['kyc' => 'kyc', 'deposits' => 'deposit', 'withdrawals' => 'withdrawal'][$this->module], $id, $decision, $this->note);
        $this->reset('password', 'note');
        Notification::make()->title('Review saved')->success()->send();
    }

    public function toggleUser(int $id): void
    {
        app(Operations::class)->authorize(auth()->user(), ['super_admin']);
        abort_if($id === auth()->id(), 422);
        DB::transaction(function () use ($id) {
            $u = User::whereKey($id)->lockForUpdate()->firstOrFail();
            $u->status = $u->status === 'active' ? 'suspended' : 'active';
            $u->save();
            app(Operations::class)->audit(auth()->user(), 'user.status', 'users', $id, ['status' => $u->status]);
        });
    }

    public function saveMarketSymbol(int $id): void
    {
        app(Operations::class)->authorize(auth()->user(), ['super_admin']);
        $symbol = $this->exchangeSymbols[$id] ?? '';
        $this->validate(["exchangeSymbols.$id" => 'required|string|in:BTCUSDT,ETHUSDT,LTCUSDT,DOGEUSDT,XRPUSDT,ADAUSDT']);
        try { app(\App\MarketData\Contracts\MarketDataProvider::class)->validateSymbol($symbol); }
        catch (\Throwable $e) { $this->addError("exchangeSymbols.$id", 'Provider validation unavailable or symbol is not Spot-enabled.'); return; }
        DB::transaction(function () use ($id, $symbol) {
            $pair = DB::table('trading_pairs')->where('id', $id)->lockForUpdate()->first(); abort_unless($pair,404);
            abort_if(DB::table('trades')->where('trading_pair_id',$id)->where('status','open')->exists(),422,'Cannot change source mapping with active positions');
            abort_unless(str_replace('/','',$pair->symbol)===$symbol,422,'Exchange symbol must match the displayed pair');
            DB::table('trading_pairs')->where('id',$id)->update(['exchange_symbol'=>$symbol,'data_provider'=>'binance_spot','market_type'=>'spot']);
            app(Operations::class)->audit(auth()->user(),'pair.market_source','trading_pairs',$id,['provider'=>'binance_spot','symbol'=>$symbol,'market_type'=>'spot']);
        });
    }

    public function togglePair(int $id): void
    {
        app(Operations::class)->authorize(auth()->user(), ['super_admin']);
        DB::transaction(function () use ($id) {
            $p = DB::table('trading_pairs')->where('id', $id)->lockForUpdate()->first();
            abort_unless($p, 404);
            DB::table('trading_pairs')->where('id', $id)->update(['enabled' => ! $p->enabled]);
            app(Operations::class)->audit(auth()->user(), 'pair.enabled', 'trading_pairs', $id);
        });
    }

    public function respond(int $id): void
    {
        app(Operations::class)->authorize(auth()->user(), ['super_admin', 'support_agent']);
        $this->validate(['reply' => 'required|string|max:4000']);
        $c = DB::table('support_conversations')->find($id);
        abort_unless($c, 404);
        DB::transaction(function () use ($c) {
            DB::table('support_conversations')->where('id', $c->id)->update(['agent_id' => auth()->id()]);
            DB::table('support_messages')->insert(['support_conversation_id' => $c->id, 'user_id' => auth()->id(), 'body' => $this->reply, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('support_messages')->where('support_conversation_id', $c->id)->where('user_id', $c->user_id)->update(['read_at' => now()]);
            app(Operations::class)->audit(auth()->user(), 'support.reply', 'support_conversations', $c->id);
            DB::afterCommit(fn () => AccountUpdated::dispatch($c->user_id));
        });
        $this->reset('reply');
    }

    public function setConversationStatus(int $id, string $status): void
    {
        app(Operations::class)->authorize(auth()->user(), ['super_admin', 'support_agent']);
        abort_unless(in_array($status, ['open', 'closed']), 422);
        DB::transaction(function () use ($id, $status) {
            $conversation = DB::table('support_conversations')->where('id', $id)->lockForUpdate()->first();
            abort_unless($conversation, 404);
            DB::table('support_conversations')->where('id', $id)->update(['status' => $status, 'agent_id' => auth()->id(), 'updated_at' => now()]);
            app(Operations::class)->audit(auth()->user(), 'support.'.$status, 'support_conversations', $id);
            DB::afterCommit(fn () => AccountUpdated::dispatch($conversation->user_id));
        });
    }
}
