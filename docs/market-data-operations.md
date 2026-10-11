# Native Windows operations

From C:\Users\style\Desktop\Trading app. PHP here is C:\xampp\php\php.exe; load intl with -d extension=intl. MySQL8.4 is the project instance on3307; the user's3306 service is not used. Never run migrate:fresh on trading_app.

Separate PowerShell terminals:

```powershell
& ./scripts/start-mysql.ps1
C:\xampp\php\php.exe -d extension=intl artisan serve --host=127.0.0.1 --port=8000
$env:Path='C:\Users\style\AppData\Local\nvm\v22.23.2;'+$env:Path
npm run dev
C:\xampp\php\php.exe -d extension=intl artisan reverb:start --host=127.0.0.1 --port=8090
C:\xampp\php\php.exe -d extension=intl artisan queue:work --queue=market-display,default --tries=3 --timeout=150
C:\xampp\php\php.exe -d extension=intl artisan schedule:work
C:\xampp\php\php.exe -d extension=intl artisan market:backfill --interval=60 --count=180
C:\xampp\php\php.exe -d extension=intl artisan market:stream
```

Local .env: MARKET_DATA_ENABLED=true; public redistribution and rights verified remain false. Published .env.example defaults all flags false. If using built assets, npm run build instead of Vite dev. Redis is optional: configure a native reachable Redis instance, CACHE_STORE=redis and matching cache prefix; database cache is the current local fallback. Restart long-lived processes after configuration/code changes.

Use only one market worker. Second-worker startup fails while the shared lease is owned. Ctrl+C stops the foreground worker. For a bounded test use --seconds=180 --report=test-artifacts/market-data/soak.json. Report path must be inside the workspace. Do not force-unlock a lease held by a running worker. A crash expires in45s.

GET /market/health requires verified active super_admin/operations_admin/auditor and gives symbol freshness, rate-budget status, row counts and recent incidents. Live endpoints additionally require the local/public gate. All account updates remain private.

Isolated MySQL tests: start scripts/start-audit-mysql.ps1 (dedicated3308 data directory), then php -d extension=intl scripts/market-mysql-tests.php. Tests require an aster_market_test_TIMESTAMP database on the verified audit instance. Test database is preserved for inspection; never redirect this runner to the application database. Protect .runtime/recovery artifacts because recovered SQL contains private application data.
