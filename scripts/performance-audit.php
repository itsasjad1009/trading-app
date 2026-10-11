<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Read-only application profiling in the dedicated MySQL audit database.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$audit = json_decode(file_get_contents(__DIR__.'/../test-artifacts/mysql-audit-results.json'), true);
$db = $audit['database'];
if (! preg_match('/^aster_audit_\d{8}_\d{6}$/', $db)) {
    throw new RuntimeException('Invalid audit database');
}
config(['database.default' => 'mysql', 'database.connections.mysql.host' => '127.0.0.1', 'database.connections.mysql.port' => 3308, 'database.connections.mysql.database' => $db, 'database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => '', 'cache.default' => 'array', 'broadcasting.default' => 'null', 'session.driver' => 'array']);
DB::purge('mysql');
$u = User::where('status', 'active')->firstOrFail();
$queries = 0;
$queryMs = 0;
DB::listen(function ($q) use (&$queries, &$queryMs) {
    $queries++;
    $queryMs += $q->time;
});
$results = [];
foreach (['dashboard', 'trade', 'positions', 'transactions', 'support', 'profile'] as $page) {
    $runs = [];
    for ($i = 0; $i < 5; $i++) {
        Auth::guard('web')->setUser($u);
        $queries = 0;
        $queryMs = 0;
        $start = microtime(true);
        $request = Request::create('/'.$page, 'GET');
        $request->headers->set('X-Inertia', 'true');
        $request->headers->set('X-Inertia-Version', hash_file('xxh128', public_path('build/manifest.json')));
        $request->headers->set('Accept', 'text/html, application/xhtml+xml');
        $response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);
        $runs[] = ['status' => $response->getStatusCode(), 'queries' => $queries, 'sql_ms' => round($queryMs, 2), 'request_ms' => round((microtime(true) - $start) * 1000, 2)];
    }$results[$page] = $runs;
}
file_put_contents(__DIR__.'/../test-artifacts/performance-results.json', json_encode(['environment' => 'Local CLI HTTP kernel, dedicated MySQL 8.4, five sequential requests per route; not browser/network latency', 'results' => $results], JSON_PRETTY_PRINT));
echo json_encode($results, JSON_PRETTY_PRINT);
exit(in_array(200, array_column($results['trade'], 'status')) ? 0 : 1);
