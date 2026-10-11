<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_pairs', function (Blueprint $t) {
            $t->string('exchange_symbol')->nullable();
            $t->string('data_provider')->default('binance_spot');
            $t->string('market_type')->default('spot');
        });
        foreach (DB::table('trading_pairs')->get() as $p) {
            $s = str_replace('/', '', $p->symbol);
            if (in_array($s, ['BTCUSDT', 'ETHUSDT', 'LTCUSDT', 'DOGEUSDT', 'XRPUSDT', 'ADAUSDT'])) {
                DB::table('trading_pairs')->where('id', $p->id)->update(['exchange_symbol' => $s]);
            }
        }
        Schema::create('market_price_observations', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 32);
            $t->string('market_type', 16);
            $t->string('symbol', 24);
            $t->unsignedBigInteger('trade_id');
            $t->decimal('price', 36, 18);
            $t->decimal('quantity', 36, 18);
            $t->unsignedBigInteger('event_ms');
            $t->unsignedBigInteger('trade_ms');
            $t->unsignedBigInteger('received_ms');
            $t->string('source', 24);
            $t->boolean('valid')->default(true);
            $t->unique(['provider', 'market_type', 'symbol', 'trade_id'], 'market_observation_unique');
            $t->index(['symbol', 'trade_ms', 'trade_id']);
            $t->index('received_ms');
        });
        Schema::create('market_candles', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 32);
            $t->string('market_type', 16);
            $t->string('symbol', 24);
            $t->unsignedInteger('interval_seconds');
            $t->unsignedBigInteger('open_ms');
            $t->unsignedBigInteger('close_ms');
            foreach (['open', 'high', 'low', 'close', 'volume', 'quote_volume'] as $c) {
                $t->decimal($c, 36, 18);
            }$t->boolean('closed');
            $t->string('source', 24);
            $t->unsignedBigInteger('updated_ms');
            $t->unique(['provider', 'market_type', 'symbol', 'interval_seconds', 'open_ms'], 'market_candle_unique');
            $t->index('open_ms');
        });
        Schema::create('market_data_incidents', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 32);
            $t->string('symbol', 24)->nullable();
            $t->string('type', 32);
            $t->json('metadata');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_data_incidents');
        Schema::dropIfExists('market_candles');
        Schema::dropIfExists('market_price_observations');
        Schema::table('trading_pairs', fn (Blueprint $t) => $t->dropColumn(['exchange_symbol', 'data_provider', 'market_type']));
    }
};
