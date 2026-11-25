<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">

{{-- Phase 7: Enterprise SEO & Social Tags --}}
@php
  $pageTitle = trim($__env->yieldContent('title'));
  $fullTitle = $pageTitle ? "{$pageTitle} · Musikku" : ($seo['title'] ?? 'Musikku — Pemutar Musik Interaktif');
  $metaDesc  = trim($__env->yieldContent('description')) ?: ($seo['description'] ?? config('app.seo.default_description'));
  $ogImage   = $seo['og_image'] ?? asset('images/og-default.png');
@endphp
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $metaDesc }}">
<link rel="canonical" href="{{ url()->current() }}">
<meta property="og:type" content="{{ $seo['og_type'] ?? 'website' }}">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ $metaDesc }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $fullTitle }}">
<meta name="twitter:description" content="{{ $metaDesc }}">

@if(isset($seo['json_ld']))
<script type="application/ld+json">{!! json_encode($seo['json_ld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endif

{{-- PWA Manifest & Mobile App Metadata --}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#16a34a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Musikku">
<link rel="apple-touch-icon" href="{{ asset('images/icons/apple-touch-icon.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icons/icon-192x192.png') }}">

<link rel="stylesheet" href="{{ asset('css/musikku.css') }}?v={{ filemtime(public_path('css/musikku.css')) }}">

{{-- Reverb / Pusher Echo Client Config --}}
@php
  $bcDriver = config('broadcasting.default');
  $bcKey    = $bcDriver === 'pusher' ? config('broadcasting.connections.pusher.key') : config('broadcasting.connections.reverb.key');
  $bcHost   = $bcDriver === 'pusher' ? config('broadcasting.connections.pusher.options.host') : config('broadcasting.connections.reverb.options.host');
  $bcPort   = $bcDriver === 'pusher' ? config('broadcasting.connections.pusher.options.port') : config('broadcasting.connections.reverb.options.port');
  $bcScheme = $bcDriver === 'pusher' ? config('broadcasting.connections.pusher.options.scheme') : config('broadcasting.connections.reverb.options.scheme');
@endphp
<script>
  window.__reverbConfig = {
    driver: @json($bcDriver),
    key: @json($bcKey),
    cluster: @json(config('broadcasting.connections.pusher.options.cluster', 'ap1')),
    host: @json($bcHost),
    port: @json($bcPort),
    scheme: @json($bcScheme),
    userId: @json(auth()->id()),
    isAdmin: @json(auth()->check() && auth()->user()->isAdmin())
  };
</script>

<script type="module">import 'https://cdn.jsdelivr.net/npm/@hotwired/turbo@8.0.12/dist/turbo.es2017-esm.min.js';</script>
<script src="{{ asset('js/vendor/pusher.min.js') }}" defer></script>
<script src="{{ asset('js/vendor/echo.iife.js') }}" defer></script>
<script src="{{ asset('js/realtime.js') }}?v={{ filemtime(public_path('js/realtime.js')) }}" defer></script>
<script src="{{ asset('js/pwa.js') }}?v={{ filemtime(public_path('js/pwa.js')) }}" defer></script>
<script src="{{ asset('js/player.js') }}?v={{ filemtime(public_path('js/player.js')) }}" defer></script>
</head>
<body data-base="{{ url('/') }}"
      @auth data-auth="1" data-eq="{{ auth()->user()->preferences['eq_preset'] ?? 'normal' }}" data-prefs='@json(auth()->user()->preferences ?? [])' @endauth>
<div class="app">
  <aside class="side">
    <a class="brand" href="{{ route('home') }}">
      <img src="{{ asset('images/icons/icon-72x72.png') }}" alt="Musikku" width="28" height="28" style="border-radius:8px">
      Musikku
    </a>
    <nav>
      <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'on' : '' }}">🏠 Beranda</a>
      <a href="{{ route('songs.index') }}" class="{{ request()->routeIs('songs.*') ? 'on' : '' }}">🎧 Jelajah</a>
      <a href="{{ route('charts') }}" class="{{ request()->routeIs('charts') ? 'on' : '' }}">📈 Chart</a>
      @auth
        <div class="sep">Fitur Terbuka</div>
        <a href="{{ route('favorites.index') }}" class="{{ request()->routeIs('favorites.*') ? 'on' : '' }}">♥ Favorit</a>
        <a href="{{ route('playlists.index') }}" class="{{ request()->routeIs('playlists.index', 'playlists.show') ? 'on' : '' }}">📂 Playlist</a>
        <a href="{{ route('history.index') }}" class="{{ request()->routeIs('history.*') ? 'on' : '' }}">🕘 Riwayat</a>
        <a href="{{ route('account.index') }}" class="{{ request()->routeIs('account.*') ? 'on' : '' }}">🎛️ Akun & API</a>
        @if(auth()->user()->isAdmin())
          <div class="sep">Admin</div>
          <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'on' : '' }}">⚙️ Panel Admin</a>
          <a href="{{ route('admin.logs.index') }}" class="{{ request()->routeIs('admin.logs.*') ? 'on' : '' }}">
            🚨 Error & Alert
            <span id="adminAlertBadge" class="badge danger {{ ($unresolvedErrorsCount ?? 0) > 0 ? '' : 'hidden' }}">{{ $unresolvedErrorsCount ?? 0 }}</span>
          </a>
          <a href="{{ route('admin.security.index') }}" class="{{ request()->routeIs('admin.security.*') ? 'on' : '' }}">🛡️ Security & LRS</a>
          <a href="{{ route('admin.system.index') }}" class="{{ request()->routeIs('admin.system.*') ? 'on' : '' }}">🖥️ Sistem & SEO</a>
        @endif
      @else
        <div class="sep">Akun</div>
        <a href="{{ route('register') }}" data-turbo="false">🔓 Buka Fitur Pro</a>
        <a href="{{ route('login') }}" data-turbo="false">🔑 Masuk</a>
      @endauth
    </nav>
    <div style="margin-top:20px;padding:0 8px">
      <button type="button" class="btn ghost sm js-pwa-install" style="width:100%;justify-content:center">📲 Install App (PWA)</button>
    </div>
  </aside>

  <div class="main-col">
    <header class="top">
      <form method="GET" action="{{ route('search') }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari lagu, artis, atau album…">
      </form>
      <div class="top-right">
        <span id="netStatus" class="net-pill is-online">● Online</span>
        <span id="rtStatus" class="rt-pill is-idle">⚡ Realtime</span>
        @auth
          <a href="{{ route('account.index') }}" class="badge {{ auth()->user()->isAdmin() ? 'info' : 'ok' }}">
            {{ auth()->user()->isAdmin() ? 'ADMIN' : 'USER' }} · {{ auth()->user()->name }}
          </a>
          <form method="POST" action="{{ route('logout') }}" style="display:inline" data-turbo="false">@csrf
            <button class="btn ghost sm">Keluar</button>
          </form>
        @else
          <a class="btn ghost sm" href="{{ route('login') }}" data-turbo="false">Masuk</a>
          <a class="btn sm" href="{{ route('register') }}" data-turbo="false">Daftar Gratis</a>
        @endauth
      </div>
    </header>

    <main id="content">
      <div id="pwaBanner" class="pwa-banner hidden">
        <div>
          <b>📲 Pasang Musikku sebagai Aplikasi</b>
          <div class="mut" style="font-size:13px">Akses cepat dari layar utama, pemutaran latar belakang, dan dukungan mode offline.</div>
        </div>
        <div class="actions" style="margin:0">
          <button type="button" class="btn sm js-pwa-install">Pasang Sekarang</button>
          <button type="button" class="btn ghost sm js-pwa-dismiss">Nanti</button>
        </div>
      </div>

      @if(session('ok'))<div class="flash">{{ session('ok') }}</div>@endif
      @yield('content')
    </main>
  </div>
</div>

{{-- Pemutar: permanen, tidak ikut diganti saat navigasi --}}
<div id="player" data-turbo-permanent>
  <div class="p-now">
    <div class="cover sm" id="pCover" style="background:#e2e8f0;color:#475569"></div>
    <div class="p-meta"><a id="pTitle" href="#">Belum ada lagu</a><small id="pArtist">Pilih lagu untuk diputar</small></div>
    <button id="pFav" class="icon" title="Favorit">♥</button>
    <button id="pOffline" class="icon" title="Simpan Offline (PWA)">📥</button>
    <button id="pEqMobileBtn" class="icon eq-mob-btn" title="Buka Studio Equalizer">🎛️</button>
  </div>
  <div class="p-center">
    <div class="p-btns">
      <button id="pShuffle" class="icon" title="Acak">🔀</button>
      <button id="pPrev" class="icon" title="Sebelumnya">⏮</button>
      <button id="pPlay" class="icon play" title="Putar/Jeda (Spasi)">▶</button>
      <button id="pNext" class="icon" title="Berikutnya">⏭</button>
      <button id="pRepeat" class="icon" title="Ulangi: mati">🔁</button>
    </div>
    <div class="p-seek"><span id="pCur">0:00</span><input type="range" id="pSeek" min="0" max="1000" value="0"><span id="pDur">0:00</span></div>
  </div>
  <div class="p-right">
    <select id="pEq" title="Equalizer & Algoritma (Akun Terdaftar)">
      <option value="normal">EQ: Normal @guest 🔒@endguest</option>
      <option value="bass">EQ: Bass Boost</option>
      <option value="vocal">EQ: Vocal</option>
      <option value="lofi">EQ: Lo-Fi</option>
      <option value="rock">EQ: Rock</option>
      <option value="acoustic">EQ: Akustik</option>
      <option value="electronic">EQ: Elektronik</option>
      <option value="auto_genre">⚡ Adaptif Genre</option>
      <option value="adaptive">🎯 Adaptif Real-Time</option>
      <option value="custom">🎛️ Kustom</option>
    </select>
    <button id="pEqStudioBtn" type="button" class="btn ghost sm" title="Atur Algoritma & Kurva EQ 10-Band">🎛️ EQ</button>
    <select id="pSpeed" title="Kecepatan Putar (Akun Terdaftar)">
      <option value="1">1.0x @guest 🔒@endguest</option>
      <option value="0.75">0.75x</option>
      <option value="1.25">1.25x</option>
      <option value="1.5">1.5x</option>
    </select>
    <select id="pTimer" title="Sleep Timer (Akun Terdaftar)">
      <option value="0">Timer: Off @guest 🔒@endguest</option>
      <option value="15">15 mnt</option>
      <option value="30">30 mnt</option>
      <option value="60">60 mnt</option>
    </select>
    <button id="pQueueBtn" class="btn ghost sm">☰ Antrean</button>
    <span>🔊</span><input type="range" id="pVol" min="0" max="100" value="80">
  </div>
  <div id="pQueue" class="queue hidden">
    <h4><span>Antrean</span><button id="pQueueClear" class="btn ghost sm">Kosongkan</button></h4>
    <ol id="pQueueList"></ol>
  </div>
  <audio id="audio" crossorigin="anonymous" preload="metadata"></audio>
</div>

@auth
<dialog id="plDialog">
  <h3>Tambah ke playlist</h3>
  @forelse(auth()->user()->playlists()->latest()->get(['id', 'name']) as $pl)
    <button type="button" class="pl-opt" data-pl="{{ $pl->id }}">{{ $pl->name }}</button>
  @empty
    <p class="mut">Belum ada playlist. <a href="{{ route('playlists.index') }}">Buat dulu</a>.</p>
  @endforelse
  <form method="dialog" style="margin-top:10px"><button class="btn ghost sm">Tutup</button></form>
</dialog>
@endauth

<dialog id="proLockDialog">
  <h3>🔓 Buka Fitur Akun Terdaftar</h3>
  <p class="mut" style="font-size:14px">Daftar gratis atau masuk untuk membuka fitur penuh:</p>
  <ul class="tier-list" style="margin-bottom:14px">
    <li>✅ Simpan lagu ke <b>Favorit</b> & <b>Playlist Kustom</b></li>
    <li>✅ <b>Audio Equalizer 5-Band</b>, Kecepatan Putar & Sleep Timer</li>
    <li>✅ <b>Simpan Offline (PWA)</b> untuk putar tanpa internet</li>
    <li>✅ <b>Rekomendasi Personal</b>, Riwayat & Token API</li>
  </ul>
  <div class="actions">
    <a class="btn" href="{{ route('register') }}" data-turbo="false">Daftar Gratis</a>
    <a class="btn ghost" href="{{ route('login') }}" data-turbo="false">Masuk</a>
    <form method="dialog"><button class="btn ghost sm">Tutup</button></form>
  </div>
</dialog>

<dialog id="pwaGuideDialog">
  <h3>📲 Pasang Aplikasi Musikku (PWA)</h3>
  <p class="mut" style="font-size:14px">Jadikan Musikku sebagai aplikasi mandiri di perangkat Anda:</p>
  <ul class="tier-list" style="margin-bottom:14px">
    <li>💻 <b>Chrome / Edge Desktop:</b> Klik ikon <b>Install App</b> di ujung kanan address bar.</li>
    <li>🤖 <b>Android Chrome:</b> Buka menu <b>⋮ → Tambah ke layar utama / Install app</b>.</li>
    <li>🍎 <b>iOS Safari:</b> Tekan tombol <b>Share (⬆️) → Add to Home Screen</b>.</li>
  </ul>
  <form method="dialog"><button class="btn sm">Mengerti</button></form>
</dialog>

<dialog id="eqStudioDialog" class="eq-dialog">
  <div class="eq-head">
    <div>
      <span class="badge ok">🎛️ 10-BAND DSP EQUALIZER</span>
      <h3 style="margin:4px 0 2px">Studio Equalizer & Algoritma Adaptif</h3>
      <small id="eqTrackBadge" class="mut">Sesuaikan kurva frekuensi atau biarkan algoritma menyelaraskan suara sesuai arahan Anda.</small>
    </div>
    <form method="dialog"><button class="icon" title="Tutup">✕</button></form>
  </div>

  <canvas id="eqCanvas" class="eq-canvas" width="600" height="130"></canvas>

  <div class="eq-controls-grid">
    <label>Mode Algoritma
      <select id="eqMode">
        <option value="manual">Manual / Preset</option>
        <option value="auto_genre">⚡ Adaptif Genre Lagu</option>
        <option value="adaptive">🎯 Adaptif Spektrum Real-Time</option>
      </select>
    </label>
    <label>Arah Karakter Suara
      <select id="eqDirection">
        <option value="balanced">Seimbang (Studio Reference)</option>
        <option value="warm_bass">Hangat &amp; Deep Bass</option>
        <option value="bright_clear">Jernih &amp; Detail Vokal</option>
        <option value="punchy">Bertenaga (V-Shape Dinamis)</option>
        <option value="focus_study">Fokus Belajar (Anti-Lelah)</option>
      </select>
    </label>
    <label>Intensitas Algoritma: <b id="eqIntensityVal">70%</b>
      <input type="range" id="eqIntensity" min="0" max="100" value="70">
    </label>
  </div>

  <div class="eq-macro-grid">
    <label>Arah Bass Tilt (<span id="eqMacroBassVal">0.0</span> dB)
      <input type="range" id="eqMacroBass" min="-6" max="6" step="0.5" value="0">
    </label>
    <label>Arah Fokus Vokal (<span id="eqMacroVocalVal">0.0</span> dB)
      <input type="range" id="eqMacroVocal" min="-6" max="6" step="0.5" value="0">
    </label>
    <label>Arah Treble Air (<span id="eqMacroTrebleVal">0.0</span> dB)
      <input type="range" id="eqMacroTreble" min="-6" max="6" step="0.5" value="0">
    </label>
  </div>

  <div id="eqSliders" class="eq-sliders"></div>

  <div class="eq-foot">
    <label class="inline" style="font-size:13px;cursor:pointer">
      <input type="checkbox" id="eqAutoGain" checked>
      <span>Auto-Gain Headroom + Compressor (Anti-Clipping)</span>
    </label>
    <div class="actions" style="margin:0">
      <button type="button" id="eqResetBtn" class="btn ghost sm">↺ Reset Datar</button>
      <button type="button" id="eqSaveBtn" class="btn sm">💾 Simpan Arahan EQ</button>
    </div>
  </div>
</dialog>

<div id="toast"></div>
</body>
</html>
