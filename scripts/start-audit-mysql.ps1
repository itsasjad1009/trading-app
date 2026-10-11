$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
$taskServer = Join-Path $taskRoot '.runtime/mysql-8.4.11-winx64/bin/mysqld.exe'
$taskData = Join-Path $taskRoot '.runtime/mysql-audit-data'
if (Get-NetTCPConnection -LocalPort 3308 -State Listen -ErrorAction SilentlyContinue) {
    Write-Output 'Port 3308 is already listening. The audit suite independently validates its data directory.'
    exit
}
if (!(Test-Path -LiteralPath $taskData)) {
    New-Item -ItemType Directory -Path $taskData | Out-Null
    & $taskServer --no-defaults --initialize-insecure "--datadir=$taskData"
    if ($LASTEXITCODE -ne 0) { throw 'Audit database initialization failed' }
}
Start-Process -FilePath $taskServer -WindowStyle Hidden -ArgumentList @('--no-defaults', "--datadir=`"$taskData`"", '--port=3308', '--bind-address=127.0.0.1', '--mysqlx=OFF', '--console')
Write-Output 'Dedicated loopback audit MySQL started on port 3308. Never use this root-without-password instance for application data.'
