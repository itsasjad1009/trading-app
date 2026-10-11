<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
$identity=DB::selectOne('select database() as db, @@port as port');if($identity->db!=='trading_app'||(int)$identity->port!==3307)throw new RuntimeException('Unexpected application database identity');
foreach(['users','trades','ledger_transactions'] as $table)if(DB::table($table)->count()!==0)throw new RuntimeException('Application database changed; cannot replace nonempty records');
$root=base_path('.runtime/mysql-8.4.11-winx64/bin');$file=base_path('.runtime/recovery/recovered.sql');
$dump=new Process([$root.'/mysqldump.exe','--host=127.0.0.1','--port=3308','--user=root','--single-transaction','--no-tablespaces','--set-gtid-purged=OFF','--skip-add-locks','--result-file='.$file,'trading_app']);$dump->setTimeout(120);$dump->mustRun();
$conf=config('database.connections.mysql');$backup=new Process([$root.'/mysqldump.exe','--host=127.0.0.1','--port=3307','--user='.$conf['username'],'--single-transaction','--no-tablespaces','--set-gtid-purged=OFF','--result-file='.base_path('.runtime/recovery/empty-before-restore.sql'),'trading_app'],base_path(),['MYSQL_PWD'=>$conf['password']]);$backup->setTimeout(120);$backup->mustRun();
$import=new Process([$root.'/mysql.exe','--host=127.0.0.1','--port=3307','--user='.$conf['username'],'trading_app','--execute=source '.str_replace('\\','/',$file)],base_path(),['MYSQL_PWD'=>$conf['password']]);$import->setTimeout(180);$import->mustRun();
$recovered=new PDO('mysql:host=127.0.0.1;port=3308;dbname=trading_app','root','');$result=[];
foreach(['users','wallets','ledger_accounts','ledger_transactions','ledger_entries','trading_pairs','trades','trade_settlements','kyc_applications','kyc_documents','support_conversations','support_messages','deposit_requests','withdrawal_requests','referrals','audit_logs'] as $table){$expected=$recovered->query("SELECT * FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);$actual=DB::select("SELECT * FROM `$table` ORDER BY id");$canonical=fn($rows)=>array_map(fn($r)=>array_map(fn($v)=>$v===null?null:(string)$v,(array)$r),$rows);$eh=hash('sha256',json_encode($canonical($expected)));$ah=hash('sha256',json_encode($canonical($actual)));$result[$table]=['rows'=>count($actual),'matches_recovered_snapshot'=>$eh===$ah];if($eh!==$ah)throw new RuntimeException('Recovered table mismatch: '.$table);}
$result['ledger']=app(App\Services\LedgerAudit::class)->inspect();file_put_contents(base_path('test-artifacts/market-data/recovery-verification.json'),json_encode($result,JSON_PRETTY_PRINT));echo json_encode($result,JSON_PRETTY_PRINT);
