# Upload only Phoenix public assets to the VPS.
# Usage: cd C:\xampp\htdocs\Bet ; .\scripts\upload-phoenix-assets.ps1

param(
  [string]$VpsHost = "187.127.4.114",
  [string]$User = "root",
  [string]$LocalPublic = "C:\xampp\htdocs\Bet\platform\public",
  [string]$RemotePublic = "/var/www/lestbet/public"
)

$ErrorActionPreference = "Stop"
$PhoenixDir = Join-Path $LocalPublic "vendor\phoenix"
$CustomCss = Join-Path $LocalPublic "css\admin-phoenix.css"

if (-not (Test-Path $PhoenixDir)) { throw "Phoenix assets not found: $PhoenixDir" }
if (-not (Test-Path $CustomCss)) { throw "Admin CSS not found: $CustomCss" }

function Invoke-Ssh {
  param([string]$Command)
  & ssh -o PreferredAuthentications=password -o PubkeyAuthentication=no -o StrictHostKeyChecking=accept-new "${User}@${VpsHost}" $Command
  if ($LASTEXITCODE -ne 0) { throw "SSH failed (exit $LASTEXITCODE)" }
}

function Invoke-Scp {
  param([string]$Local, [string]$Remote)
  & scp -o PreferredAuthentications=password -o PubkeyAuthentication=no -o StrictHostKeyChecking=accept-new $Local "${User}@${VpsHost}:${Remote}"
  if ($LASTEXITCODE -ne 0) { throw "SCP failed (exit $LASTEXITCODE)" }
}

$Stage = Join-Path $env:TEMP "lestbet-phoenix-assets"
if (Test-Path $Stage) { Remove-Item $Stage -Recurse -Force }
New-Item -ItemType Directory -Force -Path (Join-Path $Stage "vendor") | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $Stage "css") | Out-Null
Copy-Item $PhoenixDir (Join-Path $Stage "vendor\phoenix") -Recurse -Force
Copy-Item $CustomCss (Join-Path $Stage "css\admin-phoenix.css") -Force

$Archive = Join-Path $env:TEMP "lestbet-phoenix-assets.tar.gz"
if (Test-Path $Archive) { Remove-Item $Archive -Force }
& tar -czf $Archive -C $Stage .
if ($LASTEXITCODE -ne 0) { throw "tar failed (exit $LASTEXITCODE)" }

Write-Host "Uploading Phoenix assets..."
Invoke-Ssh "mkdir -p /tmp/lestbet-assets $RemotePublic"
Invoke-Scp $Archive "/tmp/lestbet-assets/phoenix.tar.gz"
Invoke-Ssh "tar -xzf /tmp/lestbet-assets/phoenix.tar.gz -C $RemotePublic && chown -R www-data:www-data $RemotePublic/vendor/phoenix $RemotePublic/css/admin-phoenix.css && test -f $RemotePublic/vendor/phoenix/assets/css/theme.min.css && echo ASSETS_OK"

Write-Host "Done. Refresh the admin with Ctrl+F5."
