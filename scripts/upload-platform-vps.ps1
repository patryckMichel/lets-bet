# Upload Laravel platform -> VPS e dispara deploy
# Uso: cd C:\xampp\htdocs\Bet ; .\scripts\upload-platform-vps.ps1

param(
  [string]$VpsHost = "187.127.4.114",
  [string]$User = "root",
  [string]$LocalApp = "C:\xampp\htdocs\Bet\platform",
  [string]$RemoteApp = "/var/www/lestbet",
  [string]$Domain = "lestber369.com"
)

$ErrorActionPreference = "Stop"
$DeployScriptLocal = Join-Path $PSScriptRoot "vps-deploy-platform.sh"
$RemoteTmp = "/tmp/lestbet-upload"
$RemoteDeploy = "/tmp/vps-deploy-platform.sh"
$RemoteExtract = "/tmp/vps-extract-platform.sh"

if (-not (Test-Path $LocalApp)) { throw "App local nao encontrada: $LocalApp" }
if (-not (Test-Path $DeployScriptLocal)) { throw "Script deploy nao encontrado: $DeployScriptLocal" }

function Write-UnixFile {
  param([string]$Path, [string]$Content)
  $utf8NoBom = New-Object System.Text.UTF8Encoding $false
  $text = $Content -replace "`r`n", "`n" -replace "`r", "`n"
  [System.IO.File]::WriteAllText($Path, $text, $utf8NoBom)
}

function Invoke-Ssh {
  param([string]$Command)
  & ssh -o PreferredAuthentications=password -o PubkeyAuthentication=no -o StrictHostKeyChecking=accept-new "${User}@${VpsHost}" $Command
  if ($LASTEXITCODE -ne 0) { throw "SSH falhou (exit $LASTEXITCODE): $Command" }
}

function Invoke-Scp {
  param([string]$Local, [string]$Remote)
  & scp -o PreferredAuthentications=password -o PubkeyAuthentication=no -o StrictHostKeyChecking=accept-new $Local "${User}@${VpsHost}:${Remote}"
  if ($LASTEXITCODE -ne 0) { throw "SCP falhou (exit $LASTEXITCODE): $Local" }
}

Write-Host "==> [1/4] Empacotar platform"
$Staging = Join-Path $env:TEMP "lestbet-platform-deploy"
if (Test-Path $Staging) { Remove-Item $Staging -Recurse -Force }
New-Item -ItemType Directory -Path $Staging | Out-Null

$RootVendor = Join-Path $LocalApp "vendor"
$RootNodeModules = Join-Path $LocalApp "node_modules"
$RootGit = Join-Path $LocalApp ".git"
robocopy $LocalApp $Staging /E /XD $RootVendor $RootNodeModules $RootGit storage\framework\cache storage\framework\sessions storage\framework\views storage\logs bootstrap\cache /XF .env /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy falhou ($LASTEXITCODE)" }

if (Test-Path (Join-Path $LocalApp ".env")) {
  Copy-Item (Join-Path $LocalApp ".env") (Join-Path $Staging ".env.production") -Force
}

$dirs = @(
  "storage\app\public",
  "storage\framework\cache\data",
  "storage\framework\sessions",
  "storage\framework\views",
  "storage\logs",
  "bootstrap\cache"
)
foreach ($d in $dirs) {
  $p = Join-Path $Staging $d
  New-Item -ItemType Directory -Force -Path $p | Out-Null
  Set-Content -Path (Join-Path $p ".gitignore") -Value "*`n!.gitignore`n" -Encoding ASCII
}

$DeployLf = Join-Path $env:TEMP "vps-deploy-platform.sh"
Write-UnixFile -Path $DeployLf -Content ([IO.File]::ReadAllText($DeployScriptLocal))

$extractLines = @(
  "#!/usr/bin/env bash",
  "set -euo pipefail",
  "mkdir -p $RemoteApp",
  "if [ -f $RemoteApp/.env ]; then cp $RemoteApp/.env /tmp/lestbet.env.bak; fi",
  "find $RemoteApp -mindepth 1 -maxdepth 1 ! -name storage -exec rm -rf {} +",
  "mkdir -p $RemoteApp/storage/framework/{cache/data,sessions,views} $RemoteApp/storage/logs $RemoteApp/storage/app/public",
  "find $RemoteApp/storage -mindepth 1 -delete 2>/dev/null || true",
  "mkdir -p $RemoteApp/storage/framework/{cache/data,sessions,views} $RemoteApp/storage/logs $RemoteApp/storage/app/public",
  "tar -xzf $RemoteTmp/platform.tar.gz -C $RemoteApp",
  "if [ -f /tmp/lestbet.env.bak ]; then cp /tmp/lestbet.env.bak $RemoteApp/.env; fi",
  "if [ ! -f $RemoteApp/.env ] && [ -f $RemoteApp/.env.production ]; then cp $RemoteApp/.env.production $RemoteApp/.env; fi",
  "chmod +x $RemoteDeploy",
  "echo EXTRACT_OK"
)
$ExtractLf = Join-Path $env:TEMP "vps-extract-platform.sh"
Write-UnixFile -Path $ExtractLf -Content ($extractLines -join "`n")

$Tar = Join-Path $env:TEMP "lestbet-platform.tar.gz"
if (Test-Path $Tar) { Remove-Item $Tar -Force }
& tar -czf $Tar -C $Staging .
if ($LASTEXITCODE -ne 0) { throw "tar falhou ($LASTEXITCODE)" }
$tarMb = [math]::Round((Get-Item $Tar).Length / 1MB, 1)
Write-Host "Pacote: $Tar ($tarMb MB)"

Write-Host "==> [2/4] Enviar para ${User}@${VpsHost}"
Write-Host "Digite a SENHA root do VPS quando pedir."
Invoke-Ssh "rm -rf $RemoteTmp && mkdir -p $RemoteTmp"
Invoke-Scp $Tar "$RemoteTmp/platform.tar.gz"
Invoke-Scp $DeployLf $RemoteDeploy
Invoke-Scp $ExtractLf $RemoteExtract

Write-Host "==> [3/4] Extrair em $RemoteApp"
Invoke-Ssh "bash $RemoteExtract"

Write-Host "==> [4/4] Deploy remoto (apt + composer, aguarde)"
Invoke-Ssh "DOMAIN=$Domain APP_ROOT=$RemoteApp bash $RemoteDeploy"

Write-Host ""
Write-Host "Pronto."
Write-Host "IP:    http://$VpsHost"
Write-Host "Site:  http://$Domain"
Write-Host "Admin: http://$Domain/admin"
Write-Host "Login: patryck.michel@gmail.com / lestbet369"
