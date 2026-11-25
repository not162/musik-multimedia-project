/* Musikku PWA Manager — Service Worker, App Install Prompt, Offline Audio Cache */
(function () {
  if (window.__musikkuPwa) return;
  window.__musikkuPwa = true;

  const AUDIO_CACHE = 'musikku-offline-audio-v1';
  let deferredPrompt = null;

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  // 1. Register Service Worker
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
  }

  // 2. Capture native PWA Install Prompt (Chrome, Edge, Android)
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    $$('.js-pwa-install').forEach((btn) => {
      btn.classList.remove('hidden');
      btn.style.display = 'inline-flex';
    });
    const banner = $('#pwaBanner');
    if (banner && !localStorage.getItem('mk_pwa_dismissed')) {
      banner.classList.remove('hidden');
    }
  });

  window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    $$('.js-pwa-install').forEach((btn) => btn.classList.add('hidden'));
    $('#pwaBanner')?.classList.add('hidden');
    window.musikkuToast?.('Aplikasi Musikku berhasil dipasang!');
  });

  document.addEventListener('click', async (e) => {
    const installBtn = e.target.closest('.js-pwa-install');
    if (installBtn) {
      if (deferredPrompt) {
        deferredPrompt.prompt();
        const choice = await deferredPrompt.userChoice;
        if (choice.outcome === 'accepted') deferredPrompt = null;
      } else {
        // Show manual instructions (e.g., iOS Safari or already installed)
        $('#pwaGuideDialog')?.showModal();
      }
      return;
    }

    if (e.target.closest('.js-pwa-dismiss')) {
      localStorage.setItem('mk_pwa_dismissed', '1');
      $('#pwaBanner')?.classList.add('hidden');
    }
  });

  // 3. Online / Offline status indicator
  function syncNetworkStatus() {
    const badge = $('#netStatus');
    if (!badge) return;
    const online = navigator.onLine;
    badge.textContent = online ? '● Online' : '○ Offline';
    badge.className = 'net-pill ' + (online ? 'is-online' : 'is-offline');
  }
  window.addEventListener('online', () => { syncNetworkStatus(); window.musikkuToast?.('Koneksi kembali online'); });
  window.addEventListener('offline', () => { syncNetworkStatus(); window.musikkuToast?.('Mode offline aktif — lagu tersimpan tetap bisa diputar'); });
  document.addEventListener('turbo:load', syncNetworkStatus);
  syncNetworkStatus();

  // 4. Offline Audio Cache Manager (Unlocked for registered users)
  window.musikkuOffline = {
    async listUrls() {
      if (!('caches' in window)) return [];
      const cache = await caches.open(AUDIO_CACHE);
      const reqs = await cache.keys();
      return reqs.map((r) => r.url.split('?')[0]);
    },
    async has(src) {
      if (!('caches' in window) || !src) return false;
      const cache = await caches.open(AUDIO_CACHE);
      const match = await cache.match(src.split('?')[0]);
      return !!match;
    },
    async toggle(song) {
      if (!document.body.dataset.auth) {
        $('#proLockDialog')?.showModal();
        return;
      }
      if (!('caches' in window)) {
        window.musikkuToast?.('Browser tidak mendukung Cache Storage offline.');
        return;
      }
      const key = song.src.split('?')[0];
      const cache = await caches.open(AUDIO_CACHE);
      const exists = await cache.match(key);
      if (exists) {
        await cache.delete(key);
        removeMeta(key);
        window.musikkuToast?.(`"${song.title}" dihapus dari penyimpanan offline`);
        markOfflineButtons();
        return false;
      }

      window.musikkuToast?.(`Mengunduh "${song.title}" untuk mode offline…`);
      const res = await fetch(key);
      if (!res.ok) throw new Error('Download failed');
      await cache.put(key, res);
      saveMeta(key, song);
      window.musikkuToast?.(`"${song.title}" siap diputar saat offline!`);
      markOfflineButtons();
      return true;
    },
    getMetaList() {
      try { return JSON.parse(localStorage.getItem('mk_offline_songs') || '[]'); } catch { return []; }
    }
  };

  function saveMeta(url, song) {
    const list = window.musikkuOffline.getMetaList().filter((s) => s.src !== url);
    list.unshift({ id: song.id, src: url, title: song.title, artist: song.artist, href: song.href });
    localStorage.setItem('mk_offline_songs', JSON.stringify(list));
  }
  function removeMeta(url) {
    const list = window.musikkuOffline.getMetaList().filter((s) => s.src !== url);
    localStorage.setItem('mk_offline_songs', JSON.stringify(list));
  }

  async function markOfflineButtons() {
    const saved = new Set(await window.musikkuOffline.listUrls());
    $$('.js-offline-save').forEach((btn) => {
      const row = btn.closest('.song-row');
      const url = row?.dataset.src?.split('?')[0];
      const isSaved = url && saved.has(url);
      btn.classList.toggle('on', !!isSaved);
      btn.title = isSaved ? 'Tersimpan offline (klik untuk hapus)' : 'Simpan untuk putar offline';
    });
  }
  document.addEventListener('turbo:load', markOfflineButtons);
  document.addEventListener('DOMContentLoaded', markOfflineButtons);
})();
