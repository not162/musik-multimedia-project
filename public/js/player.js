/* Musikku – pemutar persisten + Pro Player (EQ, Speed, Sleep Timer, Offline Cache).
   Dimuat sekali di <head>; elemen #player bertanda data-turbo-permanent, jadi
   musik tidak putus ketika berpindah halaman (Turbo Drive). */
(function () {
  if (window.__musikku) return;
  window.__musikku = true;

  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';
  const base = () => (document.body.dataset.base || '').replace(/\/$/, '');
  const isAuth = () => document.body.dataset.auth === '1';
  const fmt = s => { if (!isFinite(s)) return '0:00'; s = Math.floor(s); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); };
  const store = {
    get(k, d) { try { const v = localStorage.getItem(k); return v === null ? d : v; } catch { return d; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch {} },
  };

  const st = { queue: [], i: -1, shuffle: false, repeat: 'off', counted: false, hasPlayed: false, sleepTimer: null, sleepUntil: null };
  const audio = $('#audio');
  const player = $('#player');
  const el = {
    play: $('#pPlay'), prev: $('#pPrev'), next: $('#pNext'), shuf: $('#pShuffle'), rep: $('#pRepeat'),
    seek: $('#pSeek'), cur: $('#pCur'), dur: $('#pDur'), vol: $('#pVol'), title: $('#pTitle'), artist: $('#pArtist'),
    cover: $('#pCover'), fav: $('#pFav'), qBtn: $('#pQueueBtn'), qBox: $('#pQueue'), qList: $('#pQueueList'),
    eq: $('#pEq'), speed: $('#pSpeed'), timer: $('#pTimer'), offlineBtn: $('#pOffline'),
  };
  if (!audio) return;

  /* ---------- Web Audio 5-Band Equalizer (Unlocked for Registered Users) ---------- */
  let audioCtx = null, eqFilters = [];
  const EQ_PRESETS = {
    normal: [0, 0, 0, 0, 0],
    bass:   [6, 4, 0, -1, 1],
    vocal:  [-2, 1, 5, 4, 1],
    lofi:   [3, 2, -2, -4, -6],
    rock:   [5, 2, -1, 3, 5],
  };

  function ensureEq() {
    if (audioCtx || !window.AudioContext && !window.webkitAudioContext) return;
    try {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      audioCtx = new Ctx();
      const source = audioCtx.createMediaElementSource(audio);
      const freqs = [60, 230, 910, 3600, 14000];
      eqFilters = freqs.map((f, idx) => {
        const b = audioCtx.createBiquadFilter();
        b.type = idx === 0 ? 'lowshelf' : (idx === freqs.length - 1 ? 'highshelf' : 'peaking');
        b.frequency.value = f;
        b.Q.value = 1;
        b.gain.value = 0;
        return b;
      });
      source.connect(eqFilters[0]);
      for (let i = 0; i < eqFilters.length - 1; i++) eqFilters[i].connect(eqFilters[i + 1]);
      eqFilters[eqFilters.length - 1].connect(audioCtx.destination);
    } catch {}
  }

  function applyEqPreset(preset) {
    if (!isAuth()) return;
    ensureEq();
    const gains = EQ_PRESETS[preset] || EQ_PRESETS.normal;
    eqFilters.forEach((f, idx) => { f.gain.value = gains[idx] ?? 0; });
    store.set('mk_eq', preset);
  }

  function setPlayerVisible(visible) {
    player.classList.toggle('player-visible', visible);
    document.body.classList.toggle('player-visible', visible);
  }

  window.musikkuToast = msg => {
    const t = $('#toast'); if (!t) return;
    t.textContent = msg; t.classList.add('show');
    clearTimeout(t._h); t._h = setTimeout(() => t.classList.remove('show'), 2600);
  };

  const itemFrom = r => ({
    id: +r.dataset.id, src: r.dataset.src, title: r.dataset.title, artist: r.dataset.artist,
    href: r.dataset.href, cover: r.dataset.cover, grad: r.dataset.grad, fav: r.dataset.fav === '1',
  });
  const cur = () => st.queue[st.i];

  function renderNow() {
    const it = cur(); if (!it) return;
    el.title.textContent = it.title; el.title.href = it.href;
    el.artist.textContent = it.artist;
    el.cover.style.background = it.cover ? `url(${it.cover}) center/cover` : it.grad;
    el.cover.textContent = it.cover ? '' : it.title.charAt(0).toUpperCase();
    el.fav.classList.toggle('on', !!it.fav);
    document.title = `${it.title} · ${it.artist}`;
  }

  function renderQueue() {
    el.qList.innerHTML = '';
    st.queue.forEach((it, idx) => {
      const li = document.createElement('li'); if (idx === st.i) li.className = 'cur';
      const s = document.createElement('span'); s.textContent = `${it.title} — ${it.artist}`;
      const x = document.createElement('button'); x.className = 'icon'; x.textContent = '✕'; x.title = 'Hapus dari antrean';
      x.onclick = e => { e.stopPropagation(); removeFromQueue(idx); };
      li.onclick = () => playAt(idx); li.append(s, x); el.qList.append(li);
    });
    if (!st.queue.length) el.qList.innerHTML = '<li class="mut">Antrean kosong</li>';
  }

  function markPlaying() {
    const id = cur()?.id;
    $$('.song-row').forEach(r => {
      const playing = id !== undefined && +r.dataset.id === id;
      r.classList.toggle('playing', playing);
      const button = $('.js-play-song', r);
      if (button) {
        button.textContent = playing && !audio.paused ? '⏸ Jeda' : '▶ Putar';
        button.setAttribute('aria-label', `${playing && !audio.paused ? 'Jeda' : 'Putar'} ${r.dataset.title}`);
      }
    });
  }
  function setPlayIcon() { el.play.textContent = audio.paused ? '▶' : '⏸'; }

  function playAt(i) {
    if (i < 0 || i >= st.queue.length) return;
    st.i = i; st.counted = false;
    audio.src = st.queue[i].src;
    if (audioCtx?.state === 'suspended') audioCtx.resume().catch(() => {});
    audio.play().catch(error => {
      musikkuToast(error.name === 'NotAllowedError'
        ? 'Browser memblokir pemutaran audio. Tekan tombol putar lagi.'
        : 'Tidak dapat memutar file audio ini.');
    });
    renderNow(); renderQueue(); markPlaying(); setSession(st.queue[i]);
  }

  function next(auto) {
    if (!st.queue.length) return;
    if (auto && st.repeat === 'one') { audio.currentTime = 0; audio.play(); return; }
    let n;
    if (st.shuffle && st.queue.length > 1) { do { n = Math.floor(Math.random() * st.queue.length); } while (n === st.i); }
    else n = st.i + 1;
    if (n >= st.queue.length) {
      if (st.repeat === 'all' || !auto) n = 0; else { audio.pause(); audio.currentTime = 0; return; }
    }
    playAt(n);
  }

  function prev() {
    if (!st.queue.length) return;
    if (audio.currentTime > 3) { audio.currentTime = 0; return; }
    playAt(st.i > 0 ? st.i - 1 : st.queue.length - 1);
  }

  function toggle() {
    if (!audio.src) { const first = $('.song-row'); if (first) playRows($$('.song-row'), 0); return; }
    if (audioCtx?.state === 'suspended') audioCtx.resume().catch(() => {});
    audio.paused ? audio.play() : audio.pause();
  }

  function playRows(rows, start) { st.queue = rows.map(itemFrom); playAt(start); }
  function removeFromQueue(idx) {
    const wasCur = idx === st.i;
    st.queue.splice(idx, 1);
    if (idx < st.i) st.i--;
    if (wasCur) { if (st.queue.length) { st.i = Math.min(idx, st.queue.length) - 1; next(false); } else { audio.pause(); audio.removeAttribute('src'); st.i = -1; st.hasPlayed = false; setPlayerVisible(false); } }
    renderQueue(); markPlaying();
  }
  function setShuffle(on) { st.shuffle = on; el.shuf.classList.toggle('on', on); }

  function setFav(id, on) {
    $$(`.js-fav[data-id="${id}"]`).forEach(b => b.classList.toggle('on', on));
    $$(`.song-row[data-id="${id}"]`).forEach(r => r.dataset.fav = on ? '1' : '0');
    st.queue.forEach(it => { if (it.id === id) it.fav = on; });
    if (cur()?.id === id) el.fav.classList.toggle('on', on);
  }

  async function toggleFav(id) {
    if (!isAuth()) { $('#proLockDialog')?.showModal(); return; }
    const res = await fetch(`${base()}/favorit/${id}`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' } });
    if (!res.ok) { musikkuToast('Gagal mengubah favorit'); return; }
    const j = await res.json(); setFav(id, j.favorited);
    musikkuToast(j.favorited ? 'Ditambahkan ke favorit' : 'Dihapus dari favorit');
  }
  let pendingSong = null;

  document.addEventListener('click', async e => {
    const t = e.target;
    let b;
    if ((b = t.closest('.js-pro-locked'))) {
      e.preventDefault(); e.stopPropagation();
      $('#proLockDialog')?.showModal();
      return;
    }
    if ((b = t.closest('.js-offline-save'))) {
      e.stopPropagation();
      const row = b.closest('.song-row');
      if (row && window.musikkuOffline) {
        try { await window.musikkuOffline.toggle(itemFrom(row)); } catch { musikkuToast('Gagal mengunduh untuk offline'); }
      }
      return;
    }
    if ((b = t.closest('.js-fav')))   { e.stopPropagation(); return toggleFav(+b.dataset.id); }
    if ((b = t.closest('.js-queue'))) {
      const it = itemFrom(b.closest('.song-row')); st.queue.push(it);
      if (st.i === -1) playAt(st.queue.length - 1); else renderQueue();
      return musikkuToast('Ditambahkan ke antrean');
    }
    if ((b = t.closest('.js-play-song'))) {
      const row = b.closest('.song-row'), scope = row?.closest('.song-list');
      if (!row || !scope) return;
      if (+row.dataset.id === cur()?.id) {
        if (audio.paused) audio.play().catch(() => musikkuToast('Audio gagal diputar.'));
        else audio.pause();
        return;
      }
      const rows = $$('.song-row', scope);
      return playRows(rows, rows.indexOf(row));
    }
    if ((b = t.closest('.js-add-pl'))) {
      if (!isAuth()) { $('#proLockDialog')?.showModal(); return; }
      pendingSong = +b.dataset.id; return $('#plDialog')?.showModal();
    }
    if ((b = t.closest('.pl-opt'))) {
      const res = await fetch(`${base()}/playlists/${b.dataset.pl}/songs/${pendingSong}`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' } });
      $('#plDialog')?.close();
      return musikkuToast(res.ok ? (await res.json()).message : 'Gagal menambahkan');
    }
    if ((b = t.closest('.js-play-all, .js-shuffle-all'))) {
      const scope = $(b.dataset.scope); if (!scope) return;
      const rows = $$('.song-row', scope); if (!rows.length) return;
      const shuffle = b.classList.contains('js-shuffle-all');
      setShuffle(shuffle);
      return playRows(rows, shuffle ? Math.floor(Math.random() * rows.length) : 0);
    }
    const row = t.closest('.song-row');
    if (row && !t.closest('a,button,input,form,select,summary')) {
      const scope = row.closest('.song-list'); const rows = $$('.song-row', scope);
      if (+row.dataset.id === cur()?.id && st.queue.length) return toggle();
      playRows(rows, rows.indexOf(row));
    }
  });

  document.addEventListener('keydown', e => {
    if (e.code === 'Space' && !/INPUT|TEXTAREA|SELECT|BUTTON/.test(e.target.tagName) && !e.target.isContentEditable) { e.preventDefault(); toggle(); }
  });

  function syncPlayer() { setPlayerVisible(st.hasPlayed); markPlaying(); }
  document.addEventListener('turbo:load', syncPlayer);
  document.addEventListener('turbo:render', syncPlayer);

  el.play.onclick = toggle; el.next.onclick = () => next(false); el.prev.onclick = prev;
  el.shuf.onclick = () => setShuffle(!st.shuffle);
  el.rep.onclick = () => {
    st.repeat = { off: 'all', all: 'one', one: 'off' }[st.repeat];
    el.rep.classList.toggle('on', st.repeat !== 'off');
    el.rep.textContent = st.repeat === 'one' ? '🔂' : '🔁';
    el.rep.title = 'Ulangi: ' + { off: 'mati', all: 'semua', one: 'satu lagu' }[st.repeat];
  };
  el.fav.onclick = () => cur() && toggleFav(cur().id);
  el.qBtn.onclick = () => el.qBox.classList.toggle('hidden');
  $('#pQueueClear').onclick = () => { st.queue = []; st.i = -1; audio.pause(); audio.removeAttribute('src'); st.hasPlayed = false; setPlayerVisible(false); renderQueue(); markPlaying(); };

  // Pro Controls (Registered Users)
  if (el.eq) {
    el.eq.value = store.get('mk_eq', document.body.dataset.eq || 'normal');
    el.eq.addEventListener('change', () => {
      if (!isAuth()) { el.eq.value = 'normal'; $('#proLockDialog')?.showModal(); return; }
      applyEqPreset(el.eq.value);
      fetch(`${base()}/akun/preferensi`, {
        method: 'PUT',
        headers: { 'X-CSRF-TOKEN': csrf(), 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ eq_preset: el.eq.value }),
      }).catch(() => {});
      musikkuToast(`Equalizer: ${el.eq.options[el.eq.selectedIndex].text}`);
    });
  }

  if (el.speed) {
    el.speed.value = store.get('mk_speed', '1');
    audio.playbackRate = Number(el.speed.value) || 1;
    el.speed.addEventListener('change', () => {
      if (!isAuth()) { el.speed.value = '1'; $('#proLockDialog')?.showModal(); return; }
      audio.playbackRate = Number(el.speed.value);
      store.set('mk_speed', el.speed.value);
      musikkuToast(`Kecepatan putar: ${el.speed.value}x`);
    });
  }

  if (el.timer) {
    el.timer.addEventListener('change', () => {
      if (!isAuth()) { el.timer.value = '0'; $('#proLockDialog')?.showModal(); return; }
      clearTimeout(st.sleepTimer);
      const mins = Number(el.timer.value);
      if (mins <= 0) { musikkuToast('Sleep timer dimatikan'); return; }
      st.sleepTimer = setTimeout(() => {
        audio.pause();
        el.timer.value = '0';
        musikkuToast('Sleep timer selesai — pemutaran dijeda');
      }, mins * 60 * 1000);
      musikkuToast(`Sleep timer aktif: ${mins} menit`);
    });
  }

  if (el.offlineBtn) {
    el.offlineBtn.addEventListener('click', () => {
      if (!cur()) return;
      window.musikkuOffline?.toggle(cur());
    });
  }

  el.seek.addEventListener('input', () => { if (isFinite(audio.duration)) audio.currentTime = el.seek.value / 1000 * audio.duration; });
  el.vol.value = store.get('mk_vol', 80); audio.volume = el.vol.value / 100;
  el.vol.addEventListener('input', () => { audio.volume = el.vol.value / 100; store.set('mk_vol', el.vol.value); });

  audio.addEventListener('play', () => {
    st.hasPlayed = true; setPlayerVisible(true); setPlayIcon(); markPlaying();
    if (isAuth() && el.eq && el.eq.value !== 'normal') applyEqPreset(el.eq.value);
  });
  audio.addEventListener('pause', () => { setPlayIcon(); markPlaying(); });
  audio.addEventListener('ended', () => next(true));
  audio.addEventListener('loadedmetadata', () => el.dur.textContent = fmt(audio.duration));
  audio.addEventListener('timeupdate', () => {
    el.cur.textContent = fmt(audio.currentTime);
    if (isFinite(audio.duration) && audio.duration > 0) {
      if (document.activeElement !== el.seek) el.seek.value = audio.currentTime / audio.duration * 1000;
      el.seek.style.background = `linear-gradient(90deg,#16a34a ${el.seek.value / 10}%,#cbd5e1 0)`;
    }
    if (!st.counted && cur()) {
      const need = Math.min(10, (audio.duration || 20) / 2);
      if (audio.currentTime >= need) {
        st.counted = true;
        fetch(`${base()}/lagu/${cur().id}/played`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' } }).catch(() => {});
      }
    }
  });

  function setSession(it) {
    if (!('mediaSession' in navigator)) return;
    navigator.mediaSession.metadata = new MediaMetadata({ title: it.title, artist: it.artist, artwork: it.cover ? [{ src: it.cover, sizes: '512x512' }] : [] });
  }
  if ('mediaSession' in navigator) {
    const ms = navigator.mediaSession;
    ms.setActionHandler('play', () => audio.play()); ms.setActionHandler('pause', () => audio.pause());
    ms.setActionHandler('previoustrack', prev); ms.setActionHandler('nexttrack', () => next(false));
    ms.setActionHandler('seekto', d => { audio.currentTime = d.seekTime; });
  }
  renderQueue(); setPlayIcon();
})();
