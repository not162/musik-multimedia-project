/* Musikku Realtime Client — Laravel Echo + Pusher / Reverb */
(function () {
  if (window.__musikkuRealtime) return;
  window.__musikkuRealtime = true;

  const cfg = window.__reverbConfig || {};
  const $ = (s) => document.querySelector(s);

  let rtConnected = false;
  let rtDriverLabel = 'Realtime siap';

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
        conn.bind('connected', () => setStatus(`⚡ Live (${cfg.driver})`, true));
        conn.bind('unavailable', () => setStatus('WS Standby', false));
        conn.bind('error', () => setStatus('WS Standby', false));
      }

      // 1. Public Channel: Live song plays & catalog updates
      echo.channel('musikku.live')
        .listen('.song.played', (e) => {
          appendLiveFeed(e);
        })
        .listen('.catalog.updated', (e) => {
          window.musikkuToast?.(`Katalog diperbarui: ${e.title}`);
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

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEcho);
  } else {
    initEcho();
  }
})();
