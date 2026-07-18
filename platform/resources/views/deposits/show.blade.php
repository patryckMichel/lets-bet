@extends('layouts.app')

@section('title', 'PIX — Adicionar saldo')
@section('body_class', 'page-deposit')

@section('content')
<section class="deposit">
  <header class="deposit__top">
    <a class="deposit__back" href="{{ route('deposits.create') }}">← Novo valor</a>
    <div class="deposit__balance">
      <span>Saldo atual</span>
      <strong>$ {{ number_format((float) $balance, 2, '.', ',') }}</strong>
    </div>
  </header>

  <div class="deposit__card">
    <p class="deposit__kicker">Pagamento PIX</p>
    <h1>$ {{ number_format((float) $deposit->amount, 2, '.', ',') }}</h1>
    <p class="deposit__sub">
      @if ($deposit->isPaid())
        Depósito confirmado e creditado no seu saldo.
      @elseif ($deposit->isExpired())
        Este PIX expirou. Gere um novo valor para continuar.
      @else
        Escaneie o QR Code ou copie o código PIX para pagar.
      @endif
    </p>

    @if (session('status'))
      <div class="deposit__alert deposit__alert--ok" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->has('verify'))
      <div class="deposit__alert" role="alert">{{ $errors->first('verify') }}</div>
    @endif

    @if ($deposit->isExpired())
      <div class="deposit__done">
        <p>O código PIX foi cancelado após {{ $ttlSeconds ?? 60 }} segundos sem pagamento.</p>
        <a class="btn btn-gold" href="{{ route('deposits.create') }}">Gerar novo PIX</a>
        <a class="btn btn-ghost" href="{{ route('lobby') }}">Ir para o lobby</a>
      </div>
    @elseif ($deposit->isPaid())
      <div class="deposit__done">
        <p>Valor creditado em {{ $deposit->paid_at?->format('d/m/Y H:i') }}</p>
        <a class="btn btn-gold" href="{{ route('lobby') }}">Ir para o lobby</a>
        <a class="btn btn-ghost" href="{{ route('deposits.create') }}">Novo depósito</a>
      </div>
    @else
      <p class="deposit__hint" id="pix-timer">
        Tempo restante: <strong><span id="pix-countdown">{{ $secondsLeft ?? 0 }}</span>s</strong>
      </p>

      @if ($qrUrl)
      <div class="deposit__qr">
        <img src="{{ $qrUrl }}" alt="QR Code PIX" width="260" height="260">
      </div>
      @endif

      <label class="deposit__label" for="pix-copy">PIX copia e cola</label>
      <div class="deposit__copy-row">
        <textarea id="pix-copy" class="deposit__copy" readonly rows="4">{{ $deposit->pix_copy }}</textarea>
        <button type="button" class="btn btn-gold deposit__copy-btn" id="copy-pix">Copiar</button>
      </div>
      <p class="deposit__hint" id="copy-feedback" hidden>Código copiado!</p>
      <p class="deposit__hint">O saldo é creditado automaticamente após a confirmação do pagamento.</p>

      @if ($canVerifyPayment ?? false)
      <form method="POST" action="{{ route('deposits.verify', $deposit) }}" class="deposit__confirm" id="verify-payment-form">
        @csrf
        <button type="submit" class="btn btn-gold deposit__submit">Verificar pagamento</button>
      </form>
      @endif

      @if ($allowLocalConfirm ?? false)
      <form method="POST" action="{{ route('deposits.confirm', $deposit) }}" class="deposit__confirm">
        @csrf
        <button type="submit" class="btn btn-gold deposit__submit">Já paguei — creditar (somente local)</button>
      </form>
      @endif
    @endif
  </div>
</section>
@endsection

@push('head')
  <link rel="stylesheet" href="{{ asset('css/deposit.css') }}">
@endpush

@push('scripts')
<script>
  (function () {
    const btn = document.getElementById('copy-pix');
    const area = document.getElementById('pix-copy');
    const feedback = document.getElementById('copy-feedback');
    if (btn && area) {
      btn.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(area.value);
        } catch (e) {
          area.select();
          document.execCommand('copy');
        }
        if (feedback) {
          feedback.hidden = false;
          setTimeout(() => { feedback.hidden = true; }, 2000);
        }
      });
    }

    const countdownEl = document.getElementById('pix-countdown');
    let left = {{ (int) ($secondsLeft ?? 0) }};
    if (countdownEl && left > 0) {
      const tick = () => {
        left -= 1;
        if (left <= 0) {
          countdownEl.textContent = '0';
          window.location.reload();
          return;
        }
        countdownEl.textContent = String(left);
        window.setTimeout(tick, 1000);
      };
      window.setTimeout(tick, 1000);
    }
  })();
</script>
@endpush
