(() => {
  const app = document.getElementById('truco-app');
  if (!app) return;

  const startUrl = app.dataset.startUrl;
  const joinUrl = app.dataset.joinUrl;
  const csrf = app.dataset.csrf;
  const stakes = JSON.parse(app.dataset.stakes || '[]');

  const hub = document.getElementById('truco-hub');
  const lobbyRoom = document.getElementById('truco-lobby-room');
  const table = document.getElementById('truco-table');
  const result = document.getElementById('truco-result');
  const stakesEl = document.getElementById('truco-stakes');
  const startBtn = document.getElementById('truco-start');
  const hubError = document.getElementById('truco-hub-error');
  const hub2v2 = document.getElementById('hub-2v2-extra');
  const leaveBtn = document.getElementById('btn-leave');

  let mode = '1v1';
  let stake = null;
  let matchId = null;
  let pollTimer = null;
  let selectedCard = null;
  let lastHandKey = '';

  const suitSymbol = { clubs: '♣', hearts: '♥', spades: '♠', diamonds: '♦' };
  const isRed = (s) => s === 'hearts' || s === 'diamonds';

  function cardHtml(card, opts = {}) {
    if (!card) return '';
    const hidden = card.hidden || card.rank === '?';
    const cls = [
      'truco-card',
      !hidden && isRed(card.suit) ? 'red' : '',
      opts.sm ? 'sm' : '',
      opts.clickable ? 'is-clickable' : '',
      opts.selected ? 'is-selected' : '',
      opts.anim || '',
    ].filter(Boolean).join(' ');
    const rank = hidden ? '?' : card.rank;
    const suit = hidden ? '' : (suitSymbol[card.suit] || '');
    return `<button type="button" class="${cls}" data-code="${card.code}" ${opts.clickable ? '' : 'disabled'}>
      <span>${rank}</span><span>${suit}</span>
    </button>`;
  }

  function backHtml(n) {
    return Array.from({ length: Math.max(0, n) }, () => '<div class="truco-back"></div>').join('');
  }

  stakes.forEach((s) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'truco-stake';
    b.textContent = `R$ ${s}`;
    b.dataset.stake = String(s);
    b.addEventListener('click', () => {
      stake = s;
      stakesEl.querySelectorAll('.truco-stake').forEach((x) => x.classList.remove('is-active'));
      b.classList.add('is-active');
      startBtn.disabled = false;
      startBtn.textContent = mode === '2v2' ? 'Criar sala' : 'Jogar';
    });
    stakesEl.appendChild(b);
  });

  document.querySelectorAll('.truco-mode').forEach((btn) => {
    btn.addEventListener('click', () => {
      mode = btn.dataset.mode;
      document.querySelectorAll('.truco-mode').forEach((x) => x.classList.remove('is-active'));
      btn.classList.add('is-active');
      hub2v2.hidden = mode !== '2v2';
      startBtn.textContent = mode === '2v2' ? 'Criar sala' : 'Jogar';
      startBtn.disabled = !stake;
    });
  });

  async function api(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf,
      },
      body: JSON.stringify(body || {}),
      credentials: 'same-origin',
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || 'Erro na requisição');
    return data;
  }

  async function getState() {
    const res = await fetch(`/api/truco/${matchId}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || 'Falha ao carregar estado');
    return data;
  }

  function stopPoll() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  function startPoll() {
    stopPoll();
    pollTimer = setInterval(async () => {
      try {
        render(await getState());
      } catch (_) { /* ignore */ }
    }, 1200);
  }

  function setResultOpen(open) {
    if (!result) return;
    result.hidden = !open;
    result.setAttribute('aria-hidden', open ? 'false' : 'true');
    result.classList.toggle('is-open', !!open);
    if (open) {
      result.style.removeProperty('display');
    } else {
      result.style.setProperty('display', 'none', 'important');
    }
  }

  function showHub() {
    stopPoll();
    matchId = null;
    hub.hidden = false;
    lobbyRoom.hidden = true;
    table.hidden = true;
    setResultOpen(false);
    if (leaveBtn) leaveBtn.hidden = true;
  }

  function showRoomLobby(data) {
    hub.hidden = true;
    lobbyRoom.hidden = false;
    table.hidden = true;
    setResultOpen(false);
    if (leaveBtn) leaveBtn.hidden = true;
    document.getElementById('room-code').textContent = data.code || '—';
    const list = document.getElementById('room-seats');
    list.innerHTML = (data.seats || []).map((s) => {
      const label = s.is_ghost ? `${s.name}` : (s.filled ? s.name : 'Aguardando…');
      return `<li>Assento ${s.seat_index + 1} · Time ${String(s.team).toUpperCase()} — ${label}</li>`;
    }).join('');
    document.getElementById('btn-start-room').hidden = !data.can_start && !data.is_host;
    document.getElementById('room-msg').textContent = data.message
      || (data.can_start ? 'Sala pronta. Inicie quando quiser.' : 'Aguardando parceiro (pode iniciar com fantasma).');
  }

  function seatMap(data) {
    const seats = data.seats || [];
    const me = seats.find((s) => s.is_you) || seats[0];
    const byIndex = Object.fromEntries(seats.map((s) => [s.seat_index, s]));

    if (data.mode === '1v1') {
      return { me: byIndex[0], top: byIndex[1], left: null, right: null };
    }

    const partner = seats.find((s) => s.team === me.team && s.seat_index !== me.seat_index);
    const foes = seats.filter((s) => s.team !== me.team);
    return {
      me,
      top: partner || null,
      left: foes[0] || null,
      right: foes[1] || null,
    };
  }

  function renderSeatAvatar(el, seat, turnSeat) {
    if (!el) return;
    const wrap = el.closest('.truco-seat');
    if (!seat) {
      wrap?.classList.add('is-hidden');
      return;
    }
    wrap?.classList.remove('is-hidden');
    const name = el.querySelector('.truco-avatar__name');
    if (name) name.textContent = seat.is_you ? 'Você' : (seat.name || '…');
    el.classList.toggle('is-turn', turnSeat === seat.seat_index);
    el.classList.toggle('is-me', !!seat.is_you);

    const react = el.querySelector('.truco-react');
    const reactions = window.__trucoReactions || {};
    const r = reactions[String(seat.seat_index)];
    if (react && r && Date.now() / 1000 - r.at < 2) {
      react.hidden = false;
      react.textContent = r.emoji;
    } else if (react) {
      react.hidden = true;
    }
  }

  function renderDots(el, count) {
    el.innerHTML = [0, 1, 2].map((i) => `<span class="${i < count ? 'is-on' : ''}"></span>`).join('');
  }

  function render(data) {
    if (!data) return;
    matchId = data.match_id || data.id || matchId;
    window.__trucoReactions = data.reactions || {};

    if (data.status === 'waiting') {
      showRoomLobby(data);
      startPoll();
      return;
    }

    hub.hidden = true;
    lobbyRoom.hidden = true;
    table.hidden = false;
    if (leaveBtn) leaveBtn.hidden = false;
    table.classList.toggle('mode-1v1', data.mode === '1v1');
    table.classList.toggle('mode-2v2', data.mode === '2v2');

    document.getElementById('score-us').textContent = data.score_us;
    document.getElementById('score-them').textContent = data.score_them;
    document.getElementById('hand-value').textContent = data.hand_value;

    const tricks = data.tricks || { us: data.tricks_us || 0, them: data.tricks_them || 0 };
    renderDots(document.getElementById('dots-us'), tricks.us || 0);
    renderDots(document.getElementById('dots-them'), tricks.them || 0);

    const map = seatMap(data);
    renderSeatAvatar(document.getElementById('avatar-me'), map.me, data.turn_seat);
    renderSeatAvatar(document.getElementById('avatar-top'), map.top, data.turn_seat);
    renderSeatAvatar(document.getElementById('avatar-left'), map.left, data.turn_seat);
    renderSeatAvatar(document.getElementById('avatar-right'), map.right, data.turn_seat);

    const handCounts = data.hand_counts || {};
    document.getElementById('backs-top').innerHTML = map.top ? backHtml(handCounts[map.top.seat_index] ?? 3) : '';
    document.getElementById('backs-left').innerHTML = map.left ? backHtml(handCounts[map.left.seat_index] ?? 3) : '';
    document.getElementById('backs-right').innerHTML = map.right ? backHtml(handCounts[map.right.seat_index] ?? 3) : '';

    document.getElementById('vira-card').innerHTML = data.vira ? cardHtml(data.vira, { sm: true }) : '';
    document.getElementById('manilhas').innerHTML = (data.manilhas || []).map((c) => cardHtml(c, { sm: true })).join('');
    document.getElementById('table-cards').innerHTML = (data.table || [])
      .map((t) => cardHtml(t.card, { anim: 'is-play-anim' }))
      .join('');

    const handKey = JSON.stringify(data.hand || []);
    const handEl = document.getElementById('my-hand');
    const dealAnim = handKey !== lastHandKey && (data.hand || []).length === 3;
    lastHandKey = handKey;

    const canPlay = data.your_turn && data.phase === 'play' && !data.pending_raise;
    handEl.innerHTML = (data.hand || []).map((c) =>
      cardHtml(c, {
        clickable: canPlay,
        selected: selectedCard === String(c.code),
        anim: dealAnim ? 'is-fly-in' : '',
      })
    ).join('');

    handEl.querySelectorAll('.truco-card.is-clickable').forEach((btn) => {
      btn.addEventListener('click', () => {
        selectedCard = btn.dataset.code;
        handEl.querySelectorAll('.truco-card').forEach((x) => x.classList.remove('is-selected'));
        btn.classList.add('is-selected');
        renderActions(data);
      });
    });

    const msg = document.getElementById('truco-msg');
    if (data.message) {
      msg.textContent = data.message;
    } else if (data.last_raise) {
      const labels = { 3: 'Truco!', 6: 'Seis!', 9: 'Nove!', 12: 'Doze!' };
      msg.textContent = labels[data.last_raise] || `Vale ${data.last_raise}!`;
    } else if (data.escuro) {
      msg.textContent = 'MÃO NO ESCURO';
    } else {
      msg.textContent = data.phase === 'waiting_raise' ? 'Aguardando resposta…' : '';
    }

    renderActions(data);

    if (data.status === 'finished') {
      stopPoll();
      setResultOpen(true);
      const won = data.winner === 'us';
      document.getElementById('result-title').textContent = won ? 'Vitória!' : 'Derrota';
      document.getElementById('result-body').textContent = won
        ? `Você recebeu R$ ${Number(data.payout || data.stake * 2).toFixed(2)}`
        : `Stake R$ ${Number(data.stake).toFixed(2)} ficou com a casa.`;
    } else {
      setResultOpen(false);
    }
  }

  function renderActions(data) {
    const box = document.getElementById('truco-actions');
    const label = document.getElementById('action-label');
    box.innerHTML = '';
    if (data.status === 'finished') {
      label.textContent = '';
      return;
    }

    const serverActs = data.actions || [];
    const acts = [];

    if (serverActs.includes('mao11_play') || serverActs.includes('mao11_run')) {
      label.textContent = 'Mão de 11 — jogar (vale 3) ou correr?';
      if (serverActs.includes('mao11_play')) acts.push({ t: 'mao11_play', label: 'Jogar', cls: 'truco-btn--ok' });
      if (serverActs.includes('mao11_run')) acts.push({ t: 'mao11_run', label: 'Correr', cls: 'truco-btn--run' });
    } else if (data.your_turn && data.pending_raise && data.phase === 'waiting_raise') {
      label.textContent = `Aceitar ${data.pending_raise}?`;
      acts.push({ t: 'accept', label: 'Aceitar', cls: 'truco-btn--ok' });
      acts.push({ t: 'run', label: 'Correr', cls: 'truco-btn--run' });
      if (serverActs.includes('raise') || serverActs.includes('truco')) {
        const next = { 3: 6, 6: 9, 9: 12 }[data.pending_raise];
        if (next) acts.push({ t: 'raise', label: String(next), cls: 'truco-btn--raise', value: next });
      }
    } else if (data.your_turn && data.phase === 'play' && !data.pending_raise) {
      label.textContent = 'O que deseja fazer?';
      if (selectedCard && serverActs.includes('play')) {
        acts.push({ t: 'play', label: 'Jogar', cls: 'truco-btn--ok' });
      }
      if ((serverActs.includes('truco') || serverActs.includes('raise')) && !data.escuro) {
        const raiseTo = data.hand_value < 3 ? 3 : data.hand_value < 6 ? 6 : data.hand_value < 9 ? 9 : 12;
        const raiseLabel = raiseTo === 3 ? 'Truco' : String(raiseTo);
        acts.push({ t: 'raise', label: raiseLabel, cls: 'truco-btn--raise', value: raiseTo });
      }
    } else {
      label.textContent = data.your_turn ? '…' : 'Aguarde sua vez';
    }

    acts.forEach((a) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = `truco-btn ${a.cls}`;
      b.textContent = a.label;
      b.addEventListener('click', () => doAct(a));
      box.appendChild(b);
    });
  }

  async function doAct(a) {
    try {
      const body = { action: a.t };
      if (a.t === 'play') {
        if (selectedCard === null || selectedCard === undefined) return;
        body.card = String(selectedCard);
      }
      if (a.t === 'raise') body.value = a.value;
      if (a.t === 'react') body.emoji = a.emoji;

      const data = await api(`/api/truco/${matchId}/act`, body);
      selectedCard = null;
      render(data);
    } catch (e) {
      document.getElementById('truco-msg').textContent = e.message;
    }
  }

  startBtn.addEventListener('click', async () => {
    hubError.hidden = true;
    startBtn.disabled = true;
    try {
      const data = await api(startUrl, { mode, stake });
      matchId = data.match_id || data.id;
      selectedCard = null;
      lastHandKey = '';
      render(data);
      if (data.status === 'playing' || data.status === 'waiting') startPoll();
    } catch (e) {
      hubError.hidden = false;
      hubError.textContent = e.message;
    } finally {
      startBtn.disabled = !stake;
    }
  });

  document.getElementById('btn-join').addEventListener('click', async () => {
    hubError.hidden = true;
    const code = document.getElementById('join-code').value.trim();
    if (!code) return;
    try {
      const data = await api(joinUrl, { code });
      matchId = data.match_id || data.id;
      render(data);
      startPoll();
    } catch (e) {
      hubError.hidden = false;
      hubError.textContent = e.message;
    }
  });

  document.getElementById('btn-start-room').addEventListener('click', async () => {
    try {
      const data = await api(`/api/truco/${matchId}/start-room`, {});
      selectedCard = null;
      lastHandKey = '';
      render(data);
      startPoll();
    } catch (e) {
      document.getElementById('room-msg').textContent = e.message;
    }
  });

  async function leaveMatch() {
    if (!matchId) {
      showHub();
      return;
    }
    try {
      await api(`/api/truco/${matchId}/leave`, {});
    } catch (_) { /* ok */ }
    showHub();
  }

  if (leaveBtn) leaveBtn.addEventListener('click', leaveMatch);
  document.getElementById('btn-leave-room')?.addEventListener('click', leaveMatch);
  document.getElementById('result-again').addEventListener('click', () => {
    setResultOpen(false);
    showHub();
  });

  // Ensure overlay starts closed (CSS display must not fight [hidden])
  setResultOpen(false);

  document.getElementById('emoji-bar').addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-emoji]');
    if (!btn || !matchId) return;
    doAct({ t: 'react', emoji: btn.dataset.emoji });
  });
})();
