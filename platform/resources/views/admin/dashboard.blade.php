@extends('layouts.admin')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
<div class="pb-5">
  <div class="row g-4 mb-4">
    <div class="col-12 col-xl-6">
      <div class="row align-items-center g-3">
        <div class="col-auto">
          <div class="d-flex align-items-center">
            <img src="{{ asset('images/logo.png') }}" alt="" width="48" class="rounded-2 me-3">
            <div>
              <h2 class="mb-0 text-body-emphasis">Dashboard da casa</h2>
              <p class="mb-0 text-body-tertiary">Visão geral da operação LESTBET 369</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
      <div class="px-card-stat bg-success-subtle">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="stat-label text-success-emphasis">Depósitos hoje</div>
            <div class="stat-value text-success-emphasis">$ {{ number_format($depositsTodayPaid, 2, '.', ',') }}</div>
            <div class="small text-success-emphasis opacity-75">Pagamentos confirmados</div>
          </div>
          <span class="fa-solid fa-circle-check fa-2x text-success opacity-50"></span>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="px-card-stat bg-warning-subtle">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="stat-label text-warning-emphasis">Saques pendentes</div>
            <div class="stat-value text-warning-emphasis">{{ $withdrawalsPending }}</div>
            <div class="small text-warning-emphasis opacity-75">$ {{ number_format($withdrawalsPendingAmount, 2, '.', ',') }}</div>
          </div>
          <span class="fa-solid fa-pause fa-2x text-warning opacity-50"></span>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="px-card-stat bg-danger-subtle">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="stat-label text-danger-emphasis">PIX pendentes</div>
            <div class="stat-value text-danger-emphasis">{{ $depositsPending }}</div>
            <div class="small text-danger-emphasis opacity-75">Aguardando confirmação</div>
          </div>
          <span class="fa-solid fa-xmark fa-2x text-danger opacity-50"></span>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-12 col-xl-8">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <div>
              <h3 class="mb-1 text-body-emphasis">Movimentação</h3>
              <p class="mb-0 text-body-tertiary">Depósitos pagos e GGR nos últimos 7 dias</p>
            </div>
            <span class="badge badge-phoenix badge-phoenix-primary">Últimos 7 dias</span>
          </div>
          <div class="chart-box">
            <canvas id="dashChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-4">
      <div class="row g-3">
        <div class="col-12 col-sm-6 col-xl-12">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="text-body-emphasis mb-1">Saldo da casa</h5>
              <p class="text-body-tertiary fs-9 mb-3">Ledger interno</p>
              <h2 class="text-body-emphasis mb-0">$ {{ number_format($houseBalance, 2, '.', ',') }}</h2>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-12">
          <div class="card h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between">
                <div>
                  <h5 class="text-body-emphasis mb-1">GGR 24h</h5>
                  <p class="text-body-tertiary fs-9 mb-2">Apostas − payouts</p>
                  <h3 class="mb-0 {{ $ggr >= 0 ? 'text-success' : 'text-danger' }}">$ {{ number_format($ggr, 2, '.', ',') }}</h3>
                </div>
                <div class="text-end">
                  <h5 class="text-body-emphasis mb-1">Ativos</h5>
                  <p class="text-body-tertiary fs-9 mb-2">24h / total</p>
                  <h3 class="mb-0">{{ $activePlayers }} <span class="fs-8 text-body-tertiary">/ {{ $totalPlayers }}</span></h3>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="text-body-emphasis mb-3">Resumo</h5>
              <div class="d-flex justify-content-between py-2 border-bottom border-translucent">
                <span class="text-body-tertiary">Comissões pendentes</span>
                <strong>$ {{ number_format($commissionsPending, 2, '.', ',') }}</strong>
              </div>
              <div class="d-flex justify-content-between py-2 border-bottom border-translucent">
                <span class="text-body-tertiary">Saldo real jogadores</span>
                <strong>$ {{ number_format($playerBalances, 2, '.', ',') }}</strong>
              </div>
              <div class="d-flex justify-content-between py-2 border-bottom border-translucent">
                <span class="text-body-tertiary">Saldo bônus (fora do caixa)</span>
                <strong>$ {{ number_format($playerBalancesBonus ?? 0, 2, '.', ',') }}</strong>
              </div>
              <div class="d-flex justify-content-between py-2">
                <span class="text-body-tertiary">Afiliados</span>
                <strong>{{ $totalAffiliates }}</strong>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-xl-7">
      <div class="card">
        <div class="card-header border-bottom border-translucent d-flex justify-content-between align-items-center">
          <h4 class="mb-0 text-body-emphasis">Últimos depósitos</h4>
          <a class="fw-bold fs-9" href="{{ route('admin.finance.index') }}">Ver financeiro</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive scrollbar">
            <table class="table table-sm fs-9 mb-0">
              <thead>
                <tr class="bg-body-secondary">
                  <th class="border-top border-translucent ps-3">ID</th>
                  <th class="border-top border-translucent">Jogador</th>
                  <th class="border-top border-translucent">Valor</th>
                  <th class="border-top border-translucent">Status</th>
                  <th class="border-top border-translucent pe-3">Quando</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($recentDeposits as $d)
                  <tr>
                    <td class="align-middle ps-3">#{{ $d->id }}</td>
                    <td class="align-middle">{{ $d->user?->email }}</td>
                    <td class="align-middle">$ {{ number_format((float) $d->amount, 2, '.', ',') }}</td>
                    <td class="align-middle"><span class="admin-badge {{ $d->isPaid() ? 'admin-badge--ok' : 'admin-badge--warn' }}">{{ $d->status }}</span></td>
                    <td class="align-middle pe-3">{{ $d->created_at?->format('d/m H:i') }}</td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="ps-3 py-4 text-body-tertiary">Nenhum depósito.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-5">
      <div class="card">
        <div class="card-header border-bottom border-translucent d-flex justify-content-between align-items-center">
          <h4 class="mb-0 text-body-emphasis">Solicitações de saque</h4>
          <a class="fw-bold fs-9" href="{{ route('admin.withdrawals.index') }}">Ver saques</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive scrollbar">
            <table class="table table-sm fs-9 mb-0">
              <thead>
                <tr class="bg-body-secondary">
                  <th class="border-top border-translucent ps-3">ID</th>
                  <th class="border-top border-translucent">Jogador</th>
                  <th class="border-top border-translucent">Valor</th>
                  <th class="border-top border-translucent pe-3">Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($recentWithdrawals as $w)
                  <tr>
                    <td class="align-middle ps-3">#{{ $w->id }}</td>
                    <td class="align-middle">{{ $w->user?->email }}</td>
                    <td class="align-middle">$ {{ number_format((float) $w->amount, 2, '.', ',') }}</td>
                    <td class="align-middle pe-3"><span class="admin-badge admin-badge--warn">{{ $w->status }}</span></td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="ps-3 py-4 text-body-tertiary">Nenhum saque.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  (function () {
    const el = document.getElementById('dashChart');
    if (!el || !window.Chart) return;
    const ctx = el.getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, 260);
    grad.addColorStop(0, 'rgba(56, 116, 255, 0.35)');
    grad.addColorStop(1, 'rgba(56, 116, 255, 0)');

    new Chart(ctx, {
      type: 'line',
      data: {
        labels: @json($chartLabels),
        datasets: [
          {
            label: 'Depósitos',
            data: @json($depositsSeries),
            borderColor: '#3874ff',
            backgroundColor: grad,
            borderWidth: 2,
            tension: 0.4,
            fill: true,
            pointRadius: 3,
            pointBackgroundColor: '#3874ff',
          },
          {
            label: 'GGR',
            data: @json($ggrSeries),
            borderColor: '#85a9ff',
            borderDash: [6, 4],
            backgroundColor: 'transparent',
            borderWidth: 2,
            tension: 0.4,
            fill: false,
            pointRadius: 3,
            pointBackgroundColor: '#85a9ff',
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: { color: 'rgba(255,255,255,0.65)' },
          },
        },
        scales: {
          x: {
            grid: { color: 'rgba(255,255,255,0.06)' },
            ticks: { color: 'rgba(255,255,255,0.55)' },
          },
          y: {
            grid: { color: 'rgba(255,255,255,0.06)' },
            ticks: { color: 'rgba(255,255,255,0.55)' },
          },
        },
      },
    });
  })();
</script>
@endpush
