$ErrorActionPreference='Stop'
$taskRoot=Split-Path $PSScriptRoot -Parent
Set-Location -LiteralPath $taskRoot
php -d extension=intl artisan serve --host=127.0.0.1 --port=8000
