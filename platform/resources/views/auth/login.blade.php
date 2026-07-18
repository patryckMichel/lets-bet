@php
  $tab = old('_tab', request('tab', 'login'));
  $estados = [
    'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO',
  ];
@endphp

@extends('layouts.app')

@section('title', 'Entrar ou cadastrar — LESTBET 369')
@section('body_class', 'page-auth')

@section('content')
<section class="auth-hero">
  <div class="auth-hero__bg" aria-hidden="true">
    <img src="{{ asset('images/placeholders/hero-bg.png') }}" alt="" class="auth-hero__bg-img">
    <div class="auth-hero__veil"></div>
  </div>

  <div class="auth-panel">
    <div class="auth-panel__brand">
      <img
        src="{{ asset('images/games/tigre-aviator-logo.png') }}"
        alt="Tigre Aviator"
        class="auth-panel__logo"
        width="220"
        height="220"
      >
      <p class="auth-panel__kicker">LESTBET 369</p>
      <h1>Pronto para decolar?</h1>
      <p class="auth-panel__sub">Entre para jogar ou crie sua conta e garanta o bônus de cadastro.</p>
    </div>

    <div class="auth-panel__forms">
      <div class="auth-tabs" role="tablist" aria-label="Acesso">
        <button type="button" class="auth-tab {{ $tab === 'login' ? 'is-active' : '' }}" data-tab="login" role="tab" aria-selected="{{ $tab === 'login' ? 'true' : 'false' }}">
          Jogar / Entrar
        </button>
        <button type="button" class="auth-tab {{ $tab === 'register' ? 'is-active' : '' }}" data-tab="register" role="tab" aria-selected="{{ $tab === 'register' ? 'true' : 'false' }}">
          Cadastrar
        </button>
      </div>

      @if ($errors->any())
        <p class="auth-error">{{ $errors->first() }}</p>
      @endif

      <div class="auth-pane {{ $tab === 'login' ? 'is-active' : '' }}" data-pane="login" role="tabpanel">
        <form method="POST" action="{{ route('login.store') }}" class="auth-form">
          @csrf
          <input type="hidden" name="_tab" value="login">
          <label>
            <span>E-mail</span>
            <input type="email" name="email" value="{{ old('email') }}" required {{ $tab === 'login' ? 'autofocus' : '' }} autocomplete="username">
          </label>
          <label>
            <span>Senha</span>
            <input type="password" name="password" required autocomplete="current-password">
          </label>
          <label class="remember">
            <input type="checkbox" name="remember">
            <span>Manter conectado</span>
          </label>
          <button type="submit" class="btn btn-play btn-block">Entrar e jogar</button>
        </form>
        <p class="auth-switch">Ainda não tem conta? <button type="button" class="linkish" data-tab="register">Cadastre-se</button></p>
      </div>

      <div class="auth-pane {{ $tab === 'register' ? 'is-active' : '' }}" data-pane="register" role="tabpanel">
        <form method="POST" action="{{ route('register.store') }}" class="auth-form auth-form--register">
          @csrf
          <input type="hidden" name="_tab" value="register">

          <label>
            <span>Nome completo</span>
            <input type="text" name="name" value="{{ old('name') }}" required maxlength="120" {{ $tab === 'register' ? 'autofocus' : '' }}>
          </label>

          <label>
            <span>CPF</span>
            <input type="text" name="cpf" value="{{ old('cpf') }}" required maxlength="14" inputmode="numeric" placeholder="000.000.000-00" autocomplete="off">
          </label>

          <div class="auth-grid">
            <label>
              <span>Sexo</span>
              <select name="sexo" required>
                <option value="" disabled {{ old('sexo') ? '' : 'selected' }}>Selecione</option>
                <option value="masculino" @selected(old('sexo') === 'masculino')>Masculino</option>
                <option value="feminino" @selected(old('sexo') === 'feminino')>Feminino</option>
                <option value="outro" @selected(old('sexo') === 'outro')>Outro</option>
                <option value="nao_informar" @selected(old('sexo') === 'nao_informar')>Prefiro não informar</option>
              </select>
            </label>
            <label>
              <span>Data de nascimento</span>
              <input type="date" name="data_nascimento" value="{{ old('data_nascimento') }}" required max="{{ now()->subYears(18)->toDateString() }}">
            </label>
          </div>

          <div class="auth-grid">
            <label>
              <span>Estado (UF)</span>
              <select name="estado" id="reg-estado" required>
                <option value="" disabled {{ old('estado') ? '' : 'selected' }}>UF</option>
                @foreach ($estados as $uf)
                  <option value="{{ $uf }}" @selected(old('estado') === $uf)>{{ $uf }}</option>
                @endforeach
              </select>
            </label>
          </div>

          <label class="auth-city-combo">
            <span>Cidade</span>
            <div class="auth-combo" id="reg-cidade-combo">
              <input
                type="text"
                id="reg-cidade-input"
                class="auth-combo__input"
                placeholder="Selecione a UF e busque a cidade"
                autocomplete="off"
                autocapitalize="words"
                disabled
              >
              <input type="hidden" name="cidade" id="reg-cidade" value="{{ old('cidade') }}">
              <ul class="auth-combo__list" id="reg-cidade-list" hidden role="listbox"></ul>
            </div>
          </label>

          <label>
            <span>E-mail</span>
            <input type="email" name="email" value="{{ old('_tab') === 'register' ? old('email') : '' }}" required>
          </label>

          <label>
            <span>Código do afiliado <em class="auth-optional">(opcional)</em></span>
            <input type="text" name="affiliate_code" value="{{ old('affiliate_code', request('ref')) }}" maxlength="40" placeholder="Ex.: AFFXXXXXXXX" autocomplete="off">
            <small class="auth-hint">Com código válido você ganha +R$ 50 de bônus no primeiro depósito.</small>
          </label>

          <div class="auth-grid">
            <label>
              <span>Senha</span>
              <input type="password" name="password" required minlength="8">
            </label>
            <label>
              <span>Confirmar senha</span>
              <input type="password" name="password_confirmation" required minlength="8">
            </label>
          </div>

          <button type="submit" class="btn btn-gold btn-block">Criar conta e jogar</button>
        </form>
        <p class="auth-switch">Já tem conta? <button type="button" class="linkish" data-tab="login">Entrar</button></p>
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
  (() => {
    const tabs = document.querySelectorAll('[data-tab]');
    const panes = document.querySelectorAll('[data-pane]');
    const activate = (name) => {
      document.querySelectorAll('.auth-tab').forEach((tab) => {
        const on = tab.dataset.tab === name;
        tab.classList.toggle('is-active', on);
        tab.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      panes.forEach((pane) => pane.classList.toggle('is-active', pane.dataset.pane === name));
      const url = new URL(window.location.href);
      url.searchParams.set('tab', name);
      window.history.replaceState({}, '', url);
    };
    tabs.forEach((el) => el.addEventListener('click', () => activate(el.dataset.tab)));

    // Cidades por UF — combobox com busca
    const ufSelect = document.getElementById('reg-estado');
    const cityInput = document.getElementById('reg-cidade-input');
    const cityHidden = document.getElementById('reg-cidade');
    const cityList = document.getElementById('reg-cidade-list');
    const cityCombo = document.getElementById('reg-cidade-combo');
    const oldCity = @json(old('cidade'));
    let citiesByUf = null;
    let currentCities = [];

    const closeList = () => {
      cityList.hidden = true;
      cityList.innerHTML = '';
    };

    const pickCity = (name) => {
      cityInput.value = name;
      cityHidden.value = name;
      closeList();
    };

    const openList = (cities) => {
      cityList.innerHTML = '';
      if (!cities.length) {
        const empty = document.createElement('li');
        empty.className = 'auth-combo__empty';
        empty.textContent = 'Nenhuma cidade encontrada';
        cityList.appendChild(empty);
        cityList.hidden = false;
        return;
      }
      cities.slice(0, 80).forEach((name) => {
        const li = document.createElement('li');
        li.setAttribute('role', 'option');
        li.textContent = name;
        li.addEventListener('mousedown', (e) => {
          e.preventDefault();
          pickCity(name);
        });
        cityList.appendChild(li);
      });
      cityList.hidden = false;
    };

    const filterCities = () => {
      const q = (cityInput.value || '').trim().toLowerCase();
      cityHidden.value = '';
      if (!currentCities.length) return;
      const list = !q
        ? currentCities
        : currentCities.filter((c) => c.toLowerCase().includes(q));
      // Exact match keeps hidden value
      const exact = currentCities.find((c) => c.toLowerCase() === q);
      if (exact) cityHidden.value = exact;
      openList(list);
    };

    const loadUf = async (uf) => {
      if (!uf) return;
      cityInput.value = '';
      cityHidden.value = '';
      cityInput.disabled = true;
      cityInput.placeholder = 'Carregando cidades...';
      closeList();
      if (!citiesByUf) {
        const res = await fetch(@json(asset('data/br-cities-by-uf.json')));
        citiesByUf = await res.json();
      }
      currentCities = citiesByUf[uf] || [];
      cityInput.disabled = false;
      cityInput.placeholder = 'Digite para buscar a cidade';
      if (oldCity && currentCities.some((c) => c.toLowerCase() === oldCity.toLowerCase())) {
        const match = currentCities.find((c) => c.toLowerCase() === oldCity.toLowerCase());
        pickCity(match);
      }
    };

    ufSelect?.addEventListener('change', () => loadUf(ufSelect.value));
    cityInput?.addEventListener('input', filterCities);
    cityInput?.addEventListener('focus', () => {
      if (!cityInput.disabled && currentCities.length) filterCities();
    });
    cityInput?.addEventListener('blur', () => setTimeout(closeList, 150));

    document.querySelector('form.auth-form--register')?.addEventListener('submit', (e) => {
      if (!cityHidden.value) {
        e.preventDefault();
        cityInput.focus();
        cityInput.setCustomValidity('Selecione uma cidade da lista.');
        cityInput.reportValidity();
      } else {
        cityInput.setCustomValidity('');
      }
    });

    if (ufSelect?.value) {
      loadUf(ufSelect.value);
    }
  })();
</script>
@endpush
