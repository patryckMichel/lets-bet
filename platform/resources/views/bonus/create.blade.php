@extends('layouts.app')

@section('title', 'Resgatar bônus — LESTBET 369')
@section('body_class', 'page-deposit')

@section('content')
<section class="deposit withdraw">
  <header class="deposit__top">
    <a class="deposit__back" href="{{ route('lobby') }}">← Voltar</a>
  </header>

  <div class="withdraw-wallets" aria-label="Saldos">
    <article class="withdraw-card withdraw-card--real">
      <div class="withdraw-card__icon" aria-hidden="true">R$</div>
      <div class="withdraw-card__body">
        <span class="withdraw-card__label">Saldo total</span>
        <strong class="withdraw-card__value">R$ {{ number_format((float) $balance, 2, ',', '.') }}</strong>
        <span class="withdraw-card__tag">Real + bônus</span>
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
    <p class="deposit__kicker">Código promocional</p>
    <h1>Resgatar bônus</h1>
    <p class="deposit__sub">Digite um código de valor fixo para creditá-lo na sua conta. Cada código só pode ser usado uma vez.</p>

    @if (session('status'))
      <div class="deposit__alert deposit__alert--ok" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
      <div class="deposit__alert" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('bonus.store') }}" class="deposit__form">
      @csrf

      <label class="deposit__label" for="code">Código de bônus</label>
      <input
        class="deposit__input"
        id="code"
        name="code"
        type="text"
        maxlength="40"
        value="{{ old('code') }}"
        placeholder="Ex.: BONUS20"
        autocomplete="off"
        autocapitalize="characters"
        style="font-size:1rem;min-height:46px;letter-spacing:.04em;text-transform:uppercase"
        required
      >

      <p class="deposit__hint">Códigos de match % continuam sendo usados na tela de depósito.</p>

      <button type="submit" class="btn btn-gold deposit__submit">Resgatar bônus</button>
    </form>
  </div>

  <aside class="withdraw-promo" aria-label="Bônus nas redes">
    <div class="withdraw-promo__glow" aria-hidden="true"></div>
    <div class="withdraw-promo__content">
      <p class="withdraw-promo__eyebrow">Dica</p>
      <h2 class="withdraw-promo__title">Códigos exclusivos nas redes</h2>
      <p class="withdraw-promo__text">
        Novos códigos de bônus saem primeiro no Instagram e no TikTok. Siga @lestbet369 e não perca.
      </p>
    </div>
  </aside>
</section>
@endsection

@push('head')
  <link rel="stylesheet" href="{{ asset('css/deposit.css') }}">
@endpush
