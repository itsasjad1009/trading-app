<?php

namespace Tests\Feature;

use App\Support\DatabaseSafety;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Tests\TestCase;

class DatabaseSafetyTest extends TestCase
{
    public function test_development_database_is_rejected_even_with_testing_environment(): void
    {
        config(['database.default'=>'mysql','database.connections.mysql.host'=>'127.0.0.1','database.connections.mysql.port'=>3307,'database.connections.mysql.database'=>'trading_app']);
        $this->assertFalse(DatabaseSafety::isolatedTests());
    }
    public function test_root_test_credentials_are_rejected(): void
    {
        config(['database.default'=>'mysql','database.connections.mysql.host'=>'127.0.0.1','database.connections.mysql.port'=>3308,'database.connections.mysql.database'=>'aster_market_test_20261011_000000','database.connections.mysql.username'=>'root']);
        $this->assertFalse(DatabaseSafety::isolatedTests());
    }
    public function test_disk_sqlite_is_rejected(): void
    {
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>'development.sqlite']);
        $this->assertFalse(DatabaseSafety::isolatedTests());
    }
    public function test_local_boot_prohibits_destructive_commands_without_executing_them(): void
    {
        $this->app->instance('env','local');
        (new \App\Providers\AppServiceProvider($this->app))->boot();
        $method=new \ReflectionMethod(FreshCommand::class,'isProhibited');
        $this->assertTrue($method->invoke($this->app->make(FreshCommand::class),true));
        $this->app->instance('env','testing');
        (new \App\Providers\AppServiceProvider($this->app))->boot();
    }
}

