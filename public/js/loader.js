/* Musikku Brand Splash Loader — tampil 7.5 detik saat link lokal diklik */
(function () {
  if (window.__musikkuLoader) return;
  window.__musikkuLoader = true;

  const DURATION_MS = 7500;
  let activeTimer = null;
  let rafId = null;
  let isLoading = false;

  const $ = (s) => document.querySelector(s);

  function isEligibleLocalLink(a) {
    if (!a || !a.getAttribute('href')) return null;
    const rawHref = a.getAttribute('href').trim();
    if (!rawHref || rawHref.startsWith('#') || rawHref.startsWith('javascript:') || rawHref.startsWith('mailto:')) {
      return null;
    }
    if (a.target === '_blank' || a.hasAttribute('download') || a.classList.contains('js-no-loader')) {
      return null;
    }

    let url;
    try {
      url = new URL(a.href, window.location.origin);
    } catch {
      return null;
    }

    if (url.origin !== window.location.origin) return null;
    if (url.pathname === window.location.pathname && url.search === window.location.search) return null;
    if (/\/(stream|hires|download|lrs-export)$|\.xml$/i.test(url.pathname)) return null;

    return url;
  }

  function runLoader(url, useTurbo) {
    const overlay = $('#musikkuSplashLoader');
    if (!overlay) {
      window.location.assign(url.href);
      return;
    }

    isLoading = true;
    const bar = $('#mkLoaderBarFill');
    const secEl = $('#mkLoaderSec');
    const destEl = $('#mkLoaderDest');

    if (destEl) {
      destEl.textContent = url.pathname === '/' ? 'Beranda Musikku' : url.pathname.replace(/^\//, '');
    }
    if (bar) bar.style.width = '0%';
    if (secEl) secEl.textContent = '7.5';

    overlay.classList.remove('hidden');
    requestAnimationFrame(() => overlay.classList.add('is-visible'));

    const start = performance.now();

    function step(now) {
      const elapsed = Math.min(DURATION_MS, now - start);
      const pct = (elapsed / DURATION_MS) * 100;
      const rem = Math.max(0, (DURATION_MS - elapsed) / 1000).toFixed(1);
      if (bar) bar.style.width = `${pct.toFixed(2)}%`;
      if (secEl) secEl.textContent = rem;

      if (elapsed < DURATION_MS) {
        rafId = requestAnimationFrame(step);
      }
    }
    rafId = requestAnimationFrame(step);

    // Muat halaman di balik layar agar musik tetap berjalan, lalu tutup tepat setelah 7.5 detik
    if (useTurbo && window.Turbo && typeof window.Turbo.visit === 'function') {
      try {
        window.Turbo.visit(url.href);
      } catch {}
    }

    clearTimeout(activeTimer);
    activeTimer = setTimeout(() => {
      cancelAnimationFrame(rafId);
      if (bar) bar.style.width = '100%';
      if (secEl) secEl.textContent = '0.0';

      if (!useTurbo || !window.Turbo || typeof window.Turbo.visit !== 'function') {
        window.location.assign(url.href);
        return;
      }

      overlay.classList.remove('is-visible');
      setTimeout(() => {
        overlay.classList.add('hidden');
        isLoading = false;
      }, 280);
    }, DURATION_MS);
  }

  document.addEventListener('click', (e) => {
    if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
    if (isLoading) {
      e.preventDefault();
      return;
    }

    const link = e.target.closest('a[href]');
    const url = isEligibleLocalLink(link);
    if (!url) return;

    e.preventDefault();
    e.stopImmediatePropagation();

    const disableTurbo = link.dataset.turbo === 'false' || link.closest('[data-turbo="false"]');
    runLoader(url, !disableTurbo);
  }, true);
})();
