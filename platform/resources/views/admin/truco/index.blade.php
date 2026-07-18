@extends('layouts.admin')

@section('title', 'Truco')
@section('heading', 'Truco')

@section('content')
<div class="mb-4">
  <h2 class="text-body-emphasis mb-1">Métricas — Tigre do Truco</h2>
  <p class="text-body-tertiary mb-0">House edge configurável em <a href="{{ route('admin.settings.edit') }}">Configs</a>.</p>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card"><div class="card-body">
      <div class="small text-body-tertiary">Partidas finalizadas</div>
      <div class="fs-4 fw-bold">{{ $total }}</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card"><div class="card-body">
      <div class="small text-body-tertiary">Em jogo / sala</div>
      <div class="fs-4 fw-bold">{{ $playing }} / {{ $waiting }}</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card"><div class="card-body">
      <div class="small text-body-tertiary">Volume (stakes)</div>
      <div class="fs-4 fw-bold">$ {{ number_format($volume, 2, '.', ',') }}</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card"><div class="card-body">
      <div class="small text-body-tertiary">P&amp;L casa (aprox.)</div>
      <div class="fs-4 fw-bold {{ $housePnL >= 0 ? 'text-success' : 'text-danger' }}">
        $ {{ number_format($housePnL, 2, '.', ',') }}
      </div>
    </div></div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body">
        <h5>House edge</h5>
        <p class="mb-1">Configurado: <strong>{{ number_format($edge * 100, 1, ',', '.') }}%</strong></p>
        <p class="mb-1">Winrate teórico da casa: <strong>{{ number_format($theoreticalWinrateHouse * 100, 1, ',', '.') }}%</strong></p>
        <p class="mb-0">Winrate real (alvo them): <strong>{{ number_format($actualHouseWinrate * 100, 1, ',', '.') }}%</strong>
          <span class="text-body-tertiary small">({{ $winsThem }} / {{ $total }})</span>
        </p>
        <p class="small text-body-tertiary mt-2 mb-0">Vitórias humanas (alvo us): {{ $winsUs }}</p>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body">
        <h5>Por stake</h5>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Stake</th><th>Partidas</th></tr></thead>
            <tbody>
              @forelse ($byStake as $row)
                <tr>
                  <td>$ {{ number_format((float) $row->stake, 2, '.', ',') }}</td>
                  <td>{{ $row->c }}</td>
                </tr>
              @empty
                <tr><td colspan="2" class="text-body-tertiary">Sem dados ainda.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
