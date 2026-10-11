<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use App\Services\LedgerAudit;
use App\Services\Operations;
use App\Services\TradingEngine;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('trades:settle', function () {
    DB::table('trades')->where('status', 'open')->where('expires_at', '<=', now())->orderBy('id')->chunkById(100, function ($trades) {
        foreach ($trades as $trade) {
            app(TradingEngine::class)->settle($trade->id);
        }
    });
    $this->info('Due demo positions settled.');
});
Schedule::command('trades:settle')->everySecond()->withoutOverlapping();
Artisan::command('demo:verify {email}', function () {
    abort_unless(app()->environment('local', 'testing'), 403);
    $user = User::where('email', $this->argument('email'))->firstOrFail();
    $user->markEmailAsVerified();
    $this->info('Local demo account verified.');
});
Artisan::command('demo:role {email} {role}', function () {
    abort_unless(app()->environment('local', 'testing'), 403);
    $role = $this->argument('role');
    abort_unless(in_array($role, ['super_admin', 'operations_admin', 'kyc_officer', 'support_agent', 'auditor', 'customer']), 422);
    $user = User::where('email', $this->argument('email'))->firstOrFail();
    $user->forceFill(['role' => $role])->save();
    app(Operations::class)->audit($user, 'local.fixture.role', 'users', $user->id, ['role' => $role]);
    $this->info('Local demo fixture role set.');
});
Artisan::command('staff:create {--role=super_admin}', function () {
    $role = $this->option('role');
    if (! in_array($role, ['super_admin', 'operations_admin', 'kyc_officer', 'support_agent', 'auditor'])) {
        $this->error('Invalid staff role');

        return 1;
    }
    $name = $this->ask('Staff name');
    $email = $this->ask('Staff email');
    $password = $this->secret('Password (12+ chars, mixed case, number, symbol)');
    $user = app(CreateNewUser::class)->create(['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $password]);
    $user->forceFill(['role' => $role, 'email_verified_at' => now()])->save();
    $this->info('Staff account created. Login at /admin.');
});
Artisan::command('kyc:purge', function () {
    $cutoff = now()->subDays(config('trading.kyc_retention_days'));
    DB::table('kyc_documents')->where('created_at', '<', $cutoff)->orderBy('id')->chunkById(100, function ($documents) {
        foreach ($documents as $doc) {
            Storage::disk('local')->delete($doc->path);
            DB::table('kyc_documents')->where('id', $doc->id)->delete();
        }
    });
    $this->info('Expired private KYC documents removed.');
});
Schedule::command('kyc:purge')->daily();
Artisan::command('ledger:reconcile', function () {
    $findings = app(LedgerAudit::class)->inspect();
    $this->line(json_encode($findings));
    $failed = array_sum($findings) > 0;
    $this->info($failed ? 'Ledger discrepancies detected.' : 'All ledger transactions and reservations reconcile.');

    return $failed ? 1 : 0;
});

Artisan::command('market:backfill {--interval=60} {--count=180}', function () {
 abort_unless(app(\App\MarketData\AccessGate::class)->workerAllowed(),403);
 foreach(DB::table('trading_pairs')->where('enabled',true)->whereNotNull('exchange_symbol')->get() as $pair){$result=app(\App\MarketData\History::class)->window($pair->exchange_symbol,(int)$this->option('interval'),min(5000,max(1,(int)$this->option('count'))));$this->line($pair->exchange_symbol.': '.count($result['candles']).' candles; '.$result['missing_count'].' gaps');}
});
Artisan::command('market:prune', function () {
 // Live-settlement orders do not exist; fail closed if the source is ever changed.
 abort_unless(config('market-data.settlement_source')==='simulated',403);
 $hours=max(24,(int)config('market-data.observation_retention_hours'));
 $oldest=DB::table('trades')->where('status','open')->min('opened_at');$cutoff=now()->subHours($hours)->getTimestampMs();if($oldest)$cutoff=min($cutoff,\Carbon\Carbon::parse($oldest)->subDay()->getTimestampMs());
 $count=DB::table('market_price_observations')->where('received_ms','<',$cutoff)->delete();
 DB::table('market_candles')->where('open_ms','<',now()->subDays(max(30,(int)config('market-data.candle_retention_days')))->getTimestampMs())->delete();
 DB::table('market_data_incidents')->where('created_at','<',now()->subDays(30))->delete();$this->info('Expired display observations removed: '.$count);
});
Schedule::command('market:prune')->hourly()->withoutOverlapping();
