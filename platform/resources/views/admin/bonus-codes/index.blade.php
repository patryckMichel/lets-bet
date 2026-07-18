@extends('layouts.admin')

@section('title', 'Códigos')
@section('heading', 'Códigos')

@section('content')
<div class="mb-4">
  <h2 class="text-body-emphasis mb-1">Códigos e campanhas de bônus</h2>
  <p class="text-body-tertiary mb-0">Selecione o tipo para ver descrição, exemplo e como usar.</p>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-5">
    <div class="card">
      <div class="card-body">
        <h5 class="mb-3">Criar</h5>
        <form method="POST" action="{{ route('admin.bonus-codes.store') }}" class="row g-3" id="bonus-code-form">
          @csrf
          <div class="col-12">
            <label class="form-label">Tipo</label>
            <select name="kind" id="bonus-kind" class="form-select" required>
              @foreach ($helpCatalog as $key => $meta)
                <option value="{{ $key }}" @selected(old('kind', 'fixed') === $key)>{{ $meta['title'] }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-12">
            <div class="alert alert-subtle-primary border mb-0" id="bonus-help">
              <div class="fw-semibold mb-1" data-help="title"></div>
              <div class="small mb-2"><strong>Descrição:</strong> <span data-help="description"></span></div>
              <div class="small mb-2"><strong>Exemplo:</strong> <span data-help="example"></span></div>
              <div class="small mb-0"><strong>Como usar:</strong> <span data-help="usage"></span></div>
            </div>
          </div>

          <div class="col-12" data-show="code">
            <label class="form-label">Código (opcional em campanhas automáticas)</label>
            <input type="text" name="code" maxlength="40" class="form-control" value="{{ old('code') }}" placeholder="AUTO">
          </div>

          <div class="col-12" data-field="bonus_amount">
            <label class="form-label">Valor bônus ($)</label>
            <input type="number" step="0.01" min="0" name="bonus_amount" value="{{ old('bonus_amount', 0) }}" class="form-control">
          </div>
          <div class="col-6" data-field="match_percent">
            <label class="form-label">Percentual %</label>
            <input type="number" step="0.01" min="0" name="match_percent" value="{{ old('match_percent', 100) }}" class="form-control">
          </div>
          <div class="col-6" data-field="max_bonus">
            <label class="form-label">Teto bônus ($)</label>
            <input type="number" step="0.01" min="0" name="max_bonus" value="{{ old('max_bonus') }}" class="form-control" placeholder="Sem teto">
          </div>
          <div class="col-12" data-field="inactive_days">
            <label class="form-label">Dias sem depositar</label>
            <input type="number" min="1" name="inactive_days" value="{{ old('inactive_days', 7) }}" class="form-control">
          </div>

          <div class="col-12" data-show="affiliate">
            <label class="form-label">Afiliado ID (opcional)</label>
            <input type="number" name="affiliate_id" min="1" class="form-control" value="{{ old('affiliate_id') }}">
          </div>
          <div class="col-6">
            <label class="form-label">Máx. usos (global)</label>
            <input type="number" name="max_uses" min="1" class="form-control" value="{{ old('max_uses') }}">
          </div>
          <div class="col-6">
            <label class="form-label">Expira</label>
            <input type="datetime-local" name="expires_at" class="form-control" value="{{ old('expires_at') }}">
          </div>
          <div class="col-12">
            <button class="btn btn-primary w-100" type="submit">Criar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm fs-9 mb-0">
            <thead>
              <tr class="bg-body-secondary">
                <th class="ps-3 border-top border-translucent">Código</th>
                <th class="border-top border-translucent">Tipo</th>
                <th class="border-top border-translucent">Bônus</th>
                <th class="border-top border-translucent">Usos</th>
                <th class="border-top border-translucent">Ativo</th>
                <th class="pe-3 border-top border-translucent"></th>
              </tr>
            </thead>
            <tbody>
              @foreach ($codes as $code)
                <tr>
                  <td class="ps-3"><strong>{{ $code->code }}</strong></td>
                  <td>{{ $code->kindLabel() }}</td>
                  <td>{{ $code->label() }}</td>
                  <td>{{ $code->uses_count }}{{ $code->max_uses ? '/'.$code->max_uses : '' }}</td>
                  <td>{{ $code->active ? 'sim' : 'não' }}</td>
                  <td class="pe-3">
                    <form method="POST" action="{{ route('admin.bonus-codes.toggle', $code) }}">
                      @csrf
                      <button class="btn btn-sm btn-phoenix-secondary" type="submit">{{ $code->active ? 'Desativar' : 'Ativar' }}</button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
      @if ($codes->hasPages())
        <div class="card-footer">{{ $codes->links() }}</div>
      @endif
    </div>
  </div>
</div>

<script>
(function () {
  const catalog = @json($helpCatalog);
  const fieldsByKind = {
    fixed: ['bonus_amount'],
    match: ['match_percent', 'max_bonus'],
    first_deposit: ['bonus_amount', 'match_percent', 'max_bonus'],
    cashback: ['match_percent', 'max_bonus'],
    reload: ['bonus_amount', 'match_percent', 'max_bonus', 'inactive_days'],
    new_player: ['bonus_amount'],
    affiliate_signup: ['bonus_amount'],
  };
  const codeKinds = { fixed: true, match: true };

  const select = document.getElementById('bonus-kind');
  if (!select) return;

  const sync = () => {
    const kind = select.value;
    const help = catalog[kind] || {};
    document.querySelectorAll('[data-help]').forEach((el) => {
      el.textContent = help[el.getAttribute('data-help')] || '';
    });

    const show = new Set(fieldsByKind[kind] || []);
    document.querySelectorAll('[data-field]').forEach((el) => {
      const key = el.getAttribute('data-field');
      el.classList.toggle('d-none', !show.has(key));
    });

    document.querySelectorAll('[data-show="affiliate"]').forEach((el) => {
      el.classList.toggle('d-none', !codeKinds[kind]);
    });
  };

  select.addEventListener('change', sync);
  sync();
})();
</script>
@endsection
