@extends('layouts.admin')

@section('title', 'Configs')
@section('heading', 'Configs')

@section('content')
<div class="mb-4">
  <h2 class="text-body-emphasis mb-1">Configurações</h2>
  <p class="text-body-secondary mb-0">Parâmetros da casa de jogos e integração PIX.</p>
</div>

<form method="POST" action="{{ route('admin.settings.update') }}" id="settings-form">
  @csrf
  @method('PUT')

  <div class="row g-4">
    <div class="col-xl-6">
      <div class="card h-100">
        <div class="card-header border-bottom">
          <h5 class="mb-0">Casa de jogos</h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">House edge (0–0.5)</label>
              <input type="number" step="0.001" name="house_edge" value="{{ old('house_edge', $settings['house_edge']) }}" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Max multiplier</label>
              <input type="number" step="0.1" name="max_multiplier" value="{{ old('max_multiplier', $settings['max_multiplier']) }}" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Depósito mínimo</label>
              <input type="number" step="0.01" name="deposit_min" value="{{ old('deposit_min', $settings['deposit_min']) }}" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Depósito máximo</label>
              <input type="number" step="0.01" name="deposit_max" value="{{ old('deposit_max', $settings['deposit_max']) }}" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Saque mínimo (saldo real)</label>
              <input type="number" step="0.01" name="withdrawal_min" value="{{ old('withdrawal_min', $settings['withdrawal_min'] ?? '200') }}" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Rollover (multiplicador)</label>
              <input type="number" step="0.1" min="0" name="wagering_multiplier" value="{{ old('wagering_multiplier', $settings['wagering_multiplier'] ?? '20') }}" class="form-control" required>
              <div class="form-text">Ex.: 20 = precisa apostar 20× (depósito + bônus) antes de sacar.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">KYC: limiar de saque (R$)</label>
              <input type="number" step="0.01" min="0" name="kyc_withdraw_threshold" value="{{ old('kyc_withdraw_threshold', $settings['kyc_withdraw_threshold'] ?? '500') }}" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Cadastros máx. por IP/dia</label>
              <input type="number" min="1" name="velocity_register_per_ip_day" value="{{ old('velocity_register_per_ip_day', $settings['velocity_register_per_ip_day'] ?? '3') }}" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Depósitos máx. por IP/dia</label>
              <input type="number" min="1" name="velocity_deposit_per_ip_day" value="{{ old('velocity_deposit_per_ip_day', $settings['velocity_deposit_per_ip_day'] ?? '10') }}" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Teto diário bônus afiliado (cadastros)</label>
              <input type="number" min="0" name="affiliate_signup_daily_cap" value="{{ old('affiliate_signup_daily_cap', $settings['affiliate_signup_daily_cap'] ?? '50') }}" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Bloquear afiliado mesmo IP</label>
              <select name="affiliate_block_same_ip" class="form-select">
                <option value="1" @selected(old('affiliate_block_same_ip', $settings['affiliate_block_same_ip'] ?? '1') == '1')>Sim</option>
                <option value="0" @selected(old('affiliate_block_same_ip', $settings['affiliate_block_same_ip'] ?? '1') == '0')>Não</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Aposta mínima</label>
              <input type="number" step="0.01" name="min_bet" value="{{ old('min_bet', $settings['min_bet']) }}" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Aposta máxima</label>
              <input type="number" step="0.01" name="max_bet" value="{{ old('max_bet', $settings['max_bet']) }}" class="form-control" required>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="ghost_bets_enabled" value="1" id="ghost" @checked(old('ghost_bets_enabled', ($settings['ghost_bets_enabled'] ?? '1')) == '1')>
                <label class="form-check-label" for="ghost">Ghost bets ativos</label>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Ghost players min</label>
              <input type="number" name="ghost_players_min" value="{{ old('ghost_players_min', $settings['ghost_players_min']) }}" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Ghost players max</label>
              <input type="number" name="ghost_players_max" value="{{ old('ghost_players_max', $settings['ghost_players_max']) }}" class="form-control" required>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-6">
      <div class="card h-100">
        <div class="card-header border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
          <h5 class="mb-0">PIX / Pagamentos</h5>
          @if ($pixConfigured)
            <span class="badge bg-success">Ativo</span>
          @else
            <span class="badge bg-warning text-dark">Pendente</span>
          @endif
        </div>
        <div class="card-body">
          <div class="alert alert-subtle-info mb-3" role="alert">
            Escolha o provedor PIX. Com <strong>Asaas</strong> ou <strong>Mercado Pago</strong>, o saldo é creditado automaticamente via webhook após o pagamento.
          </div>

          <div class="mb-3">
            <label class="form-label">Provedor PIX</label>
            <select name="pix_provider" id="pix_provider" class="form-select">
              <option value="asaas" @selected(old('pix_provider', $settings['pix_provider'] ?? '') === 'asaas')>Asaas (recomendado)</option>
              <option value="mercadopago" @selected(old('pix_provider', $settings['pix_provider'] ?? '') === 'mercadopago')>Mercado Pago</option>
              <option value="static" @selected(old('pix_provider', $settings['pix_provider'] ?? '') === 'static')>PIX estático (teste / dev)</option>
            </select>
          </div>

          <div id="asaas-fields">
            <div class="mb-3">
              <label class="form-label" for="asaas_api_key">API Key Asaas</label>
              <input
                type="password"
                name="asaas_api_key"
                id="asaas_api_key"
                class="form-control @error('asaas_api_key') is-invalid @enderror"
                placeholder="{{ $hasStoredAsaasApiKey ? 'API Key cadastrada — deixe em branco para manter' : '$aact_prod_...' }}"
                autocomplete="new-password"
              >
              @error('asaas_api_key')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <div class="form-text">Integrações → API Key no painel Asaas.</div>
            </div>

            <div class="mb-3">
              <label class="form-label" for="asaas_webhook_token">Token do webhook (opcional, recomendado)</label>
              <input
                type="password"
                name="asaas_webhook_token"
                id="asaas_webhook_token"
                class="form-control"
                placeholder="{{ $hasStoredAsaasWebhookToken ? 'Token cadastrado — deixe em branco para manter' : 'Mesmo token definido no Asaas' }}"
                autocomplete="new-password"
              >
            </div>

            <div class="mb-3">
              <label class="form-label" for="asaas_webhook_url">URL do webhook Asaas</label>
              <input
                type="url"
                name="asaas_webhook_url"
                id="asaas_webhook_url"
                class="form-control"
                value="{{ old('asaas_webhook_url', $settings['asaas_webhook_url'] ?? '') }}"
                placeholder="{{ $defaultAsaasWebhookUrl }}"
              >
              <div class="form-text">
                Cadastre no Asaas: <code>{{ $defaultAsaasWebhookUrl }}</code>
                <button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="copy-asaas-webhook-url" data-url="{{ $defaultAsaasWebhookUrl }}">Copiar</button>
              </div>
            </div>
          </div>

          <div id="mp-fields">
            <div class="mb-3">
              <label class="form-label" for="mercadopago_access_token">Access Token MP</label>
              <input
                type="password"
                name="mercadopago_access_token"
                id="mercadopago_access_token"
                class="form-control @error('mercadopago_access_token') is-invalid @enderror"
                placeholder="{{ $hasStoredToken ? 'Token cadastrado — deixe em branco para manter' : 'APP_USR-...' }}"
                autocomplete="new-password"
              >
              @error('mercadopago_access_token')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label" for="mercadopago_public_key">Public Key</label>
              <input
                type="text"
                name="mercadopago_public_key"
                id="mercadopago_public_key"
                class="form-control"
                value="{{ old('mercadopago_public_key', $settings['mercadopago_public_key'] ?? '') }}"
                placeholder="APP_USR-..."
              >
            </div>

            <div class="mb-3">
              <label class="form-label" for="mercadopago_webhook_secret">Webhook secret MP (opcional)</label>
              <input
                type="password"
                name="mercadopago_webhook_secret"
                id="mercadopago_webhook_secret"
                class="form-control"
                placeholder="{{ $hasStoredWebhookSecret ? 'Secret cadastrado — deixe em branco para manter' : 'Chave de validação do webhook' }}"
                autocomplete="new-password"
              >
            </div>

            <div class="mb-3">
              <label class="form-label" for="mercadopago_webhook_url">URL do webhook MP</label>
              <input
                type="url"
                name="mercadopago_webhook_url"
                id="mercadopago_webhook_url"
                class="form-control @error('mercadopago_webhook_url') is-invalid @enderror"
                value="{{ old('mercadopago_webhook_url', $settings['mercadopago_webhook_url']) }}"
                placeholder="{{ $defaultWebhookUrl }}"
              >
              @error('mercadopago_webhook_url')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <div id="static-fields">
            <div class="mb-3">
              <label class="form-label" for="pix_key">Chave PIX</label>
              <input
                type="text"
                name="pix_key"
                id="pix_key"
                class="form-control @error('pix_key') is-invalid @enderror"
                value="{{ old('pix_key', $settings['pix_key']) }}"
                placeholder="E-mail, CPF, CNPJ ou chave aleatória"
              >
              @error('pix_key')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label" for="pix_merchant_name">Nome do recebedor</label>
              <input
                type="text"
                name="pix_merchant_name"
                id="pix_merchant_name"
                class="form-control"
                value="{{ old('pix_merchant_name', $settings['pix_merchant_name'] ?? 'LESTBET 369') }}"
                maxlength="25"
              >
            </div>

            <div class="mb-3">
              <label class="form-label" for="pix_merchant_city">Cidade</label>
              <input
                type="text"
                name="pix_merchant_city"
                id="pix_merchant_city"
                class="form-control"
                value="{{ old('pix_merchant_city', $settings['pix_merchant_city'] ?? 'SAO PAULO') }}"
                maxlength="15"
              >
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card">
        <div class="card-header border-bottom">
          <h5 class="mb-0">Taxas do gateway (Asaas)</h5>
        </div>
        <div class="card-body">
          <p class="text-body-secondary small mb-3">
            Usadas no caixa da casa quando o Asaas não informar o líquido. Com PIX, priorizamos o <code>netValue</code> do Asaas.
            Jogador continua recebendo o valor bruto no saldo.
          </p>
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">PIX — taxa fixa (R$)</label>
              <input type="number" step="0.01" min="0" name="fee_asaas_pix_fixed" class="form-control" value="{{ old('fee_asaas_pix_fixed', $settings['fee_asaas_pix_fixed'] ?? '0.99') }}" required>
              <div class="form-text">Promocional atual: 0,99</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">PIX — % </label>
              <input type="number" step="0.01" min="0" name="fee_asaas_pix_percent" class="form-control" value="{{ old('fee_asaas_pix_percent', $settings['fee_asaas_pix_percent'] ?? '0') }}" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Boleto — fixa (R$)</label>
              <input type="number" step="0.01" min="0" name="fee_asaas_boleto_fixed" class="form-control" value="{{ old('fee_asaas_boleto_fixed', $settings['fee_asaas_boleto_fixed'] ?? '0.99') }}" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Cartão crédito — %</label>
              <input type="number" step="0.01" min="0" name="fee_asaas_card_percent" class="form-control" value="{{ old('fee_asaas_card_percent', $settings['fee_asaas_card_percent'] ?? '1.99') }}" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Cartão crédito — fixa (R$)</label>
              <input type="number" step="0.01" min="0" name="fee_asaas_card_fixed" class="form-control" value="{{ old('fee_asaas_card_fixed', $settings['fee_asaas_card_fixed'] ?? '0.49') }}" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Cartão débito — %</label>
              <input type="number" step="0.01" min="0" name="fee_asaas_debit_percent" class="form-control" value="{{ old('fee_asaas_debit_percent', $settings['fee_asaas_debit_percent'] ?? '1.89') }}" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Cartão débito — fixa (R$)</label>
              <input type="number" step="0.01" min="0" name="fee_asaas_debit_fixed" class="form-control" value="{{ old('fee_asaas_debit_fixed', $settings['fee_asaas_debit_fixed'] ?? '0.35') }}" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">PIX pendente expira em (segundos)</label>
              <input type="number" step="1" min="30" max="3600" name="deposit_pix_ttl_seconds" class="form-control" value="{{ old('deposit_pix_ttl_seconds', $settings['deposit_pix_ttl_seconds'] ?? '60') }}" required>
              <div class="form-text">Após esse tempo, cancela no Asaas e remove da plataforma. 60s é agressivo; 300–900s é mais seguro.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <button class="btn btn-primary" type="submit">Salvar configurações</button>
    </div>
  </div>
</form>
@endsection

@push('scripts')
<script>
  (function () {
    const provider = document.getElementById('pix_provider');
    const asaasFields = document.getElementById('asaas-fields');
    const mpFields = document.getElementById('mp-fields');
    const staticFields = document.getElementById('static-fields');
    const copyAsaas = document.getElementById('copy-asaas-webhook-url');

    function togglePixFields() {
      const value = provider.value;
      asaasFields.style.display = value === 'asaas' ? '' : 'none';
      mpFields.style.display = value === 'mercadopago' ? '' : 'none';
      staticFields.style.display = value === 'static' ? '' : 'none';
    }

    if (provider) {
      provider.addEventListener('change', togglePixFields);
      togglePixFields();
    }

    if (copyAsaas) {
      copyAsaas.addEventListener('click', async () => {
        const url = copyAsaas.dataset.url;
        try {
          await navigator.clipboard.writeText(url);
          copyAsaas.textContent = 'Copiado!';
          setTimeout(() => { copyAsaas.textContent = 'Copiar'; }, 2000);
        } catch (e) {
          window.prompt('Copie a URL:', url);
        }
      });
    }
  })();
</script>
@endpush
