<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class DatabaseSafety
{
    public static function isolatedTests(): bool
    {
        if (!app()->environment('testing')) return false;
        $driver = config('database.default');
        if ($driver === 'sqlite') return config('database.connections.sqlite.database') === ':memory:';
        if ($driver !== 'mysql') return false;
        $c = config('database.connections.mysql');
        if (($c['host'] ?? null) !== '127.0.0.1' || (int) $c['port'] !== 3308 || ($c['username'] ?? null) !== 'aster_test' || !preg_match('/^aster_market_test_\d{8}_\d{6}$/', $c['database'])) return false;
        try {
            $i = DB::selectOne('SELECT database() db,@@port port,@@datadir datadir');
            return $i->db === $c['database'] && (int) $i->port === 3308 && str_contains(str_replace('\\', '/', $i->datadir), 'Trading app/.runtime/mysql-audit-data');
        } catch (\Throwable) {return false;}
    }
}
