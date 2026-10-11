$ErrorActionPreference='Stop'
$taskRoot=Split-Path $PSScriptRoot -Parent
$taskMySqlBase=Join-Path $taskRoot '.runtime/mysql-8.4.11-winx64'
$taskMySqlExe=Join-Path $taskMySqlBase 'bin/mysqld.exe'
$taskConfig=Join-Path $taskMySqlBase 'my.ini'
if (!(Test-Path -LiteralPath $taskMySqlExe)) { throw 'Project-local MySQL is not installed. Configure MySQL 8 in Laragon and edit .env.' }
if (Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue) { Write-Host 'Port 3307 already has a listener. Verify with php artisan db:show.'; exit }
$taskProcess=Start-Process -FilePath $taskMySqlExe -ArgumentList ('--defaults-file="'+$taskConfig+'"') -WindowStyle Hidden -PassThru
$taskProcess.Id | Set-Content (Join-Path $taskRoot '.runtime/mysql.pid')
Write-Host 'MySQL 8 starting on 127.0.0.1:3307.'
