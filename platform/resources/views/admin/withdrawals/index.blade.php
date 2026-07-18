@extends('layouts.admin')

@section('title', 'Saques')
@section('heading', 'Saques')

@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-2">
  <div>
    <h2 class="text-body-emphasis mb-1">Solicitações de saque</h2>
    <p class="text-body-tertiary mb-0">Pague via PIX (Asaas) direto para a chave informada pelo jogador.</p>
  </div>
</div>

@if (session('status'))
  <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if ($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm fs-9 mb-0 align-middle">
        <thead>
          <tr class="bg-body-secondary">
            <th class="ps-3 border-top border-translucent">ID</th>
            <th class="border-top border-translucent">Jogador</th>
            <th class="border-top border-translucent">Tipo</th>
            <th class="border-top border-translucent">Valor</th>
            <th class="border-top border-translucent">Chave PIX</th>
            <th class="border-top border-translucent">Status</th>
            <th class="border-top border-translucent">Quando</th>
            <th class="pe-3 border-top border-translucent text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($withdrawals as $w)
            <tr>
              <td class="ps-3">#{{ $w->id }}</td>
              <td>
                <div class="fw-semibold">{{ $w->user?->name }}</div>
                <div class="text-body-tertiary">{{ $w->user?->email }}</div>
              </td>
              <td>
                @if ($w->isAffiliateCommission())
                  <span class="badge badge-phoenix badge-phoenix-info">Comissão afiliado</span>
                  @if ($w->admin_note)
                    <div class="small text-body-tertiary mt-1" style="max-width:180px;">{{ \Illuminate\Support\Str::limit($w->admin_note, 80) }}</div>
                  @endif
                @else
                  <span class="text-body-tertiary">Jogador</span>
                @endif
              </td>
              <td class="fw-bold">R$ {{ number_format((float) $w->amount, 2, ',', '.') }}</td>
              <td>
                <code class="small">{{ $w->pix_key }}</code>
                @if ($w->pix_key_type)
                  <div class="text-body-tertiary">{{ $w->pix_key_type }}</div>
                @endif
              </td>
              <td>
                @php
                  $badge = match ($w->status) {
                    'paid' => 'success',
                    'approved' => 'primary',
                    'rejected' => 'danger',
                    default => 'warning',
                  };
                @endphp
                <span class="badge badge-phoenix badge-phoenix-{{ $badge }}">{{ $w->status }}</span>
                @if ($w->provider_status)
                  <div class="small text-body-tertiary">Asaas: {{ $w->provider_status }}</div>
                @endif
              </td>
              <td>{{ $w->created_at?->format('d/m/Y H:i') }}</td>
              <td class="pe-3 text-end">
                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                  @if ($w->canPay())
                    <form method="POST" action="{{ route('admin.withdrawals.pay', $w) }}" onsubmit="return confirm('Enviar PIX de R$ {{ number_format((float) $w->amount, 2, ',', '.') }} para {{ $w->pix_key }}?');">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-success">Pagar PIX</button>
                    </form>
                  @endif
                  @if ($w->isPending())
                    <form method="POST" action="{{ route('admin.withdrawals.reject', $w) }}" onsubmit="return confirm('{{ $w->isAffiliateCommission() ? 'Rejeitar comissão e liberar depósitos para novo cálculo?' : 'Rejeitar saque e devolver saldo ao jogador?' }}');">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-outline-danger">Rejeitar</button>
                    </form>
                  @endif
                  @if ($w->asaas_transfer_id)
                    <span class="small text-body-tertiary d-block">{{ $w->asaas_transfer_id }}</span>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="ps-3 py-4 text-body-tertiary">Nenhuma solicitação.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @if ($withdrawals->hasPages())
    <div class="card-footer">{{ $withdrawals->links() }}</div>
  @endif
</div>
@endsection
