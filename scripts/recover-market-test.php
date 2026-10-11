<?php
require __DIR__.'/../vendor/autoload.php';
use Symfony\Component\Process\Process;
$mysql=__DIR__.'/../.runtime/mysql-8.4.11-winx64/bin/mysql.exe';
$audit=new PDO('mysql:host=127.0.0.1;port=3308','root','');if((int)$audit->query('SELECT @@port')->fetchColumn()!==3308||!str_contains(str_replace('\\','/',$audit->query('SELECT @@datadir')->fetchColumn()),'mysql-audit-data'))throw new RuntimeException('Not audit server');$audit->exec('DROP DATABASE trading_app');$audit->exec('CREATE DATABASE trading_app');$audit->exec('SET GLOBAL innodb_flush_log_at_trx_commit=2');$audit->exec('SET GLOBAL sync_binlog=0');
$p=new Process([$mysql,'--host=127.0.0.1','--port=3308','--user=root','trading_app']);$p->setInput(fopen(__DIR__.'/../.runtime/recovery/replay.sql','r'));$p->setTimeout(600);$p->run();if(!$p->isSuccessful())throw new RuntimeException($p->getErrorOutput());
$pdo=new PDO('mysql:host=127.0.0.1;port=3308;dbname=trading_app','root','');$audit->exec('SET GLOBAL innodb_flush_log_at_trx_commit=1');$audit->exec('SET GLOBAL sync_binlog=1');$counts=[];foreach(['users','trading_pairs','trades','ledger_transactions','market_price_observations'] as $table)$counts[$table]=(int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
echo json_encode($counts,JSON_PRETTY_PRINT);file_put_contents(__DIR__.'/../.runtime/recovery/restored-counts.json',json_encode($counts,JSON_PRETTY_PRINT));
