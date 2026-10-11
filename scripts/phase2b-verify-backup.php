<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$dir = trim(file_get_contents(base_path('.runtime/phase2b-backup-path.txt')));
$audit = new PDO('mysql:host=127.0.0.1;port=3308', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$identity = $audit->query('SELECT @@port port,@@datadir datadir')->fetch(PDO::FETCH_ASSOC);
if ((int) $identity['port'] !== 3308 || !str_contains(str_replace('\\', '/', $identity['datadir']), 'Trading app/.runtime/mysql-audit-data')) throw new RuntimeException('Wrong isolated instance');
$db = 'aster_restore_'.date('Ymd_His');
$audit->exec("CREATE DATABASE `$db`");
$process = new Symfony\Component\Process\Process([base_path('.runtime/mysql-8.4.11-winx64/bin/mysql.exe'), '--host=127.0.0.1', '--port=3308', '--user=root', $db, '--execute=source '.str_replace('\\', '/', $dir.'/database.sql')]);
$process->setTimeout(180);$process->mustRun();
$audit->exec("USE `$db`");
$tables = [];
foreach ($audit->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) $tables[$table] = (int) $audit->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
$files = 0;
foreach (json_decode(file_get_contents($dir.'/manifest.json'), true) as $entry) {
 $path = $dir.$entry['RelativePath'];
 if (hash_file('sha256', $path) !== strtolower($entry['SHA256'])) throw new RuntimeException('Backup checksum mismatch');
 $files++;
}
$password = bin2hex(random_bytes(24));
$audit->exec("CREATE USER IF NOT EXISTS 'aster_test'@'127.0.0.1' IDENTIFIED BY '$password'");
$audit->exec("ALTER USER 'aster_test'@'127.0.0.1' IDENTIFIED BY '$password'");
$audit->exec("GRANT ALL PRIVILEGES ON `aster\\_market\\_test\\_%`.* TO 'aster_test'@'127.0.0.1'");
file_put_contents(base_path('.runtime/market-test-credentials.json'), json_encode(['username'=>'aster_test','password'=>$password]));
$limited = new PDO('mysql:host=127.0.0.1;port=3308', 'aster_test', $password);
$denied = false;
try {$limited->query('SELECT COUNT(*) FROM trading_app.users');} catch (PDOException $e) {$denied = true;}
if (!$denied) throw new RuntimeException('Test credentials can read recovery database');
$report = ['restored_database'=>$db,'tables'=>$tables,'checksummed_files'=>$files,'test_user_recovery_database_access_denied'=>$denied,'development'=>Illuminate\Support\Facades\DB::selectOne('SELECT database() db,@@port port'),'ledger'=>app(App\Services\LedgerAudit::class)->inspect()];
file_put_contents(base_path('test-artifacts/market-data/phase2b-backup-verification.json'), json_encode($report, JSON_PRETTY_PRINT));
echo json_encode($report, JSON_PRETTY_PRINT);
