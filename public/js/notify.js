/* Musikku Unified Alert & Notification Center */
(function () {
  if (window.__musikkuNotifyInit) return;
  window.__musikkuNotifyInit = true;

  const STORE_KEY = 'mk_notifications_v1';
  const $ = (s) => document.querySelector(s);

  function loadList() {
    try { return JSON.parse(sessionStorage.getItem(STORE_KEY) || '[]'); } catch { return []; }
  }
  function saveList(list) {
    try { sessionStorage.setItem(STORE_KEY, JSON.stringify(list.slice(0, 25))); } catch {}
  }

  function formatTypeLabel(type) {
    return {
      ok: 'Berhasil',
      success: 'Berhasil',
      error: 'Peringatan',
      danger: 'Peringatan',
      warn: 'Perhatian',
      info: 'Info',
    }[type] || 'Info';
  }

  function renderDrawer() {
    const listEl = $('#notifList');
    const badgeEl = $('#notifBadge');
    const items = loadList();
    const unread = items.filter((i) => !i.read).length;

    if (badgeEl) {
      badgeEl.textContent = String(unread);
      badgeEl.classList.toggle('hidden', unread === 0);
    }
    if (!listEl) return;

    listEl.innerHTML = '';
    if (!items.length) {
      listEl.innerHTML = '<li class="mut" style="padding:10px;font-size:13px">Belum ada pemberitahuan aktivitas.</li>';
      return;
    }

    items.forEach((item) => {
      const li = document.createElement('li');
      li.className = `notif-item is-${item.type}`;
      li.innerHTML = `<div class="notif-top"><span class="badge ${item.type === 'error' || item.type === 'danger' ? 'danger' : (item.type === 'warn' ? 'warn' : (item.type === 'ok' || item.type === 'success' ? 'ok' : 'info'))}"></span><small class="mut"></small></div><div class="notif-msg"></div>`;
      li.querySelector('.badge').textContent = item.title || formatTypeLabel(item.type);
      li.querySelector('small').textContent = item.time;
      li.querySelector('.notif-msg').textContent = item.message;
      listEl.appendChild(li);
    });
  }

  window.musikkuNotify = function (message, type = 'ok', title = null) {
    if (!message) return;
    const norm = type === 'success' ? 'ok' : (type === 'danger' ? 'error' : type);

    // 1. Tampilkan popup toast sesuai tipe pemberitahuan
    const toast = $('#toast');
    if (toast) {
      toast.textContent = message;
      toast.className = `show toast-${norm}`;
      clearTimeout(toast._h);
      toast._h = setTimeout(() => { toast.className = ''; }, 3600);
    }

    // 2. Simpan ke Lonceng Pemberitahuan agar semua riwayat aksi terlihat
    const items = loadList();
    const now = new Date();
    const time = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    if (!items.length || items[0].message !== message || items[0].type !== norm) {
      items.unshift({ id: Date.now(), message, type: norm, title, time, read: false });
      saveList(items);
    }
    renderDrawer();
  };

  window.musikkuToast = (msg, type = 'ok') => window.musikkuNotify(msg, type);

  function flushServerFlashes() {
    renderDrawer();
    const box = $('#flashAlerts');
    if (!box || box.dataset.flushed === '1') return;
    box.dataset.flushed = '1';

    let items = [];
    try { items = JSON.parse(box.dataset.flashItems || '[]'); } catch {}
    items.forEach((it) => window.musikkuNotify(it.message, it.type || 'ok', it.title || null));
  }

  document.addEventListener('click', (e) => {
    const bell = e.target.closest('#notifBellBtn');
    const drawer = $('#notifDrawer');
    if (bell && drawer) {
      e.stopPropagation();
      const opening = drawer.classList.contains('hidden');
      drawer.classList.toggle('hidden');
      if (opening) {
        const items = loadList().map((i) => Object.assign({}, i, { read: true }));
        saveList(items);
        renderDrawer();
      }
      return;
    }

    if (e.target.closest('#notifClearBtn')) {
      saveList([]);
      renderDrawer();
      return;
    }

    if (drawer && !drawer.classList.contains('hidden') && !e.target.closest('#notifDrawer')) {
      drawer.classList.add('hidden');
    }
  });

  document.addEventListener('DOMContentLoaded', flushServerFlashes);
  document.addEventListener('turbo:load', flushServerFlashes);
})();
