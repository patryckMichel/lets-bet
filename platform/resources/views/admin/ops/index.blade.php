@extends('layouts.admin')

@section('title', 'Métricas Ops')
@section('heading', 'Métricas Ops')

@section('content')
<div class="pb-5">
  <div class="mb-4">
    <h2 class="mb-1 text-body-emphasis">Operação do jogo</h2>
    <p class="mb-0 text-body-tertiary">Sessões, horários, apostas e GGR — base para regras dinâmicas no futuro.</p>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card"><div class="card-body">
        <div class="text-body-tertiary small">Online agora</div>
        <div class="fs-4 fw-bold">{{ $onlineNow }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card"><div class="card-body">
        <div class="text-body-tertiary small">Pico hoje</div>
        <div class="fs-4 fw-bold">{{ $today['online_peak'] }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card"><div class="card-body">
        <div class="text-body-tertiary small">Logins hoje</div>
        <div class="fs-4 fw-bold">{{ $today['logins'] }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card"><div class="card-body">
        <div class="text-body-tertiary small">Jogadores vistos hoje</div>
        <div class="fs-4 fw-bold">{{ $today['unique_players'] }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card"><div class="card-body">
        <div class="text-body-tertiary small">Apostas hoje</div>
        <div class="fs-4 fw-bold">{{ $today['bets_count'] }}</div>
        <div class="small text-body-tertiary">$ {{ number_format($today['bets_amount'], 2, '.', ',') }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card"><div class="card-body">
        <div class="text-body-tertiary small">Cashouts hoje</div>
        <div class="fs-4 fw-bold">$ {{ number_format($today['cashouts_amount'], 2, '.', ',') }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card"><div class="card-body">
        <div class="text-body-tertiary small">GGR hoje</div>
        <div class="fs-4 fw-bold {{ $today['ggr'] >= 0 ? 'text-success' : 'text-danger' }}">$ {{ number_format($today['ggr'], 2, '.', ',') }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card"><div class="card-body">
        <div class="text-body-tertiary small">Rodadas hoje</div>
        <div class="fs-4 fw-bold">{{ $today['rounds_count'] }}</div>
      </div></div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-12 col-xl-8">
      <div class="card">
        <div class="card-header"><h5 class="mb-0">Últimas 48 horas</h5></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
              <thead>
                <tr>
                  <th>Hora</th>
                  <th>Online pico</th>
                  <th>Logins</th>
                  <th>Únicos</th>
                  <th>Apostas</th>
                  <th>Cashouts</th>
                  <th>Rodadas</th>
                  <th>Crash médio</th>
                  <th>GGR</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($hourly as $row)
                  <tr>
                    <td>{{ $row->hour_start?->timezone(config('app.timezone'))->format('d/m H:00') }}</td>
                    <td>{{ $row->online_peak }}</td>
                    <td>{{ $row->logins }}</td>
                    <td>{{ $row->unique_players }}</td>
                    <td>{{ $row->bets_count }} <span class="text-body-tertiary">($ {{ number_format((float) $row->bets_amount, 2, '.', ',') }})</span></td>
                    <td>$ {{ number_format((float) $row->cashouts_amount, 2, '.', ',') }}</td>
                    <td>{{ $row->rounds_count }}</td>
                    <td>{{ number_format($row->avgCrashPoint(), 2, '.', ',') }}x</td>
                    <td class="{{ (float) $row->ggr >= 0 ? 'text-success' : 'text-danger' }}">$ {{ number_format((float) $row->ggr, 2, '.', ',') }}</td>
                  </tr>
                @empty
                  <tr><td colspan="9" class="text-center text-body-tertiary py-4">Sem dados ainda — jogue algumas rodadas.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-4">
      <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Sessões ativas</h5></div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
            @forelse ($openSessions as $session)
              <li class="list-group-item">
                <div class="fw-semibold">{{ $session->user?->name ?? 'Jogador' }}</div>
                <div class="small text-body-tertiary">
                  desde {{ $session->started_at?->format('H:i') }}
                  · visto {{ $session->last_seen_at?->diffForHumans() }}
                </div>
              </li>
            @empty
              <li class="list-group-item text-body-tertiary">Ninguém online agora.</li>
            @endforelse
          </ul>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h5 class="mb-0">Eventos recentes</h5></div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
            @forelse ($recentEvents as $event)
              <li class="list-group-item">
                <div class="d-flex justify-content-between gap-2">
                  <span class="fw-semibold">{{ $event->event }}</span>
                  <span class="small text-body-tertiary">{{ $event->occurred_at?->format('H:i:s') }}</span>
                </div>
                <div class="small text-body-tertiary">
                  {{ $event->user?->name ?? 'sistema' }}
                  @if ($event->amount !== null)
                    · $ {{ number_format((float) $event->amount, 2, '.', ',') }}
                  @endif
                  @if ($event->multiplier !== null)
                    · {{ number_format((float) $event->multiplier, 2, '.', ',') }}x
                  @endif
                </div>
              </li>
            @empty
              <li class="list-group-item text-body-tertiary">Sem eventos.</li>
            @endforelse
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
