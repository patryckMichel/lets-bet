@extends('layouts.app')

@section('title', 'Lobby — LESTBET 369')
@section('body_class', 'page-lobby')

@section('content')
<section class="lobby">
  <header class="lobby__top">
    <a class="lobby__brand" href="{{ route('home') }}">
      <img src="{{ asset('images/games/tigre-aviator-logo.png') }}" alt="LESTBET 369" width="40" height="40">
      <span>LESTBET 369</span>
    </a>
    <div class="lobby__balance">
      <span>Saldo</span>
      <strong>$ {{ number_format((float) auth()->user()->total_balance, 2, '.', ',') }}</strong>
    </div>
    <a class="lobby__add" href="{{ route('deposits.create') }}">+ Saldo</a>
    <a class="lobby__add" href="{{ route('withdrawals.create') }}" style="background:transparent;border:1px solid rgba(246,200,76,.45);color:#ffe7a3">Sacar</a>
    @if (auth()->user()->is_admin)
      <a class="lobby__add" href="{{ route('admin.dashboard') }}" style="background:#c41224;color:#fff">Admin</a>
    @endif
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="lobby__logout">Sair</button>
    </form>
  </header>

  @if ($game)
  <a
    class="lobby__hero {{ $game->isPlayable() ? '' : 'is-locked' }}"
    href="{{ $game->isPlayable() ? route('games.show', $game->slug) : '#' }}"
    @unless($game->isPlayable()) aria-disabled="true" @endunless
  >
    <div class="lobby__hero-bg" aria-hidden="true">
      <img src="{{ asset('images/placeholders/hero-bg.png') }}" alt="">
    </div>
    <div class="lobby__hero-content">
      <img
        class="lobby__hero-logo"
        src="{{ asset($game->thumbnail ?: 'images/games/tigre-aviator-logo.png') }}"
        alt="{{ $game->name }}"
        width="120"
        height="120"
      >
      <div class="lobby__hero-copy">
        <p class="lobby__kicker">Destaque</p>
        <h1>{{ $game->name }}</h1>
        <p>{{ $game->short_description }}</p>
        @if ($game->isPlayable())
          <span class="lobby__cta">Jogar agora</span>
        @else
          <span class="lobby__cta lobby__cta--soon">{{ $game->statusLabel() }}</span>
        @endif
      </div>
    </div>
  </a>
  @endif

  <div class="lobby__games">
    <h2 class="lobby__games-title">Jogos</h2>
    <div class="lobby__games-grid">
      @foreach ($games as $g)
        <a
          class="lobby__game-card {{ $g->isPlayable() ? '' : 'is-locked' }}"
          href="{{ $g->isPlayable() ? route('games.show', $g->slug) : '#' }}"
        >
          <img src="{{ asset($g->thumbnail ?: 'images/games/tigre-aviator-logo.png') }}" alt="{{ $g->name }}">
          <div>
            <strong>{{ $g->name }}</strong>
            <span>{{ $g->isPlayable() ? 'Jogar' : $g->statusLabel() }}</span>
          </div>
        </a>
      @endforeach
    </div>
  </div>

  <div class="lobby__social">
    <p>Bônus surpresa nas redes</p>
    <div class="lobby__social-links">
      <a href="https://www.instagram.com/lestbet369/" target="_blank" rel="noopener noreferrer">Instagram</a>
      <a href="https://www.tiktok.com/@lestbet_" target="_blank" rel="noopener noreferrer">TikTok</a>
    </div>
  </div>
</section>
@endsection

@push('head')
  <link rel="stylesheet" href="{{ asset('css/lobby.css') }}">
  <style>
    .lobby__games { margin-top: .5rem; }
    .lobby__games-title {
      margin: 0 0 .55rem;
      font-size: .95rem;
      color: rgba(255,231,163,.9);
      font-family: "Bricolage Grotesque", Georgia, serif;
    }
    .lobby__games-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: .55rem;
    }
    .lobby__game-card {
      display: flex;
      flex-direction: column;
      gap: .45rem;
      text-decoration: none;
      color: #fff;
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(246,200,76,.22);
      border-radius: 16px;
      padding: .65rem;
    }
    .lobby__game-card img {
      width: 100%;
      aspect-ratio: 1;
      object-fit: cover;
      border-radius: 12px;
      background: #140810;
    }
    .lobby__game-card strong { display: block; font-size: .92rem; }
    .lobby__game-card span { font-size: .75rem; color: #f6c84c; }
    .lobby__game-card.is-locked { opacity: .55; pointer-events: none; }
  </style>
@endpush
