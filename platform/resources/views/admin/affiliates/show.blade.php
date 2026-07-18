@extends('layouts.admin')

@section('title', 'Afiliado #'.$affiliate->id)
@section('heading', 'Afiliado')

@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-2">
  <div>
    <h2 class="text-body-emphasis mb-1">Afiliado — {{ $affiliate->user?->name }}</h2>
    <p class="text-body-tertiary mb-0">{{ $affiliate->user?->email }} · código <code>{{ $affiliate->referral_code }}</code></p>
  </div>
  <a class="btn btn-phoenix-secondary" href="{{ route('admin.affiliates.index') }}">Voltar</a>
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

<div class="row g-3">
  <div class="col-12 col-lg-5">
    <div class="card h-100">
      <div class="card-body">
        <h5 class="mb-3">Configuração</h5>
        <form method="POST" action="{{ route('admin.affiliates.update', $affiliate) }}" class="row g-3">
          @csrf
          @method('PUT')
          <div class="col-12">
            <label class="form-label">Código de indicação</label>
            <input type="text" name="referral_code" value="{{ old('referral_code', $affiliate->referral_code) }}" class="form-control" required maxlength="40">
          </div>
          <div class="col-12">
            <label class="form-label">Comissão % (máx. 15)</label>
            <input type="number" step="0.01" min="0" max="15" name="commission_percent" value="{{ old('commission_percent', $affiliate->commission_percent) }}" class="form-control" required>
          </div>
          <div class="col-12">
            <label class="form-label">PIX de recebimento</label>
            <input type="text" name="pix_key" value="{{ old('pix_key', $affiliate->pix_key) }}" class="form-control" maxlength="120" placeholder="CPF, e-mail, telefone ou chave aleatória">
            @unless ($affiliate->hasPixKey())
              <div class="form-text text-warning">Obrigatório para confirmar comissão.</div>
            @endunless
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="active" value="1" id="active" @checked(old('active', $affiliate->active))>
              <label class="form-check-label" for="active">Ativo</label>
            </div>
          </div>
          <div class="col-12">
            <button class="btn btn-primary" type="submit">Salvar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="card mb-3">
      <div class="card-body">
        <h5 class="mb-3">Calcular comissão</h5>
        <p class="text-body-tertiary small mb-3">Lista depósitos pagos da carteira ainda sem comissão liquidada (pending/paid) no período.</p>
        <form method="POST" action="{{ route('admin.affiliates.calculate', $affiliate) }}" class="row g-3 align-items-end">
          @csrf
          <div class="col-md-4">
            <label class="form-label">De</label>
            <input type="date" name="from" value="{{ old('from', $from) }}" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Até</label>
            <input type="date" name="to" value="{{ old('to', $to) }}" class="form-control" required>
          </div>
          <div class="col-md-4">
            <button class="btn btn-phoenix-secondary w-100" type="submit">Calcular comissão</button>
          </div>
        </form>

        @if ($preview)
          <div class="border rounded-3 p-3 mt-4 bg-body-highlight">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
              <div>
                <div class="fw-semibold">Preview · {{ $preview['count'] }} depósito(s)</div>
                <div class="text-body-tertiary small">Base R$ {{ number_format($preview['deposits_sum'], 2, ',', '.') }} · {{ number_format((float) $affiliate->commission_percent, 2) }}%</div>
              </div>
              <div class="fs-5 fw-bold">R$ {{ number_format($preview['total'], 2, ',', '.') }}</div>
            </div>

            @if ($preview['count'] > 0)
              <div class="table-responsive mb-3" style="max-height: 220px; overflow:auto;">
                <table class="table table-sm fs-9 mb-0">
                  <thead><tr><th>Depósito</th><th>Jogador</th><th>Pago em</th><th>Base</th><th>Comissão</th></tr></thead>
                  <tbody>
                    @foreach ($preview['items'] as $item)
                      <tr>
                        <td>#{{ $item->deposit_id }}</td>
                        <td>{{ $item->deposit?->user?->email }}</td>
                        <td>{{ $item->deposit?->paid_at?->format('d/m/Y H:i') }}</td>
                        <td>R$ {{ number_format((float) $item->base_amount, 2, ',', '.') }}</td>
                        <td>R$ {{ number_format((float) $item->commission_amount, 2, ',', '.') }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>

              <form method="POST" action="{{ route('admin.affiliates.confirm', $affiliate) }}" onsubmit="return confirm('Confirmar e criar saque de R$ {{ number_format($preview['total'], 2, ',', '.') }} em /admin/saques?');">
                @csrf
                <input type="hidden" name="from" value="{{ $from }}">
                <input type="hidden" name="to" value="{{ $to }}">
                <button class="btn btn-success" type="submit" @disabled(! $affiliate->hasPixKey())>
                  Confirmar e gerar saque
                </button>
                @unless ($affiliate->hasPixKey())
                  <span class="text-warning small ms-2">Cadastre o PIX acima antes de confirmar.</span>
                @endunless
              </form>
            @else
              <p class="mb-0 text-body-tertiary">Nenhum depósito elegível neste período.</p>
            @endif
          </div>
        @endif
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <h5 class="mb-3">Novo código de bônus (jogador)</h5>
        <form method="POST" action="{{ route('admin.affiliates.codes.store', $affiliate) }}" class="row g-3" id="affiliate-bonus-form">
          @csrf
          <div class="col-md-4">
            <label class="form-label">Código</label>
            <input type="text" name="code" maxlength="40" class="form-control" placeholder="AUTO" value="{{ old('code') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">Tipo</label>
            <select name="type" id="affiliate-bonus-type" class="form-select" required>
              <option value="fixed" @selected(old('type', 'fixed') === 'fixed')>Valor fixo</option>
              <option value="match" @selected(old('type') === 'match')>Match % do depósito</option>
            </select>
          </div>
          <div class="col-md-4" data-aff-field="fixed">
            <label class="form-label">Bônus jogador ($)</label>
            <input type="number" step="0.01" min="0" name="bonus_amount" value="{{ old('bonus_amount', 0) }}" class="form-control">
          </div>
          <div class="col-md-2 d-none" data-aff-field="match">
            <label class="form-label">Match %</label>
            <input type="number" step="0.01" min="0" name="match_percent" value="{{ old('match_percent', 100) }}" class="form-control">
          </div>
          <div class="col-md-2 d-none" data-aff-field="match">
            <label class="form-label">Teto ($)</label>
            <input type="number" step="0.01" min="0" name="max_bonus" value="{{ old('max_bonus') }}" class="form-control" placeholder="Sem teto">
          </div>
          <div class="col-md-2">
            <label class="form-label">Máx. usos</label>
            <input type="number" min="1" name="max_uses" class="form-control" value="{{ old('max_uses') }}">
          </div>
          <div class="col-md-2">
            <label class="form-label">Expira</label>
            <input type="datetime-local" name="expires_at" class="form-control" value="{{ old('expires_at') }}">
          </div>
          <div class="col-12">
            <button class="btn btn-primary" type="submit">Criar código</button>
          </div>
        </form>

        <div class="table-responsive mt-4">
          <table class="table table-sm fs-9 mb-0">
            <thead><tr><th>Código</th><th>Tipo</th><th>Bônus</th><th>Usos</th><th>Ativo</th></tr></thead>
            <tbody>
              @forelse ($affiliate->bonusCodes as $code)
                <tr>
                  <td><strong>{{ $code->code }}</strong></td>
                  <td>{{ $code->kindLabel() }}</td>
                  <td>{{ $code->label() }}</td>
                  <td>{{ $code->uses_count }}{{ $code->max_uses ? '/'.$code->max_uses : '' }}</td>
                  <td>{{ $code->active ? 'sim' : 'não' }}</td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-body-tertiary">Nenhum código.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card mb-3">
      <div class="card-header"><h5 class="mb-0">Carteira ({{ $players->total() }} jogadores)</h5></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm fs-9 mb-0">
            <thead>
              <tr class="bg-body-secondary">
                <th class="ps-3 border-top border-translucent">ID</th>
                <th class="border-top border-translucent">Nome</th>
                <th class="border-top border-translucent">E-mail</th>
                <th class="pe-3 border-top border-translucent">Cadastro</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($players as $player)
                <tr>
                  <td class="ps-3"><a href="{{ route('admin.players.show', $player) }}">#{{ $player->id }}</a></td>
                  <td>{{ $player->name }}</td>
                  <td>{{ $player->email }}</td>
                  <td class="pe-3">{{ $player->created_at?->format('d/m/Y') }}</td>
                </tr>
              @empty
                <tr><td colspan="4" class="ps-3 py-3 text-body-tertiary">Nenhum jogador vinculado.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
      @if ($players->hasPages())
        <div class="card-footer">{{ $players->withQueryString()->links() }}</div>
      @endif
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Comissões</h5></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm fs-9 mb-0">
            <thead>
              <tr class="bg-body-secondary">
                <th class="ps-3 border-top border-translucent">Depósito</th>
                <th class="border-top border-translucent">Base</th>
                <th class="border-top border-translucent">Comissão</th>
                <th class="border-top border-translucent">Status</th>
                <th class="pe-3 border-top border-translucent">Saque</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($commissions as $c)
                <tr>
                  <td class="ps-3">#{{ $c->deposit_id }}</td>
                  <td>R$ {{ number_format((float) $c->base_amount, 2, ',', '.') }}</td>
                  <td>R$ {{ number_format((float) $c->commission_amount, 2, ',', '.') }}</td>
                  <td>{{ $c->status }}</td>
                  <td class="pe-3">{{ $c->withdrawal_id ? '#'.$c->withdrawal_id : '—' }}</td>
                </tr>
              @empty
                <tr><td colspan="5" class="ps-3 py-3 text-body-tertiary">Nenhuma comissão.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
      @if ($commissions->hasPages())
        <div class="card-footer">{{ $commissions->withQueryString()->links() }}</div>
      @endif
    </div>
  </div>
</div>

<script>
(function () {
  const select = document.getElementById('affiliate-bonus-type');
  if (!select) return;
  const sync = () => {
    const type = select.value;
    document.querySelectorAll('[data-aff-field]').forEach((el) => {
      el.classList.toggle('d-none', el.getAttribute('data-aff-field') !== type);
    });
  };
  select.addEventListener('change', sync);
  sync();
})();
</script>
@endsection
