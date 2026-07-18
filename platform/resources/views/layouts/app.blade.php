<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Tigre Aviator — LESTBET 369')</title>
  <meta name="description" content="@yield('description', 'Tigre Aviator na LESTBET 369.')">
  <meta name="theme-color" content="#07040c">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="LESTBET 369">
  <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
  <link rel="icon" type="image/png" href="{{ asset('images/games/tigre-aviator-logo.png') }}">
  <link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,800&family=Manrope:wght@500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  @stack('head')
</head>
<body class="@yield('body_class', '')">
  <div id="pwa-install" class="pwa-install" hidden>
    <div class="pwa-install__card">
      <img class="pwa-install__icon" src="{{ asset('images/logo.png') }}" alt="" width="48" height="48">
      <div class="pwa-install__copy">
        <strong>Instalar LESTBET 369</strong>
        <span data-pwa-android>Adicione o atalho na tela inicial e abra como um app.</span>
        <span data-pwa-ios hidden>No Safari: toque em <em>Compartilhar</em> → <em>Adicionar à Tela de Início</em>.</span>
      </div>
      <div class="pwa-install__actions">
        <button type="button" class="pwa-install__btn" data-pwa-install>Instalar</button>
        <button type="button" class="pwa-install__close" data-pwa-close aria-label="Fechar">×</button>
      </div>
    </div>
  </div>

  <header class="topbar">
    <a class="topbar__brand" href="{{ route('home') }}">
      <img src="{{ asset('images/logo.png') }}" alt="LESTBET 369" width="36" height="36">
      <span>LESTBET 369</span>
    </a>

    <nav class="topbar__nav" aria-label="Menu principal">
      @auth
        <div class="topbar__balance">
          <span>Saldo</span>
          <strong id="av-balance">$ {{ number_format((float) auth()->user()->total_balance, 2, '.', ',') }}</strong>
        </div>
        <a class="topbar__btn topbar__btn--gold" href="{{ route('deposits.create') }}">+ Saldo</a>

        <details class="topbar__menu">
          <summary class="topbar__burger" aria-label="Abrir menu">
            <span></span>
            <span></span>
            <span></span>
          </summary>
          <div class="topbar__dropdown" role="menu">
            <a role="menuitem" href="{{ route('lobby') }}">Lobby</a>
            <a role="menuitem" href="{{ route('withdrawals.create') }}">Sacar</a>
            <a role="menuitem" href="{{ route('bonus.create') }}">Bônus</a>
            @if (auth()->user()->is_admin)
              <a role="menuitem" class="is-admin" href="{{ route('admin.dashboard') }}">Admin</a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit" role="menuitem">Sair</button>
            </form>
          </div>
        </details>
      @else
        <a class="topbar__btn topbar__btn--ghost" href="{{ route('login') }}">Entrar</a>
        <a class="topbar__btn topbar__btn--gold" href="{{ route('login') }}">Jogar</a>
      @endauth
    </nav>
  </header>

  <main>
    @yield('content')
  </main>

  <footer class="site-footer" id="legal">
    <div class="footer-social">
      <p class="footer-social__title">Bônus surpresa nas redes</p>
      <p class="footer-social__sub">Lançamos códigos e prêmios exclusivos primeiro no Instagram e no TikTok. Siga e não perca nenhum voo.</p>
      <div class="footer-social__links">
        <a href="https://www.instagram.com/lestbet369/" target="_blank" rel="noopener noreferrer" class="social-btn">
          <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="currentColor"><path d="M12 2.2c3.2 0 3.6 0 4.9.1 1.2.1 1.8.2 2.2.4.6.2 1 .5 1.4.9.4.4.7.8.9 1.4.2.4.4 1 .4 2.2.1 1.3.1 1.7.1 4.9s0 3.6-.1 4.9c-.1 1.2-.2 1.8-.4 2.2-.2.6-.5 1-.9 1.4-.4.4-.8.7-1.4.9-.4.2-1 .4-2.2.4-1.3.1-1.7.1-4.9.1s-3.6 0-4.9-.1c-1.2-.1-1.8-.2-2.2-.4-.6-.2-1-.5-1.4-.9-.4-.4-.7-.8-.9-1.4-.2-.4-.4-1-.4-2.2C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.9c.1-1.2.2-1.8.4-2.2.2-.6.5-1 .9-1.4.4-.4.8-.7 1.4-.9.4-.2 1-.4 2.2-.4C8.4 2.2 8.8 2.2 12 2.2m0 1.8c-3.1 0-3.5 0-4.8.1-1.1.1-1.5.2-1.9.3-.5.2-.8.4-1.1.7-.3.3-.5.6-.7 1.1-.1.4-.3.8-.3 1.9-.1 1.3-.1 1.7-.1 4.8s0 3.5.1 4.8c.1 1.1.2 1.5.3 1.9.2.5.4.8.7 1.1.3.3.6.5 1.1.7.4.1.8.3 1.9.3 1.3.1 1.7.1 4.8.1s3.5 0 4.8-.1c1.1-.1 1.5-.2 1.9-.3.5-.2.8-.4 1.1-.7.3-.3.5-.6.7-1.1.1-.4.3-.8.3-1.9.1-1.3.1-1.7.1-4.8s0-3.5-.1-4.8c-.1-1.1-.2-1.5-.3-1.9-.2-.5-.4-.8-.7-1.1-.3-.3-.6-.5-1.1-.7-.4-.1-.8-.3-1.9-.3-1.3-.1-1.7-.1-4.8-.1zm0 3.1a4.9 4.9 0 1 1 0 9.8 4.9 4.9 0 0 1 0-9.8zm0 8.1a3.2 3.2 0 1 0 0-6.4 3.2 3.2 0 0 0 0 6.4zm6.2-8.3a1.1 1.1 0 1 1-2.3 0 1.1 1.1 0 0 1 2.3 0z"/></svg>
          <span>@lestbet369</span>
        </a>
        <a href="https://www.tiktok.com/@lestbet_" target="_blank" rel="noopener noreferrer" class="social-btn">
          <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="currentColor"><path d="M19.6 6.9c-1.1-.7-1.9-1.9-2.1-3.2 0-.2-.1-.5-.1-.7h-3.5v13.4c0 1.6-1.3 2.9-2.9 2.9s-2.9-1.3-2.9-2.9 1.3-2.9 2.9-2.9c.3 0 .6 0 .9.1V10c-.3 0-.6-.1-.9-.1-3.5 0-6.4 2.9-6.4 6.4S7.5 22.8 11 22.8s6.4-2.9 6.4-6.4V9.7c1.3.9 2.9 1.5 4.6 1.5V7.7c-.9 0-1.7-.3-2.4-.8z"/></svg>
          <span>@lestbet_</span>
        </a>
      </div>
    </div>
    <p class="footer-legal">LESTBET 369 · Tigre Aviator · Jogue com responsabilidade · +18</p>
  </footer>

  @stack('scripts')
  <script src="{{ asset('js/pwa-install.js') }}" defer></script>
  <script>
    document.addEventListener('click', (e) => {
      document.querySelectorAll('details.topbar__menu[open]').forEach((menu) => {
        if (!menu.contains(e.target)) menu.removeAttribute('open');
      });
    });
  </script>
</body>
</html>
