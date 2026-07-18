@extends('layouts.app')

@section('title', 'Tigre Aviator — LESTBET 369')
@section('description', 'Tigre Aviator na LESTBET 369. Entre, voe com o multiplicador e saque antes do crash.')

@section('content')
<section class="game-hero">
  <div class="game-hero__bg" aria-hidden="true">
    {{-- Troque o arquivo: public/images/placeholders/hero-bg.png --}}
    <img src="{{ asset('images/placeholders/hero-bg.png') }}" alt="" class="game-hero__bg-img">
    <div class="game-hero__veil"></div>
  </div>

  <div class="game-hero__curtains" aria-hidden="true">
    <span class="curtain curtain--left"></span>
    <span class="curtain curtain--right"></span>
  </div>

  <div class="game-hero__fx" aria-hidden="true">
    <span class="coin coin-a"></span>
    <span class="coin coin-b"></span>
    <span class="coin coin-c"></span>
  </div>

  <div class="game-hero__stage">
    <p class="game-hero__kicker">LESTBET 369</p>

    <div class="game-hero__logo-wrap">
      <img
        class="game-hero__logo"
        src="{{ asset('images/games/tigre-aviator-logo.png') }}"
        alt="Tigre Aviator"
        width="520"
        height="520"
      >
    </div>

    <div class="marquee-pot" aria-live="polite">
      <span class="marquee-pot__label">Multiplicador ao vivo</span>
      <strong class="marquee-pot__value" data-multiplier>1.00<span>x</span></strong>
    </div>

    <p class="game-hero__tagline">Voe com o tigre. Saque antes do crash.</p>

    <div class="game-hero__cta">
      @auth
        <a class="btn btn-play" href="{{ route('games.show', 'tigre-aviator') }}">Jogar agora</a>
      @else
        <a class="btn btn-play" href="{{ route('login') }}">Jogar agora</a>
      @endauth
    </div>
  </div>
</section>

<section class="promo-band" id="bonus">
  {{-- Troque o arquivo: public/images/placeholders/banner-bonus.png --}}
  <div class="promo-band__frame">
    <img
      src="{{ asset('images/placeholders/banner-bonus.png') }}"
      alt="Bônus LESTBET 369"
      class="promo-band__img"
      width="1600"
      height="500"
    >
    <div class="promo-band__copy">
      <p class="promo-band__kicker">Bônus LESTBET 369</p>
      <h2>Todo dia tem prêmio para voar mais alto</h2>
      <ul class="bonus-list">
        <li>
          <strong>Bônus diários</strong>
          <span>Entre todos os dias e resgate recompensas.</span>
        </li>
        <li>
          <strong>Bônus de cadastro</strong>
          <span>Crie sua conta e ganhe para o primeiro voo.</span>
        </li>
        <li>
          <strong>Bônus por depósito</strong>
          <span>Depositou, ganhou saldo extra para jogar.</span>
        </li>
      </ul>
      @auth
        <a class="btn btn-gold" href="{{ route('games.show', 'tigre-aviator') }}">Resgatar e jogar</a>
      @else
        <a class="btn btn-gold" href="{{ route('login') }}">Criar conta e resgatar</a>
      @endauth
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
  (() => {
    const el = document.querySelector('[data-multiplier]');
    if (!el) return;
    let value = 1;
    const tick = () => {
      value += 0.01 + Math.random() * 0.04;
      if (value > 9.5) value = 1;
      el.innerHTML = value.toFixed(2) + '<span>x</span>';
    };
    setInterval(tick, 120);
  })();
</script>
@endpush
