/**
 * 1) Abra a planilha → Extensões → Apps Script
 * 2) Apague o código padrão e cole este arquivo inteiro
 * 3) Salve → Implantar → Nova implantação → Tipo: App da Web
 *    - Executar como: Eu
 *    - Quem tem acesso: Qualquer pessoa
 * 4) Copie a URL (.../exec) e cole em landing/config.js → sheetWebhook
 */

var SPREADSHEET_ID = "1ADxns34ueecZTReyjkJca0bikg5_tug0kDGJbo3ikMc";
var SHEET_NAME = "Leads";

function doPost(e) {
  try {
    var data = {};
    if (e.postData && e.postData.contents) {
      data = JSON.parse(e.postData.contents);
    } else if (e.parameter) {
      data = e.parameter;
    }

    var ss = SpreadsheetApp.openById(SPREADSHEET_ID);
    var sheet = ss.getSheetByName(SHEET_NAME);
    if (!sheet) {
      sheet = ss.insertSheet(SHEET_NAME);
      sheet.appendRow([
        "Data/Hora",
        "Nome",
        "WhatsApp",
        "Instagram",
        "Origem",
        "User Agent",
      ]);
      sheet.setFrozenRows(1);
    }

    if (sheet.getLastRow() === 0) {
      sheet.appendRow([
        "Data/Hora",
        "Nome",
        "WhatsApp",
        "Instagram",
        "Origem",
        "User Agent",
      ]);
      sheet.setFrozenRows(1);
    }

    sheet.appendRow([
      new Date(),
      String(data.name || "").trim(),
      String(data.whatsapp || "").trim(),
      String(data.instagram || "").trim(),
      String(data.source || "landing").trim(),
      String(data.userAgent || "").trim(),
    ]);

    return json_({ ok: true });
  } catch (err) {
    return json_({ ok: false, error: String(err) });
  }
}

function doGet() {
  return json_({
    ok: true,
    message: "LESTBET 369 lead webhook ativo. Use POST JSON.",
  });
}

function json_(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(
    ContentService.MimeType.JSON
  );
}
