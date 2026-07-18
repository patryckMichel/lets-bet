# Conectar landing → Google Sheets

Planilha: https://docs.google.com/spreadsheets/d/1ADxns34ueecZTReyjkJca0bikg5_tug0kDGJbo3ikMc/edit

## Passos (2 minutos)

1. Abra a planilha logado na conta Google dona dela.
2. Menu **Extensões → Apps Script**.
3. Apague o código padrão e cole o conteúdo de `google-apps-script/Code.gs`.
4. Clique em **Salvar** (nome: `LESTBET Leads`).
5. **Implantar → Nova implantação**:
   - Tipo: **App da Web**
   - Executar como: **Eu**
   - Quem tem acesso: **Qualquer pessoa**
6. **Implantar** e autorize o Google quando pedir.
7. Copie a URL que termina com `/exec`.
8. Cole em `landing/config.js`:

```js
sheetWebhook: "https://script.google.com/macros/s/XXXX/exec",
```

9. Teste o formulário na landing. A aba **Leads** será criada automaticamente.

## Observação de segurança

A URL `/exec` permite que qualquer pessoa com o link envie leads (normal para landing). Não coloque dados sensíveis na planilha além de contato de marketing.
