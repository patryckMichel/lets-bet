# Plataforma completa — Goldsvet na VPS

Prioridade agora: **jogos + admin**. Captura VIP fica para depois.

## O que sobe

- Site (jogar/login): https://lestber369.com
- Admin: https://lestber369.com/backend/login
- Logins padrao (trocar ja):
  - admin / password
  - agent / password
  - user1 / password

> Nota: `/back` e so pasta de assets (da 403). O painel real e `/backend`.

## Deploy (PowerShell no PC)

```powershell
cd C:\xampp\htdocs\Bet

$p = "scripts\vps-deploy-goldsvet.sh"
$t = [IO.File]::ReadAllText($p) -replace "`r`n","`n" -replace "`r","`n"
[IO.File]::WriteAllText($p, $t)

scp scripts\vps-deploy-goldsvet.sh root@187.127.4.114:/opt/bet/scripts/
ssh root@187.127.4.114 "bash /opt/bet/scripts/vps-deploy-goldsvet.sh"
```

Demora alguns minutos (apt + composer + clone + SQL).

No final deve aparecer `GOLDSVET DEPLOY DONE`.
Credenciais DB ficam em `/root/goldsvet-credentials.txt` na VPS.

## Limite importante dos jogos

O GitHub **nao inclui** os packs de slots/arcade.
Sem o pack do Telegram [@Supergoaladmi](https://t.me/Supergoaladmi):
- admin e telas do site abrem
- muitos jogos ficam vazios / nao carregam

## Depois do deploy

1. Abrir https://lestber369.com
2. Abrir https://lestber369.com/back com `admin` / `password`
3. Trocar a senha do admin
4. Se jogos nao aparecerem → pedir os game files no Telegram
