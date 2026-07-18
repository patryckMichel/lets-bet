@extends('layouts.admin')

@section('title', 'Financeiro')
@section('heading', 'Financeiro')

@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-end gap-3">
  <div>
    <h2 class="text-body-emphasis mb-1">Gestão financeira</h2>
    <p class="text-body-tertiary mb-0">Ledger interno da casa</p>
  </div>
  <div class="card px-3 py-2">
    <div class="small text-body-tertiary">Saldo real da casa</div>
    <div class="fs-5 fw-bold text-body-emphasis">$ {{ number_format($houseBalance, 2, '.', ',') }}</div>
    <div class="small text-body-tertiary">Bônus de jogadores não entram neste caixa</div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-4">
    <div class="card">
      <div class="card-body">
        <h5 class="mb-3">Novo lançamento</h5>
        <form method="POST" action="{{ route('admin.finance.store') }}" class="row g-3">
          @csrf
          <div class="col-12">
            <label class="form-label">Tipo</label>
            <select name="type" class="form-select" required>
              <option value="bank_transfer">Transferência bancária</option>
              <option value="manual_adjustment">Ajuste manual</option>
              <option value="affiliate_payout">Saque afiliado</option>
              <option value="withdrawal">Saque jogador</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Direção</label>
            <select name="direction" class="form-select" required>
              <option value="in">Entrada</option>
              <option value="out">Saída</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Valor ($)</label>
            <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
          </div>
          <div class="col-12">
            <label class="form-label">Nota</label>
            <textarea name="note" class="form-control" required></textarea>
          </div>
          <div class="col-12">
            <button class="btn btn-primary w-100" type="submit">Registrar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-8">
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Extrato</h5></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm fs-9 mb-0">
            <thead>
              <tr class="bg-body-secondary">
                <th class="ps-3 border-top border-translucent">Quando</th>
                <th class="border-top border-translucent">Tipo</th>
                <th class="border-top border-translucent">Dir</th>
                <th class="border-top border-translucent">Valor</th>
                <th class="border-top border-translucent">Admin</th>
                <th class="pe-3 border-top border-translucent">Nota</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($entries as $e)
                <tr>
                  <td class="ps-3">{{ $e->created_at?->format('d/m/Y H:i') }}</td>
                  <td>{{ $e->type }}</td>
                  <td>{{ $e->direction }}</td>
                  <td>$ {{ number_format((float) $e->amount, 2, '.', ',') }}</td>
                  <td>{{ $e->admin?->email ?? 'sistema' }}</td>
                  <td class="pe-3">{{ $e->note }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @if ($entries->hasPages())
        <div class="card-footer">{{ $entries->links() }}</div>
      @endif
    </div>
  </div>
</div>
@endsection
