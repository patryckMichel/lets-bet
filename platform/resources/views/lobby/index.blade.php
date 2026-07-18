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
        <p class="lobby__kicker">Jogo principal</p>
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
@endpush
