@extends('layouts.admin')

@section('title', 'Jogadores')
@section('heading', 'Jogadores')

@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-end gap-3">
  <div>
    <h2 class="text-body-emphasis mb-1">Jogadores</h2>
    <p class="text-body-tertiary mb-0">Busque, exporte, bloqueie, ajuste saldo e marque afiliados.</p>
  </div>
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <form method="GET" class="d-flex gap-2">
      <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="nome, e-mail, CPF ou cidade" style="min-width:220px">
      <button class="btn btn-sm btn-primary" type="submit">Filtrar</button>
    </form>
    <a class="btn btn-sm btn-phoenix-secondary" href="{{ route('admin.players.export', request()->only('q')) }}">
      Exportar CSV
    </a>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm fs-9 mb-0">
        <thead>
          <tr class="bg-body-secondary">
            <th class="ps-3 border-top border-translucent">ID</th>
            <th class="border-top border-translucent">Nome</th>
            <th class="border-top border-translucent">E-mail</th>
            <th class="border-top border-translucent">Sexo</th>
            <th class="border-top border-translucent">UF</th>
            <th class="border-top border-translucent">Saldo</th>
            <th class="border-top border-translucent">Depositado</th>
            <th class="border-top border-translucent">Sacado</th>
            <th class="border-top border-translucent">Resultado</th>
            <th class="border-top border-translucent">Flags</th>
            <th class="pe-3 border-top border-translucent"></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($players as $player)
            @php
              $s = $stats[$player->id] ?? ['deposited' => 0, 'withdrawn' => 0, 'result_pct' => null];
              $sexoLabel = match ($player->sexo) {
                'masculino' => 'M',
                'feminino' => 'F',
                'outro' => 'Outro',
                'nao_informar' => '—',
                default => $player->sexo ?: '—',
              };
            @endphp
            <tr>
              <td class="align-middle ps-3">#{{ $player->id }}</td>
              <td class="align-middle">{{ $player->name }}</td>
              <td class="align-middle">{{ $player->email }}</td>
              <td class="align-middle">{{ $sexoLabel }}</td>
              <td class="align-middle">{{ $player->estado ?: '—' }}</td>
              <td class="align-middle">$ {{ number_format((float) $player->balance, 2, '.', ',') }}</td>
              <td class="align-middle">$ {{ number_format((float) $s['deposited'], 2, '.', ',') }}</td>
              <td class="align-middle">$ {{ number_format((float) $s['withdrawn'], 2, '.', ',') }}</td>
              <td class="align-middle">
                @if ($s['result_pct'] === null)
                  —
                @else
                  <span class="{{ $s['result_pct'] >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format((float) $s['result_pct'], 1, '.', ',') }}%
                  </span>
                @endif
              </td>
              <td class="align-middle">
                @if ($player->is_admin)<span class="admin-badge">admin</span>@endif
                @if ($player->is_blocked)<span class="admin-badge admin-badge--err">bloqueado</span>@endif
                @if ($player->affiliate)<span class="admin-badge admin-badge--ok">afiliado</span>@endif
              </td>
              <td class="align-middle pe-3">
                <a class="btn btn-sm btn-phoenix-secondary" href="{{ route('admin.players.show', $player) }}">Abrir</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @if ($players->hasPages())
    <div class="card-footer">{{ $players->links() }}</div>
  @endif
</div>
@endsection
