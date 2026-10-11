# Local development

See README for exact commands. Use four terminals for Laravel, queue, scheduler and Reverb; a fifth is optional for Vite. The server is available at http://127.0.0.1:8000. Existing Apache ports are left unchanged. Reverb is 8090. Project-local MySQL 8.4.11 runs on 3307; its configuration and data live in ignored .runtime/mysql-8.4.11-winx64. Restart it with scripts/start-mysql.ps1. Existing MariaDB on 3306 remains intact.

Do not replace .env with .env.example on this configured machine. DB credentials were generated privately and the application user is scoped to trading_app. Verify with php -d extension=intl artisan db:show. The archive checksum was compared to the official MySQL download page before extraction. The consumed bootstrap SQL was deleted after setting the generated credentials.

PHP intl is enabled per command because the system php.ini has it disabled. In Laragon, enable extension=intl in your selected PHP configuration. PHP 8.2.12 is the available runtime; update to a current patch release before deployment. Node 22.23.2 is installed at %LOCALAPPDATA%/nvm/v22.23.2; set PATH in the project terminal without changing the machine-wide default.

Email uses MAIL_MAILER=log locally. Verification and reset URLs are sensitive and should remain private. demo:verify and demo:role are CLI-only fixture helpers restricted to local/testing; use staff:create for normal interactive staff provisioning. No default staff credentials are committed.

Troubleshooting: missing intl prevents Filament; enable it. Connection refused means start MySQL and verify port/user. Missing Vite manifest means npm run build; stale hot file after a stopped Vite process can cause asset errors. Database queue requires migrations and queue:work. Countdown reaches zero but settlement does not complete when schedule:work is absent; run trades:settle for recovery. Reverb failures are indicated by connection status; fallback refresh continues every 15 seconds. Keep the system clock accurate and use UTC server timestamps. If Windows restricted execution blocks file resolution or processes, run the project in a normal authorized terminal.
