@extends('layouts.admin')

@section('title', 'Afiliados')
@section('heading', 'Afiliados')

@section('content')
<div class="mb-4">
  <h2 class="text-body-emphasis mb-1">Afiliados</h2>
  <p class="text-body-tertiary mb-0">Carteira permanente, comissão sobre depósitos e saque manual.</p>
</div>

@if (session('status'))
  <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if (session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if ($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card mb-4">
  <div class="card-body">
    <h5 class="mb-2">Gerar comissão de todos</h5>
    <p class="text-body-tertiary small mb-3">
      Calcula o período para todos os afiliados ativos. Na confirmação, cria um saque em
      <a href="{{ route('admin.withdrawals.index') }}">/admin/saques</a> por afiliado com PIX e valor &gt; 0.
    </p>
    <form method="POST" action="{{ route('admin.affiliates.calculate-all') }}" class="row g-3 align-items-end">
      @csrf
      <div class="col-md-3">
        <label class="form-label">De</label>
        <input type="date" name="from" value="{{ old('from', $from) }}" class="form-control" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Até</label>
        <input type="date" name="to" value="{{ old('to', $to) }}" class="form-control" required>
      </div>
      <div class="col-md-3">
        <button class="btn btn-phoenix-secondary w-100" type="submit">Calcular todos</button>
      </div>
    </form>

    @if ($bulkPreview)
      <hr class="my-4">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <strong>{{ $bulkPreview['confirmable_count'] }}</strong> afiliado(s) prontos ·
          total R$ {{ number_format($bulkPreview['confirmable_total'], 2, ',', '.') }}
        </div>
        @if ($bulkPreview['confirmable_count'] > 0)
          <form method="POST" action="{{ route('admin.affiliates.confirm-all') }}"
                onsubmit="return confirm('Gerar {{ $bulkPreview['confirmable_count'] }} saque(s) totalizando R$ {{ number_format($bulkPreview['confirmable_total'], 2, ',', '.') }}?');">
            @csrf
            <input type="hidden" name="from" value="{{ $from }}">
            <input type="hidden" name="to" value="{{ $to }}">
            <button class="btn btn-success" type="submit">Confirmar e gerar todos os saques</button>
          </form>
        @endif
      </div>

      <div class="table-responsive">
        <table class="table table-sm fs-9 mb-0">
          <thead>
            <tr class="bg-body-secondary">
              <th class="ps-3">ID</th>
              <th>Afiliado</th>
              <th>Depósitos</th>
              <th>Qtd</th>
              <th>Comissão</th>
              <th class="pe-3">Situação</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($bulkPreview['rows'] as $row)
              @php $a = $row['affiliate']; @endphp
              <tr>
                <td class="ps-3">#{{ $a->id }}</td>
                <td>
                  <div>{{ $a->user?->email }}</div>
                  <code class="small">{{ $a->referral_code }}</code>
                </td>
                <td>R$ {{ number_format($row['deposits_sum'], 2, ',', '.') }}</td>
                <td>{{ $row['count'] }}</td>
                <td><strong>R$ {{ number_format($row['total'], 2, ',', '.') }}</strong></td>
                <td class="pe-3">
                  @if ($row['can_confirm'])
                    <span class="badge badge-phoenix badge-phoenix-success">Pronto</span>
                  @elseif ($row['skip_reason'])
                    <span class="text-body-tertiary small">{{ $row['skip_reason'] }}</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm fs-9 mb-0">
        <thead>
          <tr class="bg-body-secondary">
            <th class="ps-3 border-top border-translucent">ID</th>
            <th class="border-top border-translucent">Jogador</th>
            <th class="border-top border-translucent">Código</th>
            <th class="border-top border-translucent">Comissão</th>
            <th class="border-top border-translucent">Carteira</th>
            <th class="border-top border-translucent">PIX</th>
            <th class="border-top border-translucent">Status</th>
            <th class="pe-3 border-top border-translucent"></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($affiliates as $a)
            <tr>
              <td class="align-middle ps-3">#{{ $a->id }}</td>
              <td class="align-middle">{{ $a->user?->email }}</td>
              <td class="align-middle"><code>{{ $a->referral_code }}</code></td>
              <td class="align-middle">{{ number_format((float) $a->commission_percent, 2) }}%</td>
              <td class="align-middle">{{ $a->players_count }}</td>
              <td class="align-middle">{{ $a->hasPixKey() ? 'sim' : 'não' }}</td>
              <td class="align-middle">{{ $a->active ? 'ativo' : 'inativo' }}</td>
              <td class="align-middle pe-3"><a class="btn btn-sm btn-phoenix-secondary" href="{{ route('admin.affiliates.show', $a) }}">Abrir</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @if ($affiliates->hasPages())
    <div class="card-footer">{{ $affiliates->links() }}</div>
  @endif
</div>
@endsection
