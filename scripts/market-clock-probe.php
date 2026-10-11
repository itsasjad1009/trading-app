<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$path=base_path('test-artifacts/market-data/phase2b-clocks-'.date('Ymd-His').'.jsonl');
$file=fopen($path,'x');$end=microtime(true)+(int)($argv[1]??1200);
while(microtime(true)<$end){$start=hrtime(true);try{$clock=app(App\MarketData\BinanceSpotProvider::class)->serverTime();$clock['wall_ms']=now()->getTimestampMs();$clock['monotonic_rtt_ms']=(hrtime(true)-$start)/1e6;$clock['uncertainty_ms']=$clock['round_trip_ms']/2;Illuminate\Support\Facades\Cache::put('market:clock',$clock,86400);fwrite($file,json_encode($clock)."\n");}catch(Throwable $e){fwrite($file,json_encode(['wall_ms'=>now()->getTimestampMs(),'error'=>get_class($e)])."\n");}for($i=0;$i<60&&microtime(true)<$end;$i++)sleep(1);}fclose($file);echo $path;
