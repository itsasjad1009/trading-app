# Aster Exchange

A Laravel 12 / React / Inertia paper-trading application inspired by the supplied references. All balances are virtual USDT. No real deposit destination, custody, payment execution or referral rewards are enabled.

## Current local setup

- App: http://127.0.0.1:8000
- Staff: http://127.0.0.1:8000/admin
- Database: **MySQL Community Server 8.4.11**, 127.0.0.1:3307, database trading_app, scoped user aster.
- Existing MariaDB on 3306 is preserved and is no longer the application database.
- Reverb: 127.0.0.1:8090 (avoids Apache ports 80/443/8080).
- PHP: installed XAMPP PHP 8.2.12; enable intl for each command using `php -d extension=intl`.
- Node: use the already installed 22.23.2 runtime. Node 20.9 is too old for current Echo/test tools.
- Generated database and app secrets are in ignored .env/runtime files, never in this README.

## Start locally on Windows without Docker

Open PowerShell in this directory. If MySQL is stopped:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/start-mysql.ps1
```

Use separate terminal tabs for each long-running process:

`composer dev` starts the Laravel web server only on Windows; queue, scheduler and Reverb still require the commands below. The stock Pail/concurrently script has been removed.

```powershell
php -d extension=intl artisan serve --host=127.0.0.1 --port=8000
php -d extension=intl artisan queue:work --tries=3 --timeout=60
php -d extension=intl artisan schedule:work
php -d extension=intl artisan reverb:start --host=127.0.0.1 --port=8090
```

The production frontend is built already. For frontend development, another terminal:

```powershell
$env:Path="$env:LOCALAPPDATA\nvm\v22.23.2;" + $env:Path
npm run dev
```

Register through /register. Local mail uses the log driver: verification/reset links appear in storage/logs/laravel.log. Alternatively, for local demo testing only:

```powershell
php -d extension=intl artisan demo:verify your-demo-email@example.test
```

Create a staff account interactively; the password is prompted privately:

```powershell
php -d extension=intl artisan staff:create --role=super_admin
```

## Fresh Laragon installation

Install PHP 8.2+ with intl, bcmath, fileinfo, mbstring, openssl, curl, zip and PDO MySQL, Node 22.23+, Composer, and **MySQL 8**, not an XAMPP MariaDB substitute. Configure Laragon to use that MySQL binary. Create an empty trading_app database with utf8mb4 and a scoped application user. Point the web document root to this project's public directory.

```powershell
Copy-Item .env.example .env
php -d extension=intl C:\ProgramData\ComposerSetup\bin\composer.phar install
npm ci
php -d extension=intl artisan key:generate
```

Edit .env privately: DB_HOST, DB_PORT (3306 for a standard Laragon instance, 3307 for this project's portable instance), DB_DATABASE, DB_USERNAME and DB_PASSWORD. Do not overwrite the existing configured .env on this machine. Generate Reverb credentials with its installer, then retain port 8090:

```powershell
php -d extension=intl artisan reverb:install --no-interaction
php -d extension=intl artisan migrate
php -d extension=intl artisan db:seed
npm run build
php -d extension=intl artisan db:show
```

Confirm db:show reports MySQL 8. The portable runtime is excluded from source control; new machines need their own MySQL installation. Official installation references: [Laravel 12](https://laravel.com/docs/12.x/installation), [MySQL Windows ZIP setup](https://dev.mysql.com/doc/refman/8.4/en/windows-install-archive.html).

## Verification

```powershell
php -d extension=intl artisan test --compact
npm test
npm run build
npx playwright install chromium
npx playwright test
./scripts/start-audit-mysql.ps1
php -d extension=intl scripts/mysql-audit.php
npx playwright test --config=playwright.visual.config.ts
php -d extension=intl artisan trades:settle
php -d extension=intl artisan ledger:reconcile
```

The native audit runs 14 contention, retry, interruption and authorization checks in a uniquely named database on a separate loopback MySQL instance at port 3308. It validates the isolated data directory before creating data; scripts/concurrency-check.php now redirects to that safe suite. No active database reset is used. Stop the dedicated test instance afterward; its data is retained under .runtime/mysql-audit-data. Browser tests create labeled demo fixtures and grant those fixtures staff access through a CLI restricted to local/testing environments. Never use a production database for browser tests.

Final compact UI and independent audit results are in docs/qa-results.md and docs/ui-audit-after.md. Actual before/after screenshots at five viewport sizes are under test-artifacts/visual-review.

See docs/local-development.md, docs/testing.md and docs/development-report.md for setup, results and limitations. Visual specification and differences are documented; no exact pixel-match claim is made.
