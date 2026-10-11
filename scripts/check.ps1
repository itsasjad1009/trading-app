$ErrorActionPreference='Stop'
$taskRoot=Split-Path $PSScriptRoot -Parent
$taskNode22=Join-Path $env:LOCALAPPDATA 'nvm/v22.23.2'
if(Test-Path -LiteralPath (Join-Path $taskNode22 'node.exe')) { $env:Path=$taskNode22+';'+$env:Path }
Set-Location -LiteralPath $taskRoot
php -d extension=intl artisan test --compact
if($LASTEXITCODE -ne 0){exit $LASTEXITCODE}
npm test
if($LASTEXITCODE -ne 0){exit $LASTEXITCODE}
npm run build
if($LASTEXITCODE -ne 0){exit $LASTEXITCODE}
npx playwright test
exit $LASTEXITCODE
