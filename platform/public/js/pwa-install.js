(function () {
  const DISMISS_KEY = 'lestbet_pwa_dismiss_until';
  const banner = document.getElementById('pwa-install');
  if (!banner) return;

  const btnInstall = banner.querySelector('[data-pwa-install]');
  const btnClose = banner.querySelector('[data-pwa-close]');
  const iosHint = banner.querySelector('[data-pwa-ios]');
  const androidActions = banner.querySelector('[data-pwa-android]');

  let deferredPrompt = null;

  function dismissed() {
    try {
      const until = Number(localStorage.getItem(DISMISS_KEY) || 0);
      return Date.now() < until;
    } catch (_) {
      return false;
    }
  }

  function dismiss(days) {
    try {
      localStorage.setItem(DISMISS_KEY, String(Date.now() + days * 86400000));
    } catch (_) {}
    banner.hidden = true;
    banner.classList.remove('is-visible');
  }

  function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
      || window.navigator.standalone === true;
  }

  function isMobile() {
    return window.matchMedia('(max-width: 900px)').matches
      || /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
  }

  function isIos() {
    return /iPhone|iPad|iPod/i.test(navigator.userAgent)
      || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  }

  function show() {
    if (!isMobile() || isStandalone() || dismissed()) return;
    banner.hidden = false;
    requestAnimationFrame(() => banner.classList.add('is-visible'));
  }

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
  }

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    if (androidActions) androidActions.hidden = false;
    if (iosHint) iosHint.hidden = true;
    show();
  });

  window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    dismiss(90);
  });

  // iOS Safari: no beforeinstallprompt — show manual tip.
  if (isIos() && !isStandalone() && isMobile() && !dismissed()) {
    if (androidActions) androidActions.hidden = true;
    if (iosHint) iosHint.hidden = false;
    if (btnInstall) btnInstall.hidden = true;
    setTimeout(show, 1200);
  }

  btnInstall?.addEventListener('click', async () => {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    try {
      await deferredPrompt.userChoice;
    } catch (_) {}
    deferredPrompt = null;
    dismiss(30);
  });

  btnClose?.addEventListener('click', () => dismiss(14));
})();
