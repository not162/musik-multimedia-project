/* Musikku Realtime Client — Laravel Echo + Pusher / Reverb */
(function () {
  if (window.__musikkuRealtime) return;
  window.__musikkuRealtime = true;

  const cfg = window.__reverbConfig || {};
  const $ = (s) => document.querySelector(s);
  const baseUrl = () => (document.body.dataset.base || '').replace(/\/$/, '');

  let rtConnected = false;
  let rtDriverLabel = '⚡ Siap';
  let currentVersion = Number(document.body.dataset.catalogVersion || 1);
  let refreshTimer = null;

  function canSafelyRefreshCatalogView() {
    if (document.hidden) return false;
    if (document.querySelector('dialog[open]')) return false;
    const tag = document.activeElement?.tagName;
    if (tag && /INPUT|TEXTAREA|SELECT/.test(tag)) return false;
    const path = window.location.pathname;
    if (path.startsWith('/admin/songs/') || path.startsWith('/masuk') || path.startsWith('/daftar')) return false;
    return true;
  }

  function refreshCatalogView(reasonMsg) {
    if (reasonMsg) window.musikkuToast?.(reasonMsg);
    if (!canSafelyRefreshCatalogView()) return;
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(() => {
      if (window.Turbo && typeof window.Turbo.visit === 'function') {
        window.Turbo.visit(window.location.href, { action: 'replace' });
      }
    }, 350);
  }

  function setStatus(text, ok) {
    rtDriverLabel = text;
    rtConnected = !!ok;
    const el = $('#rtStatus');
    if (!el) return;
    el.textContent = text;
    el.className = 'rt-pill ' + (ok ? 'is-live' : 'is-idle');
  }

  document.addEventListener('turbo:load', () => {
    setStatus(rtDriverLabel, rtConnected);
  });

  function appendLiveFeed(item) {
    const feed = $('#liveFeed');
    if (!feed) return;
    const empty = feed.querySelector('.rt-empty');
    if (empty) empty.remove();

    const li = document.createElement('li');
    li.className = 'rt-item';
    li.innerHTML = `<span class="rt-dot"></span><div><b></b> <small></small></div><span class="mut rt-time"></span>`;
    li.querySelector('b').textContent = `${item.title} — ${item.artist}`;
    li.querySelector('small').textContent = `diputar oleh ${item.listener_name} (${item.play_count}×)`;
    li.querySelector('.rt-time').textContent = item.played_at || '';
    feed.prepend(li);
    while (feed.children.length > 6) feed.lastElementChild.remove();
  }

  function updateOnlineCount(members) {
    const el = $('#onlineListenersCount');
    if (el) el.textContent = String(members.length);
    const list = $('#onlineListenersList');
    if (list) {
      list.innerHTML = '';
      members.slice(0, 8).forEach((m) => {
        const span = document.createElement('span');
        span.className = 'chip sm';
        span.textContent = `● ${m.name}`;
        list.appendChild(span);
      });
    }
  }

  function initEcho() {
    if (!window.Echo || !window.Pusher || !cfg.key) {
      setStatus('Realtime siap', false);
      return;
    }

    try {
      window.Pusher.logToConsole = false;
      const isReverb = cfg.driver === 'reverb';

      const echo = new window.Echo({
        broadcaster: isReverb ? 'reverb' : 'pusher',
        key: cfg.key,
        cluster: cfg.cluster || 'ap1',
        wsHost: isReverb ? cfg.host : undefined,
        wsPort: isReverb ? Number(cfg.port || 8080) : undefined,
        wssPort: isReverb ? Number(cfg.port || 8080) : undefined,
        forceTLS: cfg.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: (document.body.dataset.base || '') + '/broadcasting/auth',
        auth: {
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
          },
        },
      });

      window.musikkuEcho = echo;

      const conn = echo.connector?.pusher?.connection;
      if (conn) {
        conn.bind('connected', () => setStatus('⚡ Terhubung Langsung', true));
        conn.bind('unavailable', () => setStatus('⚡ Siap', false));
        conn.bind('error', () => setStatus('⚡ Siap', false));
      }

      // 1. Public Channel: Live song plays, AI fine-tuning & catalog/label updates
      echo.channel('musikku.live')
        .listen('.song.played', (e) => {
          appendLiveFeed(e);
        })
        .listen('.song.finetuned', (e) => {
          const suffix = e.is_voice_smoothed ? ' (Halus & Jernih)' : (e.mood ? ' (' + e.mood + ')' : '');
          window.musikkuToast?.(`✨ Suara lagu dirapikan AI: ${e.title}${suffix}`);
          document.querySelectorAll(`.song-row[data-id="${e.song_id}"]`).forEach((row) => {
            if (e.eq_bands) row.dataset.aiEq = JSON.stringify(e.eq_bands);
            row.dataset.aiMood = e.mood || '';
            row.dataset.aiPreamp = String(e.preamp_db ?? 0);
            row.dataset.aiPitch = String(e.pitch_rate ?? 1);
            row.dataset.voiceSmoothed = e.is_voice_smoothed ? '1' : '0';
            if (e.label) row.dataset.label = e.label;
            const slot = row.querySelector('.js-ai-badge');
            if (slot) {
              slot.innerHTML = e.is_voice_smoothed
                ? '<span class="badge ok">✨ Suara Halus &amp; Jernih</span>'
                : `<span class="badge ok">✨ Suara AI${e.mood ? ' · ' + e.mood : ''}</span>`;
            }
          });
          window.dispatchEvent(new CustomEvent('musikku:song-finetuned', { detail: e }));
        })
        .listen('.catalog.updated', (e) => {
          const prefix = e.type === 'label' ? '🏷️ Label Musik Baru' : '🎵 Daftar lagu diperbarui';
          currentVersion += 1;
          refreshCatalogView(`${prefix}: ${e.title}`);
        });

      // 2. Registered User Channels (Presence + Private Notifications)
      if (cfg.userId) {
        echo.private(`user.${cfg.userId}`)
          .listen('.user.notification', (e) => {
            window.musikkuToast?.(`${e.title}: ${e.message}`);
          });

        let members = [];
        echo.join('musikku.listeners')
          .here((users) => { members = users; updateOnlineCount(members); })
          .joining((user) => {
            if (!members.some((m) => m.id === user.id)) members.push(user);
            updateOnlineCount(members);
          })
          .leaving((user) => {
            members = members.filter((m) => m.id !== user.id);
            updateOnlineCount(members);
          });
      }

      // 3. Admin-Only Private Alert Channel (Phase 9 Error Tracking & Security Alerts)
      if (cfg.isAdmin) {
        echo.private('admin.alerts')
          .listen('.error.alert', (e) => {
            const badge = $('#adminAlertBadge');
            if (badge) {
              const cur = parseInt(badge.textContent || '0', 10) + 1;
              badge.textContent = String(cur);
              badge.classList.remove('hidden');
            }
            window.musikkuToast?.(`🚨 [${e.level.toUpperCase()}] ${e.message}`);

            const tbl = $('#errorLogsBody');
            if (tbl) {
              const tr = document.createElement('tr');
              tr.className = 'row-new-alert';
              tr.innerHTML = `<td>#${e.id}</td><td><span class="badge danger">${e.level}</span></td><td></td><td>${e.channel}</td><td>${e.created_at}</td><td><span class="badge warn">Open</span></td><td>Baru saja</td>`;
              tr.children[2].textContent = e.message;
              tbl.prepend(tr);
            }
          })
          .listen('.security.alert', (e) => {
            window.musikkuToast?.(`🛡️ Keamanan: ${e.message}`);
          });
      }
    } catch (err) {
      setStatus('WS Standby', false);
    }
  }

  // Fallback auto-sync ringan agar Guest Public & User selalu melihat lagu .mp3/.mp4 & label baru otomatis
  setInterval(async () => {
    if (document.hidden) return;
    try {
      const res = await fetch(`${baseUrl()}/api/v1/live-sync`, { headers: { Accept: 'application/json' } });
      if (!res.ok) return;
      const data = await res.json();
      const serverVer = Number(data.catalog_version || 1);
      if (serverVer > currentVersion) {
        currentVersion = serverVer;
        document.body.dataset.catalogVersion = String(serverVer);
        const ev = data.last_event;
        const msg = ev?.title
          ? (ev.action === 'created' ? `🎵 Lagu baru tersedia: ${ev.title}` : `✨ Pembaruan musik: ${ev.title}`)
          : '✨ Daftar lagu diperbarui secara langsung';
        refreshCatalogView(msg);
      }
    } catch {}
  }, 5000);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEcho);
  } else {
    initEcho();
  }
})();
