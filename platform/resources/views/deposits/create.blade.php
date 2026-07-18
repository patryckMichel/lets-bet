@extends('layouts.app')

@section('title', 'Adicionar saldo — LESTBET 369')
@section('body_class', 'page-deposit')

@section('content')
<section class="deposit">
  <header class="deposit__top">
    <a class="deposit__back" href="{{ route('lobby') }}">← Voltar</a>
    <div class="deposit__balance">
      <span>Saldo atual</span>
      <strong>$ {{ number_format((float) $balance, 2, '.', ',') }}</strong>
    </div>
  </header>

  <div class="deposit__card">
    <p class="deposit__kicker">Depósito via PIX</p>
    <h1>Adicionar saldo</h1>
    <p class="deposit__sub">Escolha o valor. Em seguida você verá o QR Code e o código copia e cola.</p>

    @if ($errors->any())
      <div class="deposit__alert" role="alert">
        {{ $errors->first() }}
      </div>
    @endif

    <form method="POST" action="{{ route('deposits.store') }}" class="deposit__form" id="deposit-form">
      @csrf

      <div class="deposit__presets" role="group" aria-label="Valores rápidos">
        @foreach ($presets as $preset)
          <button type="button" class="deposit__preset" data-amount="{{ $preset }}">
            $ {{ number_format($preset, 0, ',', '.') }}
          </button>
        @endforeach
      </div>

      <label class="deposit__label" for="amount">Valor do depósito ($)</label>
      <input
        class="deposit__input"
        id="amount"
        name="amount"
        type="number"
        inputmode="decimal"
        min="{{ $min }}"
        max="{{ $max }}"
        step="0.01"
        value="{{ old('amount', 50) }}"
        required
      >

      <p class="deposit__hint">Mínimo $ {{ number_format($min, 2, ',', '.') }} · Máximo $ {{ number_format($max, 2, ',', '.') }}</p>

      @if ($needsCpf ?? false)
      <label class="deposit__label" for="cpf">CPF (obrigatório para PIX)</label>
      <input
        class="deposit__input"
        id="cpf"
        name="cpf"
        type="text"
        inputmode="numeric"
        maxlength="14"
        value="{{ old('cpf') }}"
        placeholder="000.000.000-00"
        required
        style="font-size:1rem;min-height:46px"
      >
      <p class="deposit__hint">O Asaas exige CPF do pagador para gerar a cobrança.</p>
      @endif

      <label class="deposit__label" for="bonus_code">Código de bônus (opcional)</label>
      <input
        class="deposit__input"
        id="bonus_code"
        name="bonus_code"
        type="text"
        value="{{ old('bonus_code') }}"
        style="font-size:1rem;min-height:46px"
        maxlength="40"
      >

      <button type="submit" class="btn btn-gold deposit__submit">Gerar PIX</button>
    </form>
  </div>
</section>
@endsection

@push('head')
  <link rel="stylesheet" href="{{ asset('css/deposit.css') }}">
@endpush

@push('scripts')
<script>
  (function () {
    const input = document.getElementById('amount');
    document.querySelectorAll('.deposit__preset').forEach((btn) => {
      btn.addEventListener('click', () => {
        input.value = btn.dataset.amount;
        document.querySelectorAll('.deposit__preset').forEach((b) => b.classList.remove('is-active'));
        btn.classList.add('is-active');
      });
    });
  })();
</script>
@endpush
