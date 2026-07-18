@extends('layouts.admin')

@section('title', 'Afiliados')
@section('heading', 'Afiliados')

@section('content')
<div class="mb-4">
  <h2 class="text-body-emphasis mb-1">Afiliados</h2>
  <p class="text-body-tertiary mb-0">Carteira permanente, comissão sobre depósitos e saque manual.</p>
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
