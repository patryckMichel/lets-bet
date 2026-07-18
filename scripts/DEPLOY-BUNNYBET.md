# Deploy BunnyBet na VPS (substitui Goldsvet)

## O que o script faz

1. Para PM2 / remove stack Goldsvet (`/var/www/goldsvet`) e landing antiga
2. Apaga banco `goldsvet`
3. Sobe BunnyBet em `/var/www/bunnybet`
4. Cria banco `bunnybet` + usuario demo
5. Build React + PM2 + Nginx + (tentativa) HTTPS

**Dominio:** https://lestber369.com  
**Login demo:** `demo@lestbet.local` / `test123`

## Como rodar (do seu PC)

No PowerShell (vai pedir senha SSH / passphrase):

```powershell
cd C:\xampp\htdocs\Bet
.\scripts\upload-bunnybet-vps.ps1
```

Parametros opcionais:

```powershell
.\scripts\upload-bunnybet-vps.ps1 -VpsHost 187.127.4.114 -User root -Domain lestber369.com
```

## Aviso

Isso **apaga** o Goldsvet no servidor. Nao tem rollback automatico.

## So redeploy (sem wipe)

No VPS, apos ja ter BunnyBet:

```bash
WIPE_OLD=0 APP_ROOT=/var/www/bunnybet bash /tmp/vps-deploy-bunnybet.sh
```

(ou rode o upload de novo — por padrao wipe=1)
