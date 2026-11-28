/* Musikku – pemutar persisten + Pro Player (10-Band DSP EQ, Algoritma Adaptif, Speed, Sleep Timer, Offline Cache).
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
  const clamp = (v, min, max) => Math.min(max, Math.max(min, v));
  const fmt = s => { if (!isFinite(s)) return '0:00'; s = Math.floor(s); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); };
  const store = {
    get(k, d) { try { const v = localStorage.getItem(k); return v === null ? d : v; } catch { return d; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch {} },
  };

  const st = { queue: [], i: -1, shuffle: false, repeat: 'off', counted: false, hasPlayed: false, sleepTimer: null };
  const audio = $('#audio');
  const player = $('#player');
  const el = {
    play: $('#pPlay'), prev: $('#pPrev'), next: $('#pNext'), shuf: $('#pShuffle'), rep: $('#pRepeat'),
    seek: $('#pSeek'), cur: $('#pCur'), dur: $('#pDur'), vol: $('#pVol'), title: $('#pTitle'), artist: $('#pArtist'),
    cover: $('#pCover'), fav: $('#pFav'), qBtn: $('#pQueueBtn'), qBox: $('#pQueue'), qList: $('#pQueueList'),
    eq: $('#pEq'), speed: $('#pSpeed'), timer: $('#pTimer'), offlineBtn: $('#pOffline'),
    eqBtn: $('#pEqStudioBtn'), eqMobBtn: $('#pEqMobileBtn'),
  };
  if (!audio) return;

  /* =====================================================================
   * 10-Band Parametric/Graphic DSP Equalizer + Direction-Guided Algorithm
   * ===================================================================== */
  const EQ_FREQS  = [32, 64, 125, 250, 500, 1000, 2000, 4000, 8000, 16000];
  const EQ_LABELS = ['Bass Dalam', 'Bass', 'Hangat', 'Vokal 1', 'Vokal 2', 'Jelas', 'Detail', 'Lembut', 'Desir', 'Udara'];
  const MODE_LABELS = {
    manual: 'Atur Sendiri',
    auto_genre: 'Otomatis Sesuai Lagu',
    adaptive: 'Haluskan Otomatis',
  };
  const DIR_LABELS = {
    balanced: 'Seimbang & Alami',
    warm_bass: 'Bass Hangat & Empuk',
    bright_clear: 'Vokal Halus & Jernih',
    punchy: 'Bertenaga & Menghentak',
    focus_study: 'Nyaman untuk Fokus',
  };

  const EQ_PRESETS = {
    normal:     [0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
    bass:       [6.5, 5.5, 4, 2, 0, -0.5, -0.5, 0, 1, 1.5],
    vocal:      [-3, -2, -1, 0, 2.5, 4.5, 5, 3.5, 1.5, 0.5],
    lofi:       [3, 3.5, 2.5, 1.5, 0, -1, -2.5, -4, -5.5, -7],
    rock:       [5, 4, 2.5, -1, -1.5, 0, 2, 3.5, 4.5, 5],
    acoustic:   [2, 2.5, 1.5, 0.5, 1.5, 2, 2.5, 3, 2.5, 2],
    electronic: [6, 5, 2.5, -1, -1.5, 0.5, 1.5, 3.5, 4.5, 5],
  };

  // Arah karakter suara target pilihan user
  const EQ_DIRECTIONS = {
    balanced:     [1, 0.5, 0, -0.5, 0, 0.5, 1, 1, 0.5, 1],
    warm_bass:    [5, 4, 2.5, 1, 0, -0.5, -1, -1.5, -1, -0.5],
    bright_clear: [-1.5, -1, 0, 0.5, 1.5, 2.5, 3.5, 4, 3.5, 3],
    punchy:       [5.5, 4.5, 2, -1.5, -2, 0, 1.5, 3, 4, 4.5],
    focus_study:  [-1, 0.5, 1.5, 1, 1.5, 1, -1.5, -2.5, -2, -1.5],
  };

  // Kurva dasar genre untuk mode adaptif genre
  const GENRE_CURVES = {
    lofi:       [3, 3, 2, 1, 0, -0.5, -1.5, -2.5, -3.5, -4],
    ambient:    [2, 2, 1, 0, 0.5, 1, 1.5, 1, 2, 2.5],
    elektronik: [5.5, 4.5, 2, -1, -1, 0.5, 1.5, 3, 4, 4.5],
    electronic: [5.5, 4.5, 2, -1, -1, 0.5, 1.5, 3, 4, 4.5],
    akustik:    [1.5, 2, 1, 0.5, 1.5, 2, 2.5, 3, 2, 1.5],
    acoustic:   [1.5, 2, 1, 0.5, 1.5, 2, 2.5, 3, 2, 1.5],
    pop:        [2.5, 2, 0.5, -0.5, 0.5, 1.5, 2.5, 2.5, 2, 1.5],
    indie:      [2, 1.5, 1, 0, 1, 1.5, 2, 2.5, 2, 1.5],
    rock:       [4.5, 3.5, 1.5, -1, -1, 0.5, 2, 3, 4, 4],
  };

  // Bobot vektor 3 arah tonal (Bass Tilt, Vocal Focus, Treble Air)
  const W_BASS  = [1.0, 0.9, 0.7, 0.4, 0.1, 0, 0, 0, 0, 0];
  const W_VOCAL = [0, 0, -0.15, 0.15, 0.65, 1.0, 0.95, 0.6, 0.15, 0];
  const W_AIR   = [0, 0, 0, 0, 0, 0, 0.2, 0.55, 0.85, 1.0];

  function loadInitialEq() {
    let serverPrefs = {};
    try { serverPrefs = JSON.parse(document.body.dataset.prefs || '{}') || {}; } catch {}
    let localPrefs = {};
    try { localPrefs = JSON.parse(store.get('mk_eq_cfg', '{}')) || {}; } catch {}
    const merged = Object.assign({
      eq_preset: 'normal',
      eq_mode: 'manual',
      eq_direction: 'balanced',
      eq_intensity: 70,
      eq_auto_gain: true,
      eq_macro: { bass_tilt: 0, vocal_focus: 0, treble_air: 0 },
      eq_bands: [...EQ_PRESETS.normal],
    }, localPrefs, serverPrefs);
    if (!Array.isArray(merged.eq_bands) || merged.eq_bands.length !== 10) {
      merged.eq_bands = [...(EQ_PRESETS[merged.eq_preset] || EQ_PRESETS.normal)];
    }
    merged.eq_macro = Object.assign({ bass_tilt: 0, vocal_focus: 0, treble_air: 0 }, merged.eq_macro || {});
    return merged;
  }

  const eqState = loadInitialEq();
  let audioCtx = null, preampGain = null, compressor = null, analyser = null, eqFilters = [];
  let adaptiveOffsets = new Array(10).fill(0);
  let computedBands = [...eqState.eq_bands];
  let spectrumLoopId = null;
  let adaptiveTimer = null;
  let saveDebounce = null;

  function ensureEqGraph() {
    if (audioCtx || (!window.AudioContext && !window.webkitAudioContext)) return;
    try {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      audioCtx = new Ctx();
      const source = audioCtx.createMediaElementSource(audio);

      preampGain = audioCtx.createGain();
      preampGain.gain.value = 1.0;

      eqFilters = EQ_FREQS.map((f, idx) => {
        const b = audioCtx.createBiquadFilter();
        b.type = idx === 0 ? 'lowshelf' : (idx === EQ_FREQS.length - 1 ? 'highshelf' : 'peaking');
        b.frequency.value = f;
        b.Q.value = (idx === 0 || idx === EQ_FREQS.length - 1) ? 0.7 : 1.414;
        b.gain.value = 0;
        return b;
      });

      compressor = audioCtx.createDynamicsCompressor();
      compressor.threshold.value = -14;
      compressor.knee.value = 16;
      compressor.ratio.value = 2.6;
      compressor.attack.value = 0.01;
      compressor.release.value = 0.18;

      analyser = audioCtx.createAnalyser();
      analyser.fftSize = 1024;
      analyser.smoothingTimeConstant = 0.82;

      source.connect(preampGain);
      preampGain.connect(eqFilters[0]);
      for (let i = 0; i < eqFilters.length - 1; i++) eqFilters[i].connect(eqFilters[i + 1]);
      eqFilters[eqFilters.length - 1].connect(compressor);
      compressor.connect(analyser);
      analyser.connect(audioCtx.destination);
    } catch {}
  }

  function computeDirectionalTarget() {
    const intensity = clamp(Number(eqState.eq_intensity ?? 70), 0, 100) / 100;
    const dirCurve  = EQ_DIRECTIONS[eqState.eq_direction] || EQ_DIRECTIONS.balanced;
    const macro     = eqState.eq_macro || { bass_tilt: 0, vocal_focus: 0, treble_air: 0 };
    const bTilt     = Number(macro.bass_tilt || 0);
    const vFocus    = Number(macro.vocal_focus || 0);
    const tAir      = Number(macro.treble_air || 0);

    const macroVec = EQ_FREQS.map((_, i) =>
      bTilt * W_BASS[i] + vFocus * W_VOCAL[i] + tAir * W_AIR[i]
    );

    if (eqState.eq_mode === 'manual') {
      return EQ_FREQS.map((_, i) =>
        clamp(Number(eqState.eq_bands[i] || 0) + macroVec[i] * 0.5, -12, 12)
      );
    }

    if (eqState.eq_mode === 'auto_genre') {
      const song = cur();
      const aiEq = song?.aiEq;
      if (Array.isArray(aiEq) && aiEq.length === 10) {
        // Jika file audio fisik sudah dihaluskan AI, beri sentuhan ringan agar suara tidak berlebihan
        const aiScale = song?.isVoiceSmoothed ? (0.35 * intensity) : intensity;
        return EQ_FREQS.map((_, i) =>
          clamp(Number(aiEq[i] || 0) * aiScale + dirCurve[i] * (0.3 * intensity) + macroVec[i], -12, 12)
        );
      }
      const gKey = (song?.genre || '').toLowerCase().trim();
      const gCurve = GENRE_CURVES[gKey] || EQ_DIRECTIONS.balanced;
      return EQ_FREQS.map((_, i) =>
        clamp(gCurve[i] * intensity + dirCurve[i] * (0.45 * intensity) + macroVec[i], -12, 12)
      );
    }

    // adaptive: kombinasi Target Arah User + Koreksi Spektral Real-Time + Vektor Arah
    return EQ_FREQS.map((_, i) =>
      clamp(dirCurve[i] * intensity + adaptiveOffsets[i] * intensity + macroVec[i], -12, 12)
    );
  }

  function applyEqToAudio(smooth = true) {
    computedBands = computeDirectionalTarget();

    if (isAuth() && audioCtx && eqFilters.length === 10) {
      const now = audioCtx.currentTime;
      const tc = smooth ? 0.045 : 0.01;
      computedBands.forEach((db, idx) => {
        eqFilters[idx].gain.setTargetAtTime(db, now, tc);
      });

      if (preampGain) {
        const maxBoost = Math.max(0, ...computedBands);
        const preampDb = eqState.eq_auto_gain ? (-maxBoost * 0.45) : 0;
        const lin = Math.pow(10, preampDb / 20);
        preampGain.gain.setTargetAtTime(lin, now, tc);
      }
    }
    syncEqStudioSliders();
  }

  function sampleAdaptiveSpectrum() {
    if (!isAuth() || eqState.eq_mode !== 'adaptive' || !analyser || audio.paused || audio.ended) return;

    const bins = new Uint8Array(analyser.frequencyBinCount);
    analyser.getByteFrequencyData(bins);
    const sampleRate = audioCtx?.sampleRate || 44100;
    const hzPerBin = sampleRate / analyser.fftSize;

    // Hitung energi rata-rata per pita ISO
    const energies = EQ_FREQS.map((freq, idx) => {
      const lowF  = idx === 0 ? 20 : Math.sqrt(EQ_FREQS[idx - 1] * freq);
      const highF = idx === EQ_FREQS.length - 1 ? 18000 : Math.sqrt(freq * EQ_FREQS[idx + 1]);
      const start = clamp(Math.floor(lowF / hzPerBin), 1, bins.length - 1);
      const end   = clamp(Math.ceil(highF / hzPerBin), start, bins.length - 1);
      let sum = 0;
      for (let b = start; b <= end; b++) sum += bins[b];
      return sum / (end - start + 1);
    });

    const trackMean = energies.reduce((a, b) => a + b, 0) / energies.length;
    if (trackMean < 12) return; // Abaikan bagian senyap

    const dirCurve = EQ_DIRECTIONS[eqState.eq_direction] || EQ_DIRECTIONS.balanced;
    const alpha = 0.16;

    energies.forEach((e, i) => {
      // Deviasi spektral terhadap rata-rata lagu, disesuaikan menuju target arahan user
      const rel = (trackMean - e) / 28; // positif bila band kurang menonjol
      const desiredOffset = clamp(rel + dirCurve[i] * 0.2, -5.5, 5.5);
      adaptiveOffsets[i] = adaptiveOffsets[i] * (1 - alpha) + desiredOffset * alpha;
    });

    applyEqToAudio(true);
  }

  function persistEqPreferences(showToast = false) {
    store.set('mk_eq', eqState.eq_preset);
    store.set('mk_eq_cfg', JSON.stringify(eqState));
    if (!isAuth()) return;

    clearTimeout(saveDebounce);
    saveDebounce = setTimeout(() => {
      fetch(`${base()}/akun/preferensi`, {
        method: 'PUT',
        headers: { 'X-CSRF-TOKEN': csrf(), 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({
          eq_preset: eqState.eq_preset,
          eq_mode: eqState.eq_mode,
          eq_direction: eqState.eq_direction,
          eq_intensity: Number(eqState.eq_intensity),
          eq_auto_gain: !!eqState.eq_auto_gain,
          eq_macro: eqState.eq_macro,
          eq_bands: computedBands.map(v => Math.round(v * 10) / 10),
        }),
      }).then(r => {
        if (r.ok && showToast) musikkuToast('Pengaturan suara halus disimpan ke akun Anda');
      }).catch(() => {});
    }, showToast ? 10 : 380);
  }

  /* ---------- UI Studio Equalizer ---------- */
  const eqDom = {
    dialog: $('#eqStudioDialog'),
    canvas: $('#eqCanvas'),
    mode: $('#eqMode'),
    dir: $('#eqDirection'),
    intensity: $('#eqIntensity'),
    intensityVal: $('#eqIntensityVal'),
    macroBass: $('#eqMacroBass'),
    macroVocal: $('#eqMacroVocal'),
    macroTreble: $('#eqMacroTreble'),
    macroBassVal: $('#eqMacroBassVal'),
    macroVocalVal: $('#eqMacroVocalVal'),
    macroTrebleVal: $('#eqMacroTrebleVal'),
    sliders: $('#eqSliders'),
    autoGain: $('#eqAutoGain'),
    trackBadge: $('#eqTrackBadge'),
  };

  function buildEqSliders() {
    if (!eqDom.sliders) return;
    eqDom.sliders.innerHTML = '';
    EQ_LABELS.forEach((lbl, idx) => {
      const wrap = document.createElement('div');
      wrap.className = 'eq-band';
      const valEl = document.createElement('b');
      valEl.id = `eqVal${idx}`;
      valEl.textContent = '0.0';
      const inp = document.createElement('input');
      inp.type = 'range';
      inp.min = '-12';
      inp.max = '12';
      inp.step = '0.5';
      inp.value = String(computedBands[idx] || 0);
      inp.dataset.band = String(idx);
      inp.addEventListener('input', () => {
        if (!isAuth()) { $('#proLockDialog')?.showModal(); return; }
        ensureEqGraph();
        eqState.eq_mode = 'manual';
        eqState.eq_preset = 'custom';
        eqState.eq_bands[idx] = Number(inp.value);
        if (eqDom.mode) eqDom.mode.value = 'manual';
        if (el.eq) el.eq.value = 'custom';
        applyEqToAudio(true);
        persistEqPreferences(false);
      });
      const hz = document.createElement('small');
      hz.textContent = lbl;
      wrap.append(valEl, inp, hz);
      eqDom.sliders.appendChild(wrap);
    });
  }

  function syncEqStudioSliders() {
    if (!eqDom.sliders) return;
    computedBands.forEach((db, idx) => {
      const valEl = $(`#eqVal${idx}`);
      const inp = eqDom.sliders.querySelector(`input[data-band="${idx}"]`);
      if (valEl) valEl.textContent = (db > 0 ? '+' : '') + db.toFixed(1);
      if (inp && document.activeElement !== inp) inp.value = String(db);
    });
  }

  function syncEqControlsFromState() {
    if (el.eq) el.eq.value = eqState.eq_preset;
    if (eqDom.mode) eqDom.mode.value = eqState.eq_mode;
    if (eqDom.dir) eqDom.dir.value = eqState.eq_direction;
    if (eqDom.intensity) eqDom.intensity.value = String(eqState.eq_intensity);
    if (eqDom.intensityVal) eqDom.intensityVal.textContent = `${eqState.eq_intensity}%`;
    if (eqDom.macroBass) eqDom.macroBass.value = String(eqState.eq_macro.bass_tilt);
    if (eqDom.macroVocal) eqDom.macroVocal.value = String(eqState.eq_macro.vocal_focus);
    if (eqDom.macroTreble) eqDom.macroTreble.value = String(eqState.eq_macro.treble_air);
    if (eqDom.macroBassVal) eqDom.macroBassVal.textContent = Number(eqState.eq_macro.bass_tilt).toFixed(1);
    if (eqDom.macroVocalVal) eqDom.macroVocalVal.textContent = Number(eqState.eq_macro.vocal_focus).toFixed(1);
    if (eqDom.macroTrebleVal) eqDom.macroTrebleVal.textContent = Number(eqState.eq_macro.treble_air).toFixed(1);
    if (eqDom.autoGain) eqDom.autoGain.checked = !!eqState.eq_auto_gain;

    if (eqDom.trackBadge) {
      const c = cur();
      const g = c?.genre ? ` · ${c.genre}` : '';
      const modeName = MODE_LABELS[eqState.eq_mode] || eqState.eq_mode;
      const dirName = DIR_LABELS[eqState.eq_direction] || eqState.eq_direction;
      eqDom.trackBadge.textContent = c
        ? `Sedang diputar: ${c.title}${g} · ${modeName} · ${dirName}`
        : 'Pilih gaya suara favorit Anda atau biarkan sistem menghaluskan suara secara otomatis.';
    }
  }

  function drawEqCanvas() {
    if (!eqDom.canvas || !eqDom.dialog?.open) { spectrumLoopId = null; return; }
    const ctx = eqDom.canvas.getContext('2d');
    const w = eqDom.canvas.width, h = eqDom.canvas.height;
    ctx.clearRect(0, 0, w, h);

    // Garis referensi 0 dB & grid ringan (Light Theme)
    ctx.strokeStyle = '#e2e8f0';
    ctx.lineWidth = 1;
    [0.25, 0.5, 0.75].forEach(r => {
      ctx.beginPath(); ctx.moveTo(0, h * r); ctx.lineTo(w, h * r); ctx.stroke();
    });

    // Bar spektrum real-time jika audio aktif
    if (analyser && !audio.paused) {
      const bins = new Uint8Array(analyser.frequencyBinCount);
      analyser.getByteFrequencyData(bins);
      const barCount = 40;
      const step = Math.floor(bins.length / 2 / barCount);
      ctx.fillStyle = '#bbf7d0';
      for (let i = 0; i < barCount; i++) {
        const val = bins[i * step] / 255;
        const bh = val * (h - 14);
        const bx = (i / barCount) * w;
        ctx.fillRect(bx + 2, h - bh, (w / barCount) - 4, bh);
      }
    }

    // Kurva respons frekuensi EQ saat ini
    ctx.strokeStyle = '#15803d';
    ctx.lineWidth = 3;
    ctx.beginPath();
    computedBands.forEach((db, idx) => {
      const x = (idx / (computedBands.length - 1)) * (w - 32) + 16;
      const y = (h / 2) - (db / 12) * (h * 0.38);
      if (idx === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });
    ctx.stroke();

    // Titik node tiap band
    computedBands.forEach((db, idx) => {
      const x = (idx / (computedBands.length - 1)) * (w - 32) + 16;
      const y = (h / 2) - (db / 12) * (h * 0.38);
      ctx.fillStyle = '#ffffff';
      ctx.strokeStyle = '#15803d';
      ctx.lineWidth = 2;
      ctx.beginPath(); ctx.arc(x, y, 4.5, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
    });

    spectrumLoopId = requestAnimationFrame(drawEqCanvas);
  }

  function openEqStudio() {
    if (!isAuth()) { $('#proLockDialog')?.showModal(); return; }
    ensureEqGraph();
    syncEqControlsFromState();
    applyEqToAudio(false);
    eqDom.dialog?.showModal();
    if (!spectrumLoopId) drawEqCanvas();
  }

  /* ---------- Core Player Helpers ---------- */
  function setPlayerVisible(visible) {
    player.classList.toggle('player-visible', visible);
    document.body.classList.toggle('player-visible', visible);
  }

  if (!window.musikkuToast) {
    window.musikkuToast = (msg, type = 'ok') => {
      if (typeof window.musikkuNotify === 'function') {
        return window.musikkuNotify(msg, type);
      }
      const t = $('#toast'); if (!t) return;
      t.textContent = msg; t.className = `show toast-${type}`;
      clearTimeout(t._h); t._h = setTimeout(() => t.className = '', 3000);
    };
  }
  const notify = (msg, type = 'ok', title = null) =>
    window.musikkuNotify ? window.musikkuNotify(msg, type, title) : window.musikkuToast(msg, type);

  const itemFrom = r => {
    let aiEq = null;
    try { aiEq = r.dataset.aiEq ? JSON.parse(r.dataset.aiEq) : null; } catch {}
    return {
      id: +r.dataset.id, src: r.dataset.src, title: r.dataset.title, artist: r.dataset.artist,
      genre: r.dataset.genre || '', label: r.dataset.label || '',
      aiEq, aiMood: r.dataset.aiMood || '', aiPitch: Number(r.dataset.aiPitch || 1),
      isVoiceSmoothed: r.dataset.voiceSmoothed === '1',
      href: r.dataset.href, cover: r.dataset.cover, grad: r.dataset.grad, fav: r.dataset.fav === '1',
    };
  };
  const cur = () => st.queue[st.i];

  function renderNow() {
    const it = cur(); if (!it) return;
    el.title.textContent = it.title; el.title.href = it.href;
    el.artist.textContent = it.artist + (it.genre ? ` · ${it.genre}` : '');
    el.cover.style.background = it.cover ? `url(${it.cover}) center/cover` : it.grad;
    el.cover.textContent = it.cover ? '' : it.title.charAt(0).toUpperCase();
    el.fav.classList.toggle('on', !!it.fav);
    document.title = `${it.title} · ${it.artist}`;
    syncEqControlsFromState();
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
    adaptiveOffsets.fill(0);
    if (isAuth()) ensureEqGraph();
    audio.src = st.queue[i].src;
    if (audioCtx?.state === 'suspended') audioCtx.resume().catch(() => {});
    audio.play().then(() => {
      notify(`Memutar: ${st.queue[i].title}`, 'info', 'Pemutar');
    }).catch(error => {
      notify(error.name === 'NotAllowedError'
        ? 'Browser memblokir pemutaran audio. Tekan tombol putar lagi.'
        : 'Tidak dapat memutar file audio ini.', 'error', 'Peringatan');
    });
    renderNow(); renderQueue(); markPlaying(); setSession(st.queue[i]);
    if (isAuth()) applyEqToAudio(true);
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
    if (isAuth()) ensureEqGraph();
    if (audioCtx?.state === 'suspended') audioCtx.resume().catch(() => {});
    if (audio.paused) {
      audio.play().then(() => notify(`Melanjutkan: ${cur()?.title || 'Musik'}`, 'info', 'Pemutar')).catch(() => {});
    } else {
      audio.pause();
      notify(`Pemutaran dijeda`, 'info', 'Pemutar');
    }
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
    if (!res.ok) { notify('Gagal mengubah lagu favorit', 'error', 'Peringatan'); return; }
    const j = await res.json(); setFav(id, j.favorited);
    notify(j.favorited ? 'Ditambahkan ke lagu favorit' : 'Dihapus dari lagu favorit', j.favorited ? 'ok' : 'warn', 'Favorit');
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

  window.addEventListener('musikku:song-finetuned', (ev) => {
    const d = ev.detail || {};
    st.queue.forEach(it => {
      if (it.id === +d.song_id) {
        it.aiEq = d.eq_bands || it.aiEq;
        it.aiMood = d.mood || it.aiMood;
        it.aiPitch = Number(d.pitch_rate || 1);
        it.isVoiceSmoothed = !!d.is_voice_smoothed;
      }
    });
    if (cur()?.id === +d.song_id && isAuth()) {
      applyEqToAudio(true);
      syncEqControlsFromState();
    }
  });

  el.play.onclick = toggle; el.next.onclick = () => next(false); el.prev.onclick = prev;
  el.shuf.onclick = () => {
    setShuffle(!st.shuffle);
    notify(st.shuffle ? 'Mode acak diaktifkan' : 'Mode acak dimatikan', 'info', 'Pemutar');
  };
  el.rep.onclick = () => {
    st.repeat = { off: 'all', all: 'one', one: 'off' }[st.repeat];
    el.rep.classList.toggle('on', st.repeat !== 'off');
    el.rep.textContent = st.repeat === 'one' ? '🔂' : '🔁';
    const repLabel = { off: 'mati', all: 'semua lagu', one: 'satu lagu' }[st.repeat];
    el.rep.title = 'Ulangi: ' + repLabel;
    notify(`Mode ulangi: ${repLabel}`, 'info', 'Pemutar');
  };
  el.fav.onclick = () => cur() && toggleFav(cur().id);
  el.qBtn.onclick = () => el.qBox.classList.toggle('hidden');
  $('#pQueueClear').onclick = () => {
    st.queue = []; st.i = -1; audio.pause(); audio.removeAttribute('src'); st.hasPlayed = false;
    setPlayerVisible(false); renderQueue(); markPlaying();
    notify('Antrean putar dikosongkan', 'warn', 'Antrean');
  };

  // Pro Controls + EQ Studio Bindings
  buildEqSliders();
  syncEqControlsFromState();

  if (el.eqBtn) el.eqBtn.onclick = openEqStudio;
  if (el.eqMobBtn) el.eqMobBtn.onclick = openEqStudio;

  if (el.eq) {
    el.eq.value = eqState.eq_preset;
    el.eq.addEventListener('change', () => {
      if (!isAuth()) { el.eq.value = 'normal'; $('#proLockDialog')?.showModal(); return; }
      ensureEqGraph();
      const val = el.eq.value;
      eqState.eq_preset = val;
      if (val === 'auto_genre' || val === 'adaptive') {
        eqState.eq_mode = val;
        adaptiveOffsets.fill(0);
      } else if (EQ_PRESETS[val]) {
        eqState.eq_mode = 'manual';
        eqState.eq_bands = [...EQ_PRESETS[val]];
      } else {
        eqState.eq_mode = 'manual';
      }
      syncEqControlsFromState();
      applyEqToAudio(true);
      persistEqPreferences(false);
      musikkuToast(`Pengaturan suara: ${el.eq.options[el.eq.selectedIndex].text}`);
    });
  }

  eqDom.mode?.addEventListener('change', () => {
    eqState.eq_mode = eqDom.mode.value;
    if (eqState.eq_mode !== 'manual') eqState.eq_preset = eqState.eq_mode;
    adaptiveOffsets.fill(0);
    syncEqControlsFromState();
    applyEqToAudio(true);
    persistEqPreferences(false);
  });

  eqDom.dir?.addEventListener('change', () => {
    eqState.eq_direction = eqDom.dir.value;
    if (eqState.eq_mode === 'manual') {
      const dir = EQ_DIRECTIONS[eqState.eq_direction] || EQ_DIRECTIONS.balanced;
      eqState.eq_bands = [...dir];
      eqState.eq_preset = 'custom';
    }
    syncEqControlsFromState();
    applyEqToAudio(true);
    persistEqPreferences(false);
  });

  eqDom.intensity?.addEventListener('input', () => {
    eqState.eq_intensity = Number(eqDom.intensity.value);
    if (eqDom.intensityVal) eqDom.intensityVal.textContent = `${eqState.eq_intensity}%`;
    applyEqToAudio(true);
    persistEqPreferences(false);
  });

  const bindMacro = (input, key, labelEl) => {
    input?.addEventListener('input', () => {
      const v = Number(input.value);
      eqState.eq_macro[key] = v;
      if (labelEl) labelEl.textContent = v.toFixed(1);
      applyEqToAudio(true);
      persistEqPreferences(false);
    });
  };
  bindMacro(eqDom.macroBass, 'bass_tilt', eqDom.macroBassVal);
  bindMacro(eqDom.macroVocal, 'vocal_focus', eqDom.macroVocalVal);
  bindMacro(eqDom.macroTreble, 'treble_air', eqDom.macroTrebleVal);

  eqDom.autoGain?.addEventListener('change', () => {
    eqState.eq_auto_gain = eqDom.autoGain.checked;
    applyEqToAudio(true);
    persistEqPreferences(false);
  });

  $('#eqResetBtn')?.addEventListener('click', () => {
    eqState.eq_preset = 'normal';
    eqState.eq_mode = 'manual';
    eqState.eq_direction = 'balanced';
    eqState.eq_macro = { bass_tilt: 0, vocal_focus: 0, treble_air: 0 };
    eqState.eq_bands = [...EQ_PRESETS.normal];
    adaptiveOffsets.fill(0);
    syncEqControlsFromState();
    applyEqToAudio(true);
    persistEqPreferences(true);
  });

  $('#eqSaveBtn')?.addEventListener('click', () => {
    persistEqPreferences(true);
    eqDom.dialog?.close();
  });

  eqDom.dialog?.addEventListener('close', () => {
    if (spectrumLoopId) { cancelAnimationFrame(spectrumLoopId); spectrumLoopId = null; }
  });

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
      if (mins <= 0) { musikkuToast('Penghenti otomatis dimatikan'); return; }
      st.sleepTimer = setTimeout(() => {
        audio.pause();
        el.timer.value = '0';
        musikkuToast('Waktu habis — musik dihentikan otomatis');
      }, mins * 60 * 1000);
      musikkuToast(`Musik akan berhenti otomatis dalam ${mins} menit`);
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
    if (isAuth()) {
      ensureEqGraph();
      if (audioCtx?.state === 'suspended') audioCtx.resume().catch(() => {});
      applyEqToAudio(true);
      if (!adaptiveTimer) adaptiveTimer = setInterval(sampleAdaptiveSpectrum, 220);
    }
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
