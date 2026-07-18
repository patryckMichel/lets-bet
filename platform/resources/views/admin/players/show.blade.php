@extends('layouts.admin')

@section('title', $user->name)
@section('heading', $user->name)

@section('content')
<div class="mb-4">
  <h2 class="text-body-emphasis mb-1">{{ $user->name }}</h2>
  <p class="text-body-tertiary mb-0">{{ $user->email }}</p>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <h5 class="mb-3">Resumo</h5>
        <p class="mb-1"><strong>E-mail:</strong> {{ $user->email }}</p>
        <p class="mb-1"><strong>CPF:</strong> {{ $user->cpf ?: '—' }}</p>
        <p class="mb-1">
          <strong>Sexo:</strong>
          {{ match ($user->sexo) {
            'masculino' => 'Masculino',
            'feminino' => 'Feminino',
            'outro' => 'Outro',
            'nao_informar' => 'Não informado',
            default => $user->sexo ?: '—',
          } }}
        </p>
        <p class="mb-1"><strong>UF / Cidade:</strong> {{ $user->estado ?: '—' }} / {{ $user->cidade ?: '—' }}</p>
        <p class="mb-1"><strong>Saldo:</strong> $ {{ number_format((float) $user->balance, 2, '.', ',') }}</p>
        <p class="mb-3"><strong>Bônus:</strong> $ {{ number_format((float) $user->bonus_balance, 2, '.', ',') }}</p>
        <hr class="my-3">
        <p class="mb-1"><strong>Depositado:</strong> $ {{ number_format((float) $stats['deposited'], 2, '.', ',') }}</p>
        <p class="mb-1"><strong>Sacado:</strong> $ {{ number_format((float) $stats['withdrawn'], 2, '.', ',') }}</p>
        <p class="mb-3">
          <strong>Resultado:</strong>
          @if ($stats['result_pct'] === null)
            —
          @else
            <span class="{{ $stats['result_pct'] >= 0 ? 'text-success' : 'text-danger' }}">
              {{ number_format((float) $stats['result_pct'], 1, '.', ',') }}%
            </span>
            <span class="small text-body-tertiary d-block">(sacado + saldo − depositado) / depositado</span>
          @endif
        </p>
        <p class="mb-1"><strong>Rollover:</strong> $ {{ number_format((float) $user->wagering_progress, 2, '.', ',') }} / $ {{ number_format((float) $user->wagering_required, 2, '.', ',') }}</p>
        <p class="mb-1"><strong>IP cadastro:</strong> {{ $user->registration_ip ?: '—' }}</p>
        <p class="mb-1"><strong>Último IP:</strong> {{ $user->last_ip ?: '—' }}</p>
        <p class="mb-3">
          @if ($user->is_blocked)<span class="admin-badge admin-badge--err">bloqueado</span>@endif
          @if ($user->fraud_flag)<span class="admin-badge admin-badge--err">suspeito</span>@endif
          @if ($user->kyc_verified)<span class="admin-badge admin-badge--ok">KYC ok</span>@endif
          @if ($user->affiliate)<span class="admin-badge admin-badge--ok">afiliado #{{ $user->affiliate->id }}</span>@endif
          @if ($user->affiliate_id)<span class="admin-badge">indicado #{{ $user->affiliate_id }}</span>@endif
        </p>
        @if ($user->fraud_note)
          <pre class="small text-body-tertiary" style="white-space:pre-wrap;">{{ $user->fraud_note }}</pre>
        @endif
        <form method="POST" action="{{ route('admin.players.block', $user) }}" class="mb-2">
          @csrf
          <button class="btn {{ $user->is_blocked ? 'btn-success' : 'btn-danger' }} w-100" type="submit">
            {{ $user->is_blocked ? 'Desbloquear' : 'Bloquear' }}
          </button>
        </form>
        <form method="POST" action="{{ route('admin.players.kyc', $user) }}" class="mb-2">
          @csrf
          <button class="btn btn-phoenix-secondary w-100" type="submit">
            {{ $user->kyc_verified ? 'Revogar KYC' : 'Marcar KYC verificado' }}
          </button>
        </form>
        @if ($user->fraud_flag)
          <form method="POST" action="{{ route('admin.players.fraud-clear', $user) }}">
            @csrf
            <button class="btn btn-outline-warning w-100" type="submit">Limpar flag suspeito</button>
          </form>
        @endif
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-8">
    <div class="card mb-3">
      <div class="card-body">
        <h5 class="mb-3">Ajustar saldo</h5>
        <p class="small text-body-tertiary mb-3">
          <strong>Saldo normal</strong> entra na gestão financeira.
          <strong>Saldo bônus</strong> não altera o caixa — só o histórico do jogador.
        </p>
        <form method="POST" action="{{ route('admin.players.balance', $user) }}" class="row g-3">
          @csrf
          <div class="col-md-4">
            <label class="form-label">Saldo real</label>
            <input type="number" step="0.01" min="0" name="balance" value="{{ $user->balance }}" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Saldo bônus (não vai ao caixa)</label>
            <input type="number" step="0.01" min="0" name="bonus_balance" value="{{ $user->bonus_balance }}" class="form-control" required>
          </div>
          <div class="col-12">
            <label class="form-label">Motivo</label>
            <textarea name="note" class="form-control" required></textarea>
          </div>
          <div class="col-12">
            <button class="btn btn-primary" type="submit">Salvar saldo</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><h5 class="mb-0">Histórico de ajustes</h5></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm fs-9 mb-0">
            <thead>
              <tr class="bg-body-secondary">
                <th class="ps-3">Quando</th>
                <th>Admin</th>
                <th>Saldo real</th>
                <th>Bônus</th>
                <th class="pe-3">Motivo</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($history as $log)
                @php
                  $before = $log->before ?? [];
                  $after = $log->after ?? [];
                @endphp
                <tr>
                  <td class="ps-3">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                  <td>{{ $log->admin?->email ?? '—' }}</td>
                  <td>
                    $ {{ number_format((float) ($before['balance'] ?? 0), 2, '.', ',') }}
                    →
                    $ {{ number_format((float) ($after['balance'] ?? 0), 2, '.', ',') }}
                  </td>
                  <td>
                    $ {{ number_format((float) ($before['bonus_balance'] ?? 0), 2, '.', ',') }}
                    →
                    $ {{ number_format((float) ($after['bonus_balance'] ?? 0), 2, '.', ',') }}
                  </td>
                  <td class="pe-3">{{ $after['note'] ?? '—' }}</td>
                </tr>
              @empty
                <tr><td colspan="5" class="ps-3 py-3 text-body-tertiary">Nenhum ajuste registrado.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    @unless ($user->affiliate)
      <div class="card">
        <div class="card-body">
          <h5 class="mb-3">Tornar afiliado</h5>
          <form method="POST" action="{{ route('admin.players.affiliate', $user) }}" class="row g-3">
            @csrf
            <div class="col-md-4">
              <label class="form-label">Comissão % (máx. 15)</label>
              <input type="number" step="0.01" min="0" max="15" name="commission_percent" value="5" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">PIX de recebimento</label>
              <input type="text" name="pix_key" class="form-control" maxlength="120" placeholder="Opcional agora">
            </div>
            <div class="col-12">
              <button class="btn btn-primary" type="submit">Cadastrar afiliado</button>
            </div>
          </form>
        </div>
      </div>
    @else
      <div class="card">
        <div class="card-body">
          <a class="btn btn-primary" href="{{ route('admin.affiliates.show', $user->affiliate) }}">Abrir afiliado</a>
        </div>
      </div>
    @endunless
  </div>
</div>
@endsection
