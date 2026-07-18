(() => {
  const cfg = window.LESTBET_CONFIG || {};
  const year = document.getElementById("year");
  if (year) year.textContent = String(new Date().getFullYear());

  const setHref = (id, url) => {
    const el = document.getElementById(id);
    if (el && url) el.href = url;
  };

  setHref("link-instagram", cfg.instagram);
  setHref("btn-instagram", cfg.instagram);
  setHref("foot-ig", cfg.instagram);
  setHref("link-tiktok", cfg.tiktok);
  setHref("foot-tt", cfg.tiktok);

  const form = document.getElementById("lead-form");
  const status = document.getElementById("form-status");
  const whatsappInput = document.getElementById("whatsapp-input");
  const submitBtn = document.getElementById("submit-lead");
  const loginExisting = document.getElementById("login-existing");
  const digitsOnly = (value) => String(value || "").replace(/\D/g, "");

  const apiBase = () => {
    const host = window.location.hostname;
    const isLocal = host === "127.0.0.1" || host === "localhost";
    if (String(cfg.leadsApiUrl || "").trim()) {
      return String(cfg.leadsApiUrl).replace(/\/api\/leads\/?$/, "");
    }
    return isLocal ? "http://127.0.0.1:8089" : "";
  };

  const loginUrlFor = (whatsapp) => {
    const base = String(cfg.loginUrl || "/").trim() || "/";
    const join = base.includes("?") ? "&" : "?";
    return `${base}${join}whatsapp=${encodeURIComponent(whatsapp)}`;
  };

  const showExisting = (whatsapp) => {
    if (status) {
      status.textContent = "Este WhatsApp já está cadastrado. Faça login na plataforma.";
    }
    if (submitBtn) submitBtn.classList.add("is-hidden");
    if (loginExisting) {
      loginExisting.href = loginUrlFor(whatsapp);
      loginExisting.classList.remove("is-hidden");
    }
  };

  const showNew = () => {
    if (submitBtn) submitBtn.classList.remove("is-hidden");
    if (loginExisting) loginExisting.classList.add("is-hidden");
  };

  const checkPhone = async (whatsapp) => {
    const res = await fetch(
      `${apiBase()}/api/leads/check?whatsapp=${encodeURIComponent(whatsapp)}`
    );
    const data = await res.json().catch(() => null);
    if (!res.ok || !data?.ok) {
      throw new Error(data?.error || "Não foi possível validar o telefone.");
    }
    return Boolean(data.exists);
  };

  let checkTimer = null;
  whatsappInput?.addEventListener("input", () => {
    showNew();
    if (status) status.textContent = "";
    const whatsapp = digitsOnly(whatsappInput.value);
    if (checkTimer) window.clearTimeout(checkTimer);
    if (whatsapp.length < 10) return;
    checkTimer = window.setTimeout(async () => {
      try {
        const exists = await checkPhone(whatsapp);
        if (exists) showExisting(whatsapp);
      } catch (_) {
        // silencioso no blur/digitação; submit valida de novo
      }
    }, 450);
  });

  whatsappInput?.addEventListener("blur", async () => {
    const whatsapp = digitsOnly(whatsappInput.value);
    if (whatsapp.length < 10) return;
    try {
      const exists = await checkPhone(whatsapp);
      if (exists) showExisting(whatsapp);
    } catch (_) {}
  });

  form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const data = new FormData(form);
    const name = String(data.get("name") || "").trim();
    const whatsapp = digitsOnly(data.get("whatsapp"));
    const instagram = String(data.get("instagram") || "").trim();

    if (!name || whatsapp.length < 10) {
      if (status) status.textContent = "Preencha nome e WhatsApp válidos.";
      return;
    }

    if (submitBtn) submitBtn.disabled = true;
    if (status) status.textContent = "Validando telefone...";

    try {
      const exists = await checkPhone(whatsapp);
      if (exists) {
        showExisting(whatsapp);
        return;
      }

      if (status) status.textContent = "Salvando sua vaga...";
      const response = await fetch(`${apiBase()}/api/leads`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          name,
          whatsapp,
          instagram,
          source: "acessovip",
          userAgent: navigator.userAgent,
        }),
      });
      const result = await response.json().catch(() => null);

      if (response.status === 409 || result?.code === "PHONE_EXISTS" || result?.exists) {
        showExisting(whatsapp);
        return;
      }

      if (!response.ok || !result?.ok) {
        throw new Error(result?.error || "Falha ao salvar o cadastro.");
      }

      if (status) status.textContent = "Cadastro ok! Abrindo login da plataforma...";
      window.setTimeout(() => {
        window.location.assign(loginUrlFor(whatsapp));
      }, 600);
    } catch (err) {
      if (status) status.textContent = err.message || "Erro ao enviar. Tente de novo.";
      showNew();
    } finally {
      if (submitBtn) submitBtn.disabled = false;
    }
  });
})();
