@extends('layouts.app')

@section('title', 'Solicitar saque — LESTBET 369')
@section('body_class', 'page-deposit')

@section('content')
@php
  $wageringMet = (bool) ($wagering['met'] ?? true);
  $canWithdraw = (float) $realBalance >= (float) $minWithdrawal && $wageringMet;
  $minLabel = 'R$ '.number_format((float) $minWithdrawal, 2, ',', '.');
@endphp

<section class="deposit withdraw">
  <header class="deposit__top">
    <a class="deposit__back" href="{{ route('lobby') }}">← Voltar</a>
    <span class="withdraw-min-chip">Saque mínimo {{ $minLabel }}</span>
  </header>

  <div class="withdraw-wallets" aria-label="Saldos">
    <article class="withdraw-card withdraw-card--real">
      <div class="withdraw-card__icon" aria-hidden="true">R$</div>
      <div class="withdraw-card__body">
        <span class="withdraw-card__label">Saldo real</span>
        <strong class="withdraw-card__value">R$ {{ number_format((float) $realBalance, 2, ',', '.') }}</strong>
        <span class="withdraw-card__tag">Disponível para saque</span>
      </div>
    </article>

    <article class="withdraw-card withdraw-card--bonus">
      <div class="withdraw-card__icon" aria-hidden="true">B</div>
      <div class="withdraw-card__body">
        <span class="withdraw-card__label">Saldo bônus</span>
        <strong class="withdraw-card__value">R$ {{ number_format((float) $bonusBalance, 2, ',', '.') }}</strong>
        <span class="withdraw-card__tag">Não sacável</span>
      </div>
    </article>
  </div>

  <div class="deposit__card withdraw-panel">
    <p class="deposit__kicker">Retirada</p>
    <h1>Solicitar saque</h1>
    <p class="withdraw-min-line">Saque mínimo {{ $minLabel }}</p>

    <div class="withdraw-rollover" style="margin:1rem 0;padding:0.85rem 1rem;border:1px solid rgba(255,214,130,0.22);border-radius:14px;">
      <div style="display:flex;justify-content:space-between;gap:0.5rem;font-size:0.85rem;margin-bottom:0.45rem;">
        <strong>Rollover</strong>
        <span>R$ {{ number_format((float) $wagering['progress'], 2, ',', '.') }} / R$ {{ number_format((float) $wagering['required'], 2, ',', '.') }}</span>
      </div>
      <div style="height:8px;background:rgba(255,255,255,0.08);border-radius:99px;overflow:hidden;">
        <div style="height:100%;width:{{ $wagering['percent'] }}%;background:#f6c84c;"></div>
      </div>
      @if (! $wageringMet)
        <p class="deposit__hint" style="margin-top:0.55rem;">
          Faltam R$ {{ number_format((float) $wagering['remaining'], 2, ',', '.') }} em apostas ({{ number_format((float) $wagering['multiplier'], 0) }}× depósito+bônus) para liberar o saque.
        </p>
      @else
        <p class="deposit__hint" style="margin-top:0.55rem;">Rollover completo — saque liberado (respeitando o mínimo).</p>
      @endif
    </div>

    @if (session('status'))
      <div class="deposit__alert deposit__alert--ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
      <div class="deposit__alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('withdrawals.store') }}" class="deposit__form">
      @csrf
      <label class="deposit__label" for="amount">Valor do saque</label>
      <input
        class="deposit__input"
        id="amount"
        name="amount"
        type="number"
        min="{{ number_format((float) $minWithdrawal, 2, '.', '') }}"
        max="{{ number_format((float) $realBalance, 2, '.', '') }}"
        step="0.01"
        value="{{ old('amount') }}"
        placeholder="{{ number_format((float) $minWithdrawal, 2, '.', '') }}"
        @disabled(! $canWithdraw)
        @required($canWithdraw)
      >
      <p class="deposit__hint">
        Disponível: R$ {{ number_format((float) $realBalance, 2, ',', '.') }}
      </p>

      <label class="deposit__label" for="pix_key">Chave PIX</label>
      <input
        class="deposit__input"
        id="pix_key"
        name="pix_key"
        type="text"
        value="{{ old('pix_key') }}"
        placeholder="CPF, e-mail, telefone ou chave aleatória"
        style="font-size:1rem"
        @disabled(! $canWithdraw)
        @required($canWithdraw)
      >

      <button type="submit" class="btn btn-gold deposit__submit" @disabled(! $canWithdraw)>
        Solicitar saque
      </button>
    </form>

    @if ($pending->isNotEmpty())
      <div class="withdraw-pending">
        <p class="deposit__label">Pendentes</p>
        <div class="withdraw-pending__list">
          @foreach ($pending as $w)
            <div class="withdraw-pending__item">
              <strong>R$ {{ number_format((float) $w->amount, 2, ',', '.') }}</strong>
              <span>{{ $w->created_at?->format('d/m H:i') }}</span>
            </div>
          @endforeach
        </div>
      </div>
    @endif
  </div>

  <aside class="withdraw-promo" aria-label="Transforme seu bônus em dinheiro real">
    <div class="withdraw-promo__glow" aria-hidden="true"></div>
    <div class="withdraw-promo__content">
      <p class="withdraw-promo__eyebrow">Bônus</p>
      <h2 class="withdraw-promo__title">Transforme seu Bônus em Dinheiro Real</h2>
      <p class="withdraw-promo__text">
        Lucros vão para o saldo real, mas o saque só libera após cumprir o rollover (apostas sobre depósito + bônus).
      </p>
    </div>
  </aside>
</section>
@endsection

@push('head')
  <link rel="stylesheet" href="{{ asset('css/deposit.css') }}">
@endpush
