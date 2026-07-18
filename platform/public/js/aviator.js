(() => {
  const root = document.getElementById('aviator-app');
  if (!root) return;

  const stateUrl = root.dataset.stateUrl;
  const betUrl = root.dataset.betUrl;
  const cashoutUrl = root.dataset.cashoutUrl;
  const csrf = root.dataset.csrf;

  const elBalance = document.getElementById('av-balance');
  const elHistory = document.getElementById('av-history');
  const elStatus = document.getElementById('av-status');
  const elMult = document.getElementById('av-mult');
  const elPlayers = document.getElementById('av-players');
  const elStage = document.getElementById('av-stage');
  const elPlane = document.getElementById('av-plane');
  const elTrail = document.getElementById('av-trail');
  const elFeedMeta = document.getElementById('av-feed-meta');
  const elFeedRows = document.getElementById('av-feed-rows');

  let state = null;
  let busy = { 1: false, 2: false };
  let polling = false;
  let receivedAt = Date.now();
  let clockSkew = 0; // serverNow - clientNow at receive time
  let rafId = 0;
  let displayMult = 1;

  const money = (n) => `$ ${Number(n || 0).toFixed(2)}`;

  const histClass = (x) => {
    if (x >= 10) return 'is-high';
    if (x >= 2) return 'is-mid';
    return 'is-low';
  };

  async function api(url, options = {}) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 8000);
    try {
      const res = await fetch(url, {
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
          ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        },
        credentials: 'same-origin',
        signal: controller.signal,
        ...options,
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        throw new Error(data.message || 'Falha na requisição');
      }
      return data;
    } finally {
      clearTimeout(timer);
    }
  }

  function localMultiplier() {
    const round = state?.round;
    if (!round) return 1;
    if (round.status === 'crashed') return Number(round.crash_point || round.multiplier || 1);
    if (round.status !== 'running' || !round.started_at) return 1;

    const growth = Number(round.growth_rate || 0.00006);
    const maxMult = Number(round.max_multiplier || 100);
    const nowApprox = Date.now() + clockSkew;
    const started = new Date(round.started_at).getTime();
    const elapsed = Math.max(0, nowApprox - started);
    const mult = Math.exp(growth * elapsed);
    return Math.min(maxMult, Math.floor(mult * 100) / 100);
  }

  function renderHistory(history) {
    elHistory.innerHTML = (history || [])
      .map((h) => `<span class="hist-chip ${histClass(h.crash_point)}">${h.crash_point.toFixed(2)}x</span>`)
      .join('');
  }

  function renderFeed(bets, feed) {
    const list = bets || [];
    const label = feed?.label || `${list.length} apostas`;
    const won = feed?.total_won ? ` · $ ${Number(feed.total_won).toFixed(2)} ganho` : '';
    elFeedMeta.textContent = `${label}${won}`;
    elFeedRows.innerHTML = list
      .map((b) => {
        const wonRow = b.status === 'cashed_out';
        return `<div class="feed-row ${wonRow ? 'is-won' : ''}">
          <span>${b.player}</span>
          <span>${money(b.amount)}</span>
          <span class="x">${b.multiplier ? b.multiplier.toFixed(2) + 'x' : '—'}</span>
          <span>${b.payout != null ? money(b.payout) : '—'}</span>
        </div>`;
      })
      .join('');
  }

  function setMult(value, crashed = false) {
    displayMult = Number(value) || 1;
    elMult.innerHTML = `${displayMult.toFixed(2)}<span>x</span>`;
    elStage.classList.toggle('is-crashed', crashed);
  }

  function flyVisual(multiplier, status) {
    if (status !== 'running') {
      elPlane.style.transform = 'translate(0, 0) rotate(-8deg)';
      elTrail.style.width = '0';
      elTrail.style.opacity = '0';
      return;
    }
    const t = Math.min(1, Math.log(Math.max(1.01, multiplier)) / Math.log(50));
    const x = t * 220;
    const y = t * -120;
    elPlane.style.transform = `translate(${x}px, ${y}px) rotate(-12deg)`;
    elTrail.style.opacity = '1';
    elTrail.style.width = `${40 + t * 220}px`;
  }

  function canCashout(slot) {
    const my = state?.my_bets?.[slot] || state?.my_bets?.[String(slot)];
    const round = state?.round;
    if (!my || my.status !== 'active' || !round) return false;
    return round.status === 'running';
  }

  function panelState(slot) {
    const my = state?.my_bets?.[slot] || state?.my_bets?.[String(slot)];
    const round = state?.round;
    const panel = root.querySelector(`.bet-panel[data-slot="${slot}"]`);
    if (!panel || !round) return;

    const btn = panel.querySelector('[data-action]');
    const label = panel.querySelector('[data-action-label]');
    const amountEl = panel.querySelector('[data-action-amount]');
    const amountInput = panel.querySelector('[data-amount]');
    const amount = Number(amountInput.value || 0);
    const liveMult = localMultiplier();

    btn.classList.remove('bet-action--bet', 'bet-action--cashout', 'bet-action--waiting');

    if (my && my.status === 'active' && round.status === 'running') {
      btn.classList.add('bet-action--cashout');
      label.textContent = 'Cash Out';
      amountEl.textContent = money(Number(my.amount) * liveMult);
      btn.disabled = !!busy[slot];
      return;
    }

    if (my && my.status === 'cashed_out') {
      btn.classList.add('bet-action--waiting');
      label.textContent = `Saiu ${Number(my.cashout_multiplier).toFixed(2)}x`;
      amountEl.textContent = money(my.payout);
      btn.disabled = true;
      return;
    }

    if (my && my.status === 'lost') {
      btn.classList.add('bet-action--waiting');
      label.textContent = 'Perdeu';
      amountEl.textContent = money(my.amount);
      btn.disabled = true;
      return;
    }

    if (my && my.status === 'active' && round.status === 'waiting') {
      btn.classList.add('bet-action--waiting');
      label.textContent = 'Aguardando decolagem';
      amountEl.textContent = money(my.amount);
      btn.disabled = true;
      return;
    }

    if (round.status === 'waiting') {
      btn.classList.add('bet-action--bet');
      label.textContent = `Aposta ${slot}`;
      amountEl.textContent = money(amount);
      btn.disabled = !!busy[slot] || amount < 1;
      return;
    }

    btn.classList.add('bet-action--waiting');
    label.textContent = 'Aguarde';
    amountEl.textContent = money(amount);
    btn.disabled = true;
  }

  function render(next, { soft = false } = {}) {
    state = next;
    receivedAt = Date.now();
    if (next.server_now) {
      clockSkew = new Date(next.server_now).getTime() - receivedAt;
    }

    if (elBalance) elBalance.textContent = money(next.balance);
    if (!soft) {
      renderHistory(next.history);
      renderFeed(next.bets, next.feed);
    }

    const round = next.round;
    const crashed = round.status === 'crashed';
    const mult = crashed ? Number(round.crash_point || round.multiplier || 1) : localMultiplier();
    setMult(mult, crashed);
    flyVisual(mult, round.status);
    elPlayers.textContent = `${Number(round.players || 0).toLocaleString('pt-BR')} jogadores`;

    if (round.status === 'waiting') {
      const sec = Math.ceil((round.betting_ms_left || 0) / 1000);
      elStatus.textContent = sec > 0 ? `Apostas abertas · ${sec}s` : 'Decolando...';
    } else if (round.status === 'running') {
      elStatus.textContent = 'Voo em andamento — clique em Cash Out';
    } else {
      elStatus.textContent = `Crashou @ ${Number(round.crash_point).toFixed(2)}x`;
    }

    panelState(1);
    panelState(2);
  }

  function tickFrame() {
    if (state?.round?.status === 'running') {
      const mult = localMultiplier();
      setMult(mult, false);
      flyVisual(mult, 'running');
      panelState(1);
      panelState(2);
    }
    rafId = requestAnimationFrame(tickFrame);
  }

  async function poll() {
    if (polling) return;
    polling = true;
    try {
      const data = await api(stateUrl);
      render(data);
    } catch (e) {
      if (e.name !== 'AbortError') {
        elStatus.textContent = e.message || 'Erro de conexão';
      }
    } finally {
      polling = false;
    }
  }

  async function placeBet(slot) {
    const panel = root.querySelector(`.bet-panel[data-slot="${slot}"]`);
    const amount = Number(panel.querySelector('[data-amount]').value);
    const mode = panel.querySelector('.bet-panel__tabs button.is-active')?.dataset.mode;
    const autoInput = panel.querySelector('[data-auto]');
    const payload = { amount, slot };
    if (mode === 'auto') {
      payload.auto_cashout_at = Number(autoInput.value);
    }

    busy[slot] = true;
    panelState(slot);
    try {
      const data = await api(betUrl, { method: 'POST', body: JSON.stringify(payload) });
      render(data.state);
    } catch (e) {
      alert(e.message);
      poll();
    } finally {
      busy[slot] = false;
      panelState(slot);
    }
  }

  async function cashout(slot) {
    if (busy[slot]) return;
    if (!canCashout(slot)) {
      alert('Cash out disponível somente durante o voo.');
      return;
    }

    busy[slot] = true;
    panelState(slot);
    try {
      const data = await api(cashoutUrl, {
        method: 'POST',
        body: JSON.stringify({ slot }),
      });

      // Apply cashout instantly without waiting a full state rebuild
      if (!state.my_bets) state.my_bets = {};
      state.my_bets[slot] = {
        ...(state.my_bets[slot] || state.my_bets[String(slot)] || {}),
        status: data.bet.status,
        cashout_multiplier: data.bet.cashout_multiplier,
        payout: data.bet.payout,
        amount: data.bet.amount,
      };
      state.balance = data.balance;
      state.wallet = data.wallet;
      if (elBalance) elBalance.textContent = money(data.balance);
      panelState(slot);
      poll();
    } catch (e) {
      alert(e.message);
      await poll();
    } finally {
      busy[slot] = false;
      panelState(slot);
    }
  }

  root.querySelectorAll('.bet-panel').forEach((panel) => {
    const slot = Number(panel.dataset.slot);
    const amountInput = panel.querySelector('[data-amount]');
    const autoWrap = panel.querySelector('[data-auto-wrap]');

    panel.querySelectorAll('[data-adj]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const delta = Number(btn.dataset.adj);
        const next = Math.max(1, Number(amountInput.value || 1) + delta);
        amountInput.value = next.toFixed(2);
        panelState(slot);
      });
    });

    panel.querySelectorAll('[data-preset]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const add = Number(btn.dataset.preset) || 0;
        const current = Number(amountInput.value || 0);
        amountInput.value = Math.max(1, current + add).toFixed(2);
        panelState(slot);
      });
    });

    amountInput.addEventListener('input', () => panelState(slot));

    panel.querySelectorAll('.bet-panel__tabs button').forEach((tab) => {
      tab.addEventListener('click', () => {
        panel.querySelectorAll('.bet-panel__tabs button').forEach((t) => t.classList.remove('is-active'));
        tab.classList.add('is-active');
        autoWrap.classList.toggle('hidden', tab.dataset.mode !== 'auto');
      });
    });

    panel.querySelector('[data-action]').addEventListener('click', (ev) => {
      ev.preventDefault();
      ev.stopPropagation();
      const my = state?.my_bets?.[slot] || state?.my_bets?.[String(slot)];
      if (canCashout(slot)) {
        cashout(slot);
      } else if (state?.round?.status === 'waiting' && !my) {
        placeBet(slot);
      }
    });
  });

  poll();
  setInterval(poll, 1000);
  rafId = requestAnimationFrame(tickFrame);
})();
