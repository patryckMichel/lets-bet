@extends('layouts.app')

@section('title', $game->name.' — LESTBET 369')
@section('body_class', 'page-crash')

@section('content')
@if (! $game->isPlayable())
  <section class="game-shell">
    <div class="game-stage-inner" style="text-align:center;padding:3rem 1rem">
      <h2>{{ $game->name }}</h2>
      <p class="muted">Este jogo ainda não está disponível.</p>
      <a class="btn btn-secondary" href="{{ route('lobby') }}">Voltar ao lobby</a>
    </div>
  </section>
@else
<section
  class="aviator"
  id="aviator-app"
  data-state-url="{{ route('crash.state') }}"
  data-bet-url="{{ route('crash.bet') }}"
  data-cashout-url="{{ route('crash.cashout') }}"
  data-csrf="{{ csrf_token() }}"
>
  <header class="aviator__top">
    <a class="aviator__brand" href="{{ route('home') }}">
      <img src="{{ asset('images/games/tigre-aviator-logo.png') }}" alt="Tigre Aviator" width="42" height="42">
      <span>Tigre Aviator</span>
    </a>
    <div class="aviator__balance">
      <span>Saldo</span>
      <strong id="av-balance">$ 0.00</strong>
    </div>
    <a class="aviator__add" href="{{ route('deposits.create') }}">+ Saldo</a>
    <a class="aviator__back" href="{{ route('lobby') }}">Lobby</a>
  </header>

  <div class="aviator__history" id="av-history" aria-label="Histórico de crashes"></div>

  <div class="aviator__stage" id="av-stage">
    <div class="aviator__sky" aria-hidden="true"></div>
    <div class="aviator__trail" id="av-trail" aria-hidden="true"></div>
    <img class="aviator__plane" id="av-plane" src="{{ asset('images/games/tigre-aviator-logo.png') }}" alt="" width="72" height="72">
    <div class="aviator__center">
      <p class="aviator__status" id="av-status">Preparando...</p>
      <strong class="aviator__mult" id="av-mult">1.00<span>x</span></strong>
    </div>
    <div class="aviator__players" id="av-players">0 jogadores</div>
  </div>

  <div class="aviator__panels">
    @foreach ([1, 2] as $slot)
      <div class="bet-panel" data-slot="{{ $slot }}">
        <div class="bet-panel__tabs">
          <button type="button" class="is-active" data-mode="manual">Aposta</button>
          <button type="button" data-mode="auto">Auto</button>
        </div>
        <div class="bet-panel__body">
          <div class="bet-panel__left">
            <div class="bet-amount">
              <button type="button" data-adj="-1">−</button>
              <input type="number" inputmode="decimal" min="1" step="1" value="1.00" data-amount>
              <button type="button" data-adj="1">+</button>
            </div>
            <div class="bet-presets">
              <button type="button" data-preset="1">1</button>
              <button type="button" data-preset="5">5</button>
              <button type="button" data-preset="10">10</button>
              <button type="button" data-preset="50">50</button>
            </div>
            <label class="bet-auto hidden" data-auto-wrap>
              <span>Auto cash out</span>
              <input type="number" inputmode="decimal" min="1.01" step="0.01" value="2.00" data-auto>
            </label>
          </div>
          <button type="button" class="bet-action bet-action--bet" data-action>
            <span data-action-label>Aposta {{ $slot }}</span>
            <strong data-action-amount>$ 1.00</strong>
          </button>
        </div>
      </div>
    @endforeach
  </div>

  <div class="aviator__feed">
    <div class="aviator__feed-tabs">
      <button type="button" class="is-active">Apostas</button>
    </div>
    <div class="aviator__feed-meta" id="av-feed-meta">0 apostas</div>
    <div class="aviator__feed-table">
      <div class="aviator__feed-head">
        <span>Jogador</span>
        <span>Aposta</span>
        <span>X</span>
        <span>Prêmio</span>
      </div>
      <div id="av-feed-rows"></div>
    </div>
  </div>
</section>
@endif
@endsection

@push('head')
  <link rel="stylesheet" href="{{ asset('css/aviator.css') }}">
@endpush

@push('scripts')
  <script src="{{ asset('js/aviator.js') }}" defer></script>
@endpush
