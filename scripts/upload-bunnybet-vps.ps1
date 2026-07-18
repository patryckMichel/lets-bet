# Upload BunnyBet local -> VPS e dispara deploy (wipe Goldsvet + sobe BunnyBet)
# Uso:
#   .\scripts\upload-bunnybet-vps.ps1
#   .\scripts\upload-bunnybet-vps.ps1 -Host 187.127.4.114 -User root

param(
  [string]$VpsHost = "187.127.4.114",
  [string]$User = "root",
  [string]$LocalApp = "C:\xampp\htdocs\casino-laravel",
  [string]$RemoteApp = "/var/www/bunnybet",
  [string]$Domain = "lestber369.com"
)

$ErrorActionPreference = "Stop"
$DeployScriptLocal = Join-Path $PSScriptRoot "vps-deploy-bunnybet.sh"
$RemoteTmp = "/tmp/bunnybet-upload"
$RemoteDeploy = "/tmp/vps-deploy-bunnybet.sh"

if (-not (Test-Path $LocalApp)) { throw "App local nao encontrada: $LocalApp" }
if (-not (Test-Path $DeployScriptLocal)) { throw "Script deploy nao encontrado: $DeployScriptLocal" }

Write-Host "==> [1/4] Empacotar codigo (sem node_modules)"
$Staging = Join-Path $env:TEMP "bunnybet-deploy"
if (Test-Path $Staging) { Remove-Item $Staging -Recurse -Force }
New-Item -ItemType Directory -Path $Staging | Out-Null

robocopy $LocalApp $Staging /E /XD node_modules .git client\node_modules client\build /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy falhou ($LASTEXITCODE)" }

$Zip = Join-Path $env:TEMP "bunnybet-deploy.zip"
if (Test-Path $Zip) { Remove-Item $Zip -Force }
Compress-Archive -Path (Join-Path $Staging '*') -DestinationPath $Zip -Force
Write-Host "Pacote: $Zip ($([math]::Round((Get-Item $Zip).Length/1MB,1)) MB)"

Write-Host "==> [2/4] Enviar para ${User}@${VpsHost} (vai pedir senha/passphrase)"
ssh "${User}@${VpsHost}" "rm -rf ${RemoteTmp} && mkdir -p ${RemoteTmp}"
scp $Zip "${User}@${VpsHost}:${RemoteTmp}/bunnybet.zip"
scp $DeployScriptLocal "${User}@${VpsHost}:${RemoteDeploy}"

Write-Host "==> [3/4] Extrair em ${RemoteApp}"
ssh "${User}@${VpsHost}" @"
set -e
apt-get install -y unzip >/dev/null 2>&1 || true
rm -rf ${RemoteApp}
mkdir -p ${RemoteApp}
unzip -qo ${RemoteTmp}/bunnybet.zip -d ${RemoteApp}
chmod +x ${RemoteDeploy}
"@

Write-Host "==> [4/4] Rodar deploy (APAGA Goldsvet + sobe BunnyBet)"
Write-Host "Confirme mentalmente: vai remover /var/www/goldsvet e o banco goldsvet."
ssh "${User}@${VpsHost}" "DOMAIN=${Domain} APP_ROOT=${RemoteApp} WIPE_OLD=1 bash ${RemoteDeploy}"

Write-Host ""
Write-Host "Pronto. Abra https://${Domain}"
Write-Host "Login: demo@lestbet.local / test123"
