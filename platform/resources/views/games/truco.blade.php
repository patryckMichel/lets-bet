@extends('layouts.app')

@section('title', $game->name.' — LESTBET 369')
@section('body_class', 'page-truco')

@section('content')
<div id="truco-app"
     data-start-url="{{ route('truco.start') }}"
     data-join-url="{{ route('truco.join') }}"
     data-csrf="{{ csrf_token() }}"
     data-stakes='@json($stakes)'>

  <header class="truco-top">
    <a href="{{ route('lobby') }}" class="truco-back">← Lobby</a>
    <strong>Tigre do Truco</strong>
    <button type="button" class="truco-menu-btn" id="btn-leave" hidden>Sair</button>
  </header>

  <section id="truco-hub" class="truco-hub">
    <img class="truco-hub__art" src="{{ asset('images/games/tigre-truco.png') }}" alt="Tigre do Truco">
    <h1>Tigre do Truco</h1>
    <p>1x1 rápido ou 2x2 com amigo. Partida até 12 pontos.</p>

    <div class="truco-modes">
      <button type="button" class="truco-mode is-active" data-mode="1v1">1x1 rápido</button>
      <button type="button" class="truco-mode" data-mode="2v2">2x2 sala</button>
    </div>

    <div id="hub-2v2-extra" class="truco-2v2-extra" hidden>
      <label class="truco-label">Entrar com código do amigo</label>
      <div class="truco-join-row">
        <input type="text" id="join-code" class="truco-input" maxlength="8" placeholder="Código">
        <button type="button" class="truco-cta truco-cta--sm" id="btn-join">Entrar</button>
      </div>
      <p class="truco-or">ou crie uma sala abaixo</p>
    </div>

    <p class="truco-label">Valor de entrada</p>
    <div class="truco-stakes" id="truco-stakes"></div>
    <p id="truco-hub-error" class="truco-error" hidden></p>
    <button type="button" class="truco-cta" id="truco-start" disabled>Jogar</button>
  </section>

  <section id="truco-lobby-room" class="truco-hub" hidden>
    <h2>Sala 2x2</h2>
    <p>Código: <strong id="room-code" class="truco-code"></strong></p>
    <p class="truco-hint">Envie o código ao parceiro. Adversários são preenchidos automaticamente.</p>
    <ul id="room-seats" class="truco-room-seats"></ul>
    <p id="room-msg" class="truco-msg"></p>
    <button type="button" class="truco-cta" id="btn-start-room" hidden>Iniciar partida</button>
    <button type="button" class="truco-cta truco-cta--ghost" id="btn-leave-room">Sair da sala</button>
  </section>

  <section id="truco-table" class="truco-table" hidden>
    <div class="truco-score">
      <div class="truco-score__side">
        <span>nós</span>
        <div class="truco-dots" id="dots-us"></div>
        <strong id="score-us">0</strong>
      </div>
      <div class="truco-hand-val">vale <span id="hand-value">1</span></div>
      <div class="truco-score__side truco-score__side--right">
        <span>eles</span>
        <div class="truco-dots" id="dots-them"></div>
        <strong id="score-them">0</strong>
      </div>
    </div>

    <div class="truco-felt">
      <div class="truco-seat truco-seat--top" data-seat-slot="top">
        <div class="truco-avatar" id="avatar-top"><span class="truco-avatar__name">…</span><span class="truco-react" hidden></span></div>
        <div class="truco-backs" id="backs-top"></div>
      </div>
      <div class="truco-seat truco-seat--left" data-seat-slot="left">
        <div class="truco-avatar" id="avatar-left"><span class="truco-avatar__name">…</span><span class="truco-react" hidden></span></div>
        <div class="truco-backs" id="backs-left"></div>
      </div>
      <div class="truco-seat truco-seat--right" data-seat-slot="right">
        <div class="truco-avatar" id="avatar-right"><span class="truco-avatar__name">…</span><span class="truco-react" hidden></span></div>
        <div class="truco-backs" id="backs-right"></div>
      </div>

      <div class="truco-center">
        <div class="truco-vira-box">
          <span>Vira</span>
          <div id="vira-card"></div>
        </div>
        <div class="truco-manilhas-box">
          <span>MANILHAS</span>
          <div id="manilhas" class="truco-manilhas__row"></div>
        </div>
        <div class="truco-played" id="table-cards"></div>
        <p class="truco-toast" id="truco-msg"></p>
      </div>

      <div class="truco-seat truco-seat--me" data-seat-slot="me">
        <div class="truco-avatar is-me" id="avatar-me"><span class="truco-avatar__name">Você</span><span class="truco-react" hidden></span></div>
        <div class="truco-hand" id="my-hand"></div>
      </div>
    </div>

    <div class="truco-bottom-bar">
      <div class="truco-emojis" id="emoji-bar">
        <button type="button" data-emoji="😀">😀</button>
        <button type="button" data-emoji="😎">😎</button>
        <button type="button" data-emoji="🔥">🔥</button>
        <button type="button" data-emoji="😤">😤</button>
      </div>
      <div class="truco-actions-wrap">
        <p class="truco-ask" id="action-label">O que deseja fazer?</p>
        <div class="truco-actions" id="truco-actions"></div>
      </div>
    </div>
  </section>

  <div id="truco-result" class="truco-result" hidden>
    <div class="truco-result__box">
      <h2 id="result-title">Fim</h2>
      <p id="result-body"></p>
      <button type="button" class="truco-cta" id="result-again">Jogar de novo</button>
      <a class="truco-link" href="{{ route('lobby') }}">Voltar ao lobby</a>
    </div>
  </div>
</div>
@endsection

@push('head')
  <link rel="stylesheet" href="{{ asset('css/truco.css') }}">
@endpush

@push('scripts')
  <script src="{{ asset('js/truco.js') }}" defer></script>
@endpush
