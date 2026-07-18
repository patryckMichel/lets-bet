<!DOCTYPE html>
<html lang="pt-BR" dir="ltr" data-navigation-type="vertical" data-navbar-horizontal-shape="default" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Admin') — LESTBET 369</title>
  <link rel="icon" type="image/png" href="{{ asset('images/games/tigre-aviator-logo.png') }}">

  <script>
    localStorage.setItem('phoenixTheme', 'dark');
    localStorage.setItem('phoenixNavbarPosition', 'vertical');
    document.documentElement.setAttribute('data-bs-theme', 'dark');
  </script>
  <script src="{{ asset('vendor/phoenix/vendors/simplebar/simplebar.min.js') }}"></script>
  <script src="{{ asset('vendor/phoenix/assets/js/config.js') }}"></script>

  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:opsz,wght@6..12,300;6..12,400;6..12,600;6..12,700;6..12,800;6..12,900&display=swap" rel="stylesheet">
  <link href="{{ asset('vendor/phoenix/vendors/simplebar/simplebar.min.css') }}" rel="stylesheet">
  <link href="https://unicons.iconscout.com/release/v4.0.8/css/line.css" rel="stylesheet">
  <link href="{{ asset('vendor/phoenix/assets/css/theme.min.css') }}" rel="stylesheet">
  <link href="{{ asset('vendor/phoenix/assets/css/user.min.css') }}" rel="stylesheet">
  <link href="{{ asset('css/admin-phoenix.css') }}" rel="stylesheet">
  @stack('styles')
</head>
<body>
  <main class="main" id="top">
    <nav class="navbar navbar-vertical navbar-expand-lg">
      <div class="collapse navbar-collapse" id="navbarVerticalCollapse">
        <div class="navbar-vertical-content">
          <ul class="navbar-nav flex-column" id="navbarVerticalNav">
            <li class="nav-item">
              <p class="navbar-vertical-label">Operação</p>
              <hr class="navbar-vertical-line">
            </li>
            <li class="nav-item">
              <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                <div class="d-flex align-items-center">
                  <span class="nav-link-icon"><span data-feather="pie-chart"></span></span>
                  <span class="nav-link-text">Dashboard</span>
                </div>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link {{ request()->routeIs('admin.games.*') ? 'active' : '' }}" href="{{ route('admin.games.index') }}">
                <div class="d-flex align-items-center">
                  <span class="nav-link-icon"><span data-feather="grid"></span></span>
                  <span class="nav-link-text">Jogos</span>
                </div>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link {{ request()->routeIs('admin.players.*') ? 'active' : '' }}" href="{{ route('admin.players.index') }}">
                <div class="d-flex align-items-center">
                  <span class="nav-link-icon"><span data-feather="users"></span></span>
                  <span class="nav-link-text">Jogadores</span>
                </div>
              </a>
            </li>

            <li class="nav-item">
              <p class="navbar-vertical-label">Crescimento</p>
              <hr class="navbar-vertical-line">
            </li>
            <li class="nav-item">
              <a class="nav-link {{ request()->routeIs('admin.affiliates.*') ? 'active' : '' }}" href="{{ route('admin.affiliates.index') }}">
                <div class="d-flex align-items-center">
                  <span class="nav-link-icon"><span data-feather="share-2"></span></span>
                  <span class="nav-link-text">Afiliados</span>
                </div>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link {{ request()->routeIs('admin.bonus-codes.*') ? 'active' : '' }}" href="{{ route('admin.bonus-codes.index') }}">
                <div class="d-flex align-items-center">
                  <span class="nav-link-icon"><span data-feather="gift"></span></span>
                  <span class="nav-link-text">Códigos</span>
                </div>
              </a>
            </li>

            <li class="nav-item">
              <p class="navbar-vertical-label">Financeiro</p>
              <hr class="navbar-vertical-line">
            </li>
            <li class="nav-item">
              <a class="nav-link {{ request()->routeIs('admin.finance.*') ? 'active' : '' }}" href="{{ route('admin.finance.index') }}">
                <div class="d-flex align-items-center">
                  <span class="nav-link-icon"><span data-feather="dollar-sign"></span></span>
                  <span class="nav-link-text">Ledger</span>
                </div>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link {{ request()->routeIs('admin.withdrawals.*') ? 'active' : '' }}" href="{{ route('admin.withdrawals.index') }}">
                <div class="d-flex align-items-center">
                  <span class="nav-link-icon"><span data-feather="credit-card"></span></span>
                  <span class="nav-link-text">Saques</span>
                </div>
              </a>
            </li>

            <li class="nav-item">
              <p class="navbar-vertical-label">Sistema</p>
              <hr class="navbar-vertical-line">
            </li>
            <li class="nav-item">
              <a class="nav-link {{ request()->routeIs('admin.ops.*') ? 'active' : '' }}" href="{{ route('admin.ops.index') }}">
                <div class="d-flex align-items-center">
                  <span class="nav-link-icon"><span data-feather="activity"></span></span>
                  <span class="nav-link-text">Métricas</span>
                </div>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.edit') }}">
                <div class="d-flex align-items-center">
                  <span class="nav-link-icon"><span data-feather="settings"></span></span>
                  <span class="nav-link-text">Configs</span>
                </div>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link {{ request()->routeIs('admin.logs.*') ? 'active' : '' }}" href="{{ route('admin.logs.index') }}">
                <div class="d-flex align-items-center">
                  <span class="nav-link-icon"><span data-feather="file-text"></span></span>
                  <span class="nav-link-text">Logs</span>
                </div>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="{{ route('lobby') }}">
                <div class="d-flex align-items-center">
                  <span class="nav-link-icon"><span data-feather="external-link"></span></span>
                  <span class="nav-link-text">Ir ao lobby</span>
                </div>
              </a>
            </li>
          </ul>
        </div>
      </div>
      <div class="navbar-vertical-footer">
        <button class="btn navbar-vertical-toggle border-0 fw-semibold w-100 white-space-nowrap d-flex align-items-center">
          <span class="uil uil-left-arrow-to-left fs-8"></span>
          <span class="uil uil-arrow-from-right fs-8"></span>
          <span class="navbar-vertical-footer-text ms-2">Recolher</span>
        </button>
      </div>
    </nav>

    <nav class="navbar navbar-top fixed-top navbar-expand" id="navbarDefault">
      <div class="collapse navbar-collapse justify-content-between">
        <div class="navbar-logo">
          <button class="btn navbar-toggler navbar-toggler-humburger-icon hover-bg-transparent" type="button" data-bs-toggle="collapse" data-bs-target="#navbarVerticalCollapse" aria-controls="navbarVerticalCollapse" aria-expanded="false" aria-label="Toggle Navigation">
            <span class="navbar-toggle-icon"><span class="toggle-line"></span></span>
          </button>
          <a class="navbar-brand me-1 me-sm-3" href="{{ route('admin.dashboard') }}">
            <div class="d-flex align-items-center">
              <div class="d-flex align-items-center">
                <img src="{{ asset('images/logo.png') }}" alt="LESTBET" width="32" />
                <p class="logo-text ms-2 d-none d-sm-block">LESTBET 369</p>
              </div>
            </div>
          </a>
        </div>

        <ul class="navbar-nav navbar-nav-icons flex-row">
          <li class="nav-item d-none d-md-block">
            <span class="nav-link text-body-tertiary small">@yield('heading', 'Admin')</span>
          </li>
          <li class="nav-item">
            <div class="dropdown">
              <a class="d-flex align-items-center px-2 text-decoration-none" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <div class="avatar avatar-l">
                  <span class="avatar-name rounded-circle bg-primary-subtle text-primary">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}</span>
                </div>
              </a>
              <div class="dropdown-menu dropdown-menu-end navbar-dropdown-caret py-0 dropdown-profile shadow border">
                <div class="card position-relative border-0">
                  <div class="card-body p-0">
                    <div class="text-center pt-4 pb-3">
                      <h6 class="mt-2 text-body-emphasis">{{ auth()->user()->name }}</h6>
                      <p class="text-body-tertiary small mb-0">{{ auth()->user()->email }}</p>
                    </div>
                  </div>
                  <div class="overflow-auto scrollbar" style="height: 8rem;">
                    <ul class="nav d-flex flex-column mb-2 pb-1">
                      <li class="nav-item"><a class="nav-link px-3" href="{{ route('lobby') }}"><span class="me-2 text-body" data-feather="home"></span><span>Lobby</span></a></li>
                      <li class="nav-item"><a class="nav-link px-3" href="{{ route('admin.settings.edit') }}"><span class="me-2 text-body" data-feather="settings"></span><span>Configs</span></a></li>
                    </ul>
                  </div>
                  <div class="card-footer p-0 border-top border-translucent">
                    <div class="px-3 py-2">
                      <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-phoenix-secondary d-flex flex-center w-100" type="submit">
                          <span class="me-2" data-feather="log-out"></span>Sair
                        </button>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </nav>

    <div class="content">
      @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          {{ session('status') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      @endif
      @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          {{ $errors->first() }}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      @endif

      @yield('content')

      <footer class="footer position-absolute">
        <div class="row g-0 justify-content-between align-items-center h-100">
          <div class="col-12 col-sm-auto text-center">
            <p class="mb-0 mt-2 mt-sm-0 text-body-tertiary">LESTBET 369 Admin <span class="d-none d-sm-inline-block"></span><span class="d-none d-sm-inline-block mx-1">|</span><br class="d-sm-none" />{{ date('Y') }}</p>
          </div>
          <div class="col-12 col-sm-auto text-center">
            <p class="mb-0 text-body-tertiary text-opacity-85">Phoenix UI · dark</p>
          </div>
        </div>
      </footer>
    </div>
  </main>

  <script src="{{ asset('vendor/phoenix/vendors/popper/popper.min.js') }}"></script>
  <script src="{{ asset('vendor/phoenix/vendors/bootstrap/bootstrap.min.js') }}"></script>
  <script src="{{ asset('vendor/phoenix/vendors/anchorjs/anchor.min.js') }}"></script>
  <script src="{{ asset('vendor/phoenix/vendors/is/is.min.js') }}"></script>
  <script src="{{ asset('vendor/phoenix/vendors/fontawesome/all.min.js') }}"></script>
  <script src="{{ asset('vendor/phoenix/vendors/lodash/lodash.min.js') }}"></script>
  @if (file_exists(public_path('vendor/phoenix/vendors/list.js/list.min.js')))
    <script src="{{ asset('vendor/phoenix/vendors/list.js/list.min.js') }}"></script>
  @endif
  <script src="{{ asset('vendor/phoenix/vendors/feather-icons/feather.min.js') }}"></script>
  <script src="{{ asset('vendor/phoenix/vendors/dayjs/dayjs.min.js') }}"></script>
  <script src="{{ asset('vendor/phoenix/assets/js/phoenix.js') }}"></script>
  <script>if (window.feather) feather.replace({ width: 16, height: 16 });</script>
  @stack('scripts')
</body>
</html>
