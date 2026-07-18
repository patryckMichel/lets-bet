@extends('layouts.admin')

@section('title', 'Atualização')
@section('heading', 'Atualização')

@section('content')
<div class="mb-4">
  <h2 class="text-body-emphasis mb-1">Atualização do sistema</h2>
  <p class="text-body-tertiary mb-0">
    Compara a versão instalada na VPS com
    <code>{{ $status['repo'] }}</code> (branch <code>{{ $status['branch'] }}</code>).
  </p>
</div>

@if (session('status'))
  <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if ($errors->any())
  <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card h-100">
      <div class="card-body">
        <h5 class="mb-3">Versões</h5>
        <dl class="row mb-0">
          <dt class="col-sm-5">Instalada (VPS)</dt>
          <dd class="col-sm-7"><strong>v{{ $status['local'] }}</strong></dd>

          <dt class="col-sm-5">No Git</dt>
          <dd class="col-sm-7">
            @if ($status['remote'])
              <strong>v{{ $status['remote'] }}</strong>
            @else
              <span class="text-warning">Não foi possível ler o GitHub</span>
              @if (! empty($status['remote_error']))
                <div class="small text-body-tertiary mt-1">{{ $status['remote_error'] }}</div>
              @endif
            @endif
          </dd>

          <dt class="col-sm-5">Arquivo remoto</dt>
          <dd class="col-sm-7">
            <code class="small">{{ $status['version_path'] ?? 'platform/VERSION' }}</code>
            <div class="small text-body-tertiary mt-1">
              <a href="{{ $status['remote_url'] ?? '#' }}" target="_blank" rel="noopener">abrir no GitHub</a>
            </div>
          </dd>

          <dt class="col-sm-5">Commit local</dt>
          <dd class="col-sm-7"><code>{{ $status['commit'] ?: '—' }}</code></dd>

          <dt class="col-sm-5">Script VPS</dt>
          <dd class="col-sm-7">
            @if ($status['script_ok'])
              <span class="badge badge-phoenix badge-phoenix-success">OK</span>
            @else
              <span class="badge badge-phoenix badge-phoenix-warning">Não configurado</span>
            @endif
          </dd>
        </dl>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card h-100">
      <div class="card-body">
        <h5 class="mb-3">Ação</h5>
        @if ($status['update_available'])
          <p class="text-body-tertiary">Há uma versão mais nova no Git. Ao confirmar, o servidor executa <code>git pull</code>, migrations e limpeza de cache.</p>
          <form method="POST" action="{{ route('admin.system-update.run') }}" id="form-update" onsubmit="return window.__confirmUpdate(this);">
            @csrf
            <button type="submit" class="btn btn-primary" @disabled(! $status['script_ok']) id="btn-update">
              Atualizar versão
            </button>
          </form>
          @unless ($status['script_ok'])
            <p class="small text-warning mt-3 mb-0">
              Configure o script na VPS com <code>scripts/vps-enable-git-updates.sh</code> antes de atualizar.
            </p>
          @endunless
        @else
          <p class="mb-0 text-body-tertiary">
            @if ($status['remote'] === null)
              Não foi possível comparar com o Git. Verifique rede/token (<code>GITHUB_TOKEN</code> se o repo for privado).
            @else
              O sistema já está na versão mais recente disponível no Git.
            @endif
          </p>
          <button type="button" class="btn btn-phoenix-secondary mt-3" disabled>Atualizar versão</button>
        @endif
      </div>
    </div>
  </div>
</div>

@if (session('update_log'))
  <div class="card mt-3">
    <div class="card-header"><h5 class="mb-0">Log do update</h5></div>
    <div class="card-body">
      <pre class="mb-0 small" style="max-height:320px;overflow:auto;white-space:pre-wrap;">{{ session('update_log') }}</pre>
    </div>
  </div>
@endif

<div class="card mt-3">
  <div class="card-body">
    <h5 class="mb-2">Como publicar uma versão</h5>
    <ol class="mb-0 text-body-tertiary">
      <li>Altere o código e incremente <code>platform/VERSION</code> (ex.: <code>1.0.1</code>).</li>
      <li><code>git push</code> para <code>{{ $status['branch'] }}</code>.</li>
      <li>Recarregue esta página e clique em <strong>Atualizar versão</strong>.</li>
    </ol>
  </div>
</div>

<script>
window.__confirmUpdate = function (form) {
  if (!confirm('Atualizar a VPS para v{{ $status['remote'] }}? O site pode ficar indisponível por alguns segundos.')) {
    return false;
  }
  const btn = form.querySelector('#btn-update');
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Atualizando…';
  }
  return true;
};
</script>
@endsection
