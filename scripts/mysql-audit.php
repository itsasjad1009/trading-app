<?php

// Isolated MySQL integration suite. Never connects to the development database.
use App\Models\User;
use App\Services\DemoMarket;
use App\Services\Ledger;
use App\Services\LedgerAudit;
use App\Services\Operations;
use App\Services\TradingEngine;
use App\Services\WalletRequests;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$worker = ($argv[1] ?? '') === 'worker';
$database = $worker ? $argv[2] : 'aster_audit_'.date('Ymd_His');
if (! preg_match('/^aster_audit_\d{8}_\d{6}$/', $database)) {
    throw new RuntimeException('Unsafe audit database');
}
config(['database.default' => 'mysql', 'database.connections.mysql.host' => '127.0.0.1', 'database.connections.mysql.port' => 3308, 'database.connections.mysql.database' => $database, 'database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => '', 'broadcasting.default' => 'null', 'cache.default' => 'array', 'mail.default' => 'array', 'queue.default' => 'sync']);
DB::purge('mysql');
if ($worker) {
    $action = $argv[3];
    $id = (int) $argv[4];
    $key = $argv[5];
    $amount = $argv[6] ?? '7000';
    try {
        if (in_array($action, ['settle', 'interrupt'])) {
            Carbon::setTestNow(now()->addSeconds(500));
            if ($action === 'interrupt') {
                DB::listen(function ($query) {
                    if (str_contains($query->sql, 'insert into `trade_settlements`')) {
                        exit(17);
                    }
                });
            }
            app(TradingEngine::class)->settle($id);
        } elseif ($action === 'trade') {
            app(TradingEngine::class)->place($id, ['amount' => $amount, 'pair_id' => 1, 'direction' => 'buy', 'duration' => 360, 'request_key' => $key]);
        } elseif ($action === 'withdraw') {
            app(WalletRequests::class)->withdrawal($id, ['amount' => $amount, 'request_key' => $key, 'method' => 'crypto', 'destination' => 'Demo wallet destination', 'password' => 'AuditFixture!123']);
        } elseif ($action === 'fund') {
            app(WalletRequests::class)->funding($id, ['amount' => '100', 'request_key' => $key]);
        }
        echo 'accepted';
    } catch (ValidationException $e) {
        echo 'rejected';
    }
    exit;
}
$pdo = new PDO('mysql:host=127.0.0.1;port=3308', 'root', '');
$actual = str_replace('\\', '/', rtrim($pdo->query('SELECT @@datadir')->fetchColumn(), '/\\'));
$expected = str_replace('\\', '/', realpath(__DIR__.'/../.runtime/mysql-audit-data'));
if (strcasecmp($actual, $expected) !== 0) {
    throw new RuntimeException('Refusing to create audit data outside the dedicated MySQL data directory');
}
$pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
Artisan::call('migrate', ['--force' => true]);
Artisan::call('db:seed', ['--force' => true]);
$results = [];
function check(string $name, bool $passed, array $details = []): void
{
    global $results;
    $results[] = ['case' => $name, 'passed' => $passed, 'details' => $details];
    echo "$name: ".($passed ? 'PASS' : 'FAIL')."\n";
}
function customer(): User
{
    $u = User::factory()->create(['name' => 'Isolated audit fixture', 'password' => Hash::make('AuditFixture!123')]);
    app(Ledger::class)->fund($u->id);

    return $u;
}
function race(array $jobs): array
{
    global $database;
    $ps = [];
    foreach ($jobs as $job) {
        $p = new Process([PHP_BINARY, '-d', 'extension=intl', __FILE__, 'worker', $database, ...array_map('strval', $job)]);
        $p->setTimeout(45);
        $p->start();
        $ps[] = $p;
    }$out = [];
    foreach ($ps as $p) {
        $p->wait();
        if (! $p->isSuccessful() && $p->getExitCode() !== 17) {
            throw new RuntimeException($p->getErrorOutput());
        }$out[] = $p->getExitCode() === 17 ? 'interrupted' : trim($p->getOutput());
    }sort($out);

    return $out;
}
function job(string $action, int $id, string $amount = '7000'): array
{
    return [$action, $id, (string) Str::uuid(), $amount];
}
$u = customer();
$out = race([job('trade', $u->id), job('trade', $u->id)]);
check('Concurrent trades', $out === ['accepted', 'rejected'] && app(Ledger::class)->wallet($u->id)['available'] === '3000.00', ['workers' => $out]);
$u = customer();
$out = race(array_fill(0, 4, job('trade', $u->id, '2500'))); // unique keys below are required for distinct debits
// Above intentionally retries one key; separately verify four distinct debits.
check('Concurrent same-key trade retries', $out === array_fill(0, 4, 'accepted') && DB::table('trades')->where('user_id', $u->id)->count() === 1);
$u = customer();
$out = race(array_map(fn () => job('trade', $u->id, '2500'), range(1, 4)));
check('Four simultaneous wallet debits', $out === array_fill(0, 4, 'accepted') && app(Ledger::class)->wallet($u->id)['available'] === '0.00');
$u = customer();
$out = race([job('withdraw', $u->id), job('withdraw', $u->id)]);
check('Simultaneous withdrawals', $out === ['accepted', 'rejected'] && app(Ledger::class)->wallet($u->id)['reserved'] === '7000.00');
$out = race([job('trade', $u->id, '4000')]);
check('Trade while withdrawal pending', $out === ['rejected'] && app(Ledger::class)->wallet($u->id)['available'] === '3000.00');
$u = customer();
$out = race([job('trade', $u->id), job('withdraw', $u->id)]);
check('Mixed trade and withdrawal contention', $out === ['accepted', 'rejected']);
$u = customer();
$trade = app(TradingEngine::class)->place($u->id, ['amount' => '100', 'pair_id' => 1, 'direction' => 'buy', 'duration' => 60, 'request_key' => (string) Str::uuid()]);
$snapshot = DB::table('trades')->find($trade);
$out = race([job('settle', $trade), job('settle', $trade), job('settle', $trade)]);
$s = DB::table('trade_settlements')->where('trade_id', $trade)->first();
$pair = DB::table('trading_pairs')->find(1);
$expected = app(DemoMarket::class)->price($pair, Carbon::parse($snapshot->expires_at)->timestamp);
check('Duplicate delayed settlements', DB::table('trade_settlements')->where('trade_id', $trade)->count() === 1 && $s->expiry_price_units === $expected && $s->observed_at === $snapshot->expires_at, ['workers' => $out]);
$trade = app(TradingEngine::class)->place($u->id, ['amount' => '100', 'pair_id' => 1, 'direction' => 'buy', 'duration' => 60, 'request_key' => (string) Str::uuid()]);
$out = race([job('interrupt', $trade)]);
check('Interrupted settlement rolls back', $out === ['interrupted'] && DB::table('trades')->find($trade)->status === 'open' && ! DB::table('trade_settlements')->where('trade_id', $trade)->exists() && ! DB::table('ledger_transactions')->where('idempotency_key', "trade:$trade:settlement")->exists());
race([job('settle', $trade)]);
check('Retry after interruption', DB::table('trade_settlements')->where('trade_id', $trade)->count() === 1);
$u = customer();
$key = (string) Str::uuid();
$out = race(array_fill(0, 4, ['fund', $u->id, $key]));
$request = DB::table('deposit_requests')->where('user_id', $u->id)->first();
check('Idempotent funding requests', $out === array_fill(0, 4, 'accepted') && DB::table('deposit_requests')->where('user_id', $u->id)->count() === 1 && app(Ledger::class)->wallet($u->id)['available'] === '10000.00');
$staff = User::factory()->create(['role' => 'operations_admin']);
app(Operations::class)->review($staff, 'deposit', $request->id, 'approved', 'Isolated audit');
try {
    app(Operations::class)->review($staff, 'deposit', $request->id, 'approved', 'Retry');
    $blocked = false;
} catch (HttpException $e) {
    $blocked = $e->getStatusCode() === 409;
}check('Duplicate funding review rejected', $blocked && app(Ledger::class)->wallet($u->id)['available'] === '10100.00');
$before = app(Ledger::class)->wallet($u->id);
try {
    app(WalletRequests::class)->withdrawal($u->id, ['amount' => '100', 'request_key' => (string) Str::uuid(), 'method' => 'crypto', 'destination' => 'Demo destination', 'password' => 'wrong']);
    $blocked = false;
} catch (ValidationException) {
    $blocked = true;
}check('Wrong-password authorization', $blocked && app(Ledger::class)->wallet($u->id) === $before);
$u->forceFill(['status' => 'suspended'])->save();
try {
    app(WalletRequests::class)->funding($u->id, ['amount' => '100', 'request_key' => (string) Str::uuid()]);
    $blocked = false;
} catch (HttpException $e) {
    $blocked = $e->getStatusCode() === 403;
}check('Suspended authorization', $blocked);
$audit = app(LedgerAudit::class)->inspect();
check('Ledger and reservations reconcile', array_sum($audit) === 0, $audit);
$report = ['database' => $database, 'port' => 3308, 'version' => DB::selectOne('SELECT VERSION() AS version')->version, 'results' => $results, 'passed' => ! in_array(false, array_column($results, 'passed'), true), 'development_database_modified' => false];
file_put_contents(__DIR__.'/../test-artifacts/mysql-audit-results.json', json_encode($report, JSON_PRETTY_PRINT));
exit($report['passed'] ? 0 : 1);
