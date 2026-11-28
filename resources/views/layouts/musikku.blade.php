<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">

{{-- Judul, Deskripsi & Info Berbagi Sosial --}}
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

{{-- Dukungan Aplikasi Perangkat --}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#16a34a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Musikku">
<link rel="apple-touch-icon" href="{{ asset('images/icons/apple-touch-icon.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icons/icon-192x192.png') }}">

<link rel="stylesheet" href="{{ asset('css/musikku.css') }}?v={{ filemtime(public_path('css/musikku.css')) }}">

{{-- Konfigurasi Pembaruan Langsung --}}
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
<script src="{{ asset('js/notify.js') }}?v={{ filemtime(public_path('js/notify.js')) }}" defer></script>
<script src="{{ asset('js/realtime.js') }}?v={{ filemtime(public_path('js/realtime.js')) }}" defer></script>
<script src="{{ asset('js/pwa.js') }}?v={{ filemtime(public_path('js/pwa.js')) }}" defer></script>
<script src="{{ asset('js/player.js') }}?v={{ filemtime(public_path('js/player.js')) }}" defer></script>
</head>
<body data-base="{{ url('/') }}"
      data-catalog-version="{{ \App\Services\CatalogSyncService::version() }}"
      @auth data-auth="1" data-eq="{{ auth()->user()->preferences['eq_preset'] ?? 'normal' }}" data-prefs='@json(auth()->user()->preferences ?? [])' @endauth>
<div class="app">
  <aside class="side">
    <a class="brand" href="{{ route('home') }}">
      <img src="{{ asset('images/icons/icon-72x72.png') }}" alt="Musikku" width="28" height="28" style="border-radius:8px">
      Musikku
    </a>
    <nav>
      <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'on' : '' }}">🏠 Beranda</a>
      <a href="{{ route('songs.index') }}" class="{{ request()->routeIs('songs.*') ? 'on' : '' }}">🎧 Jelajah Lagu</a>
      <a href="{{ route('charts') }}" class="{{ request()->routeIs('charts') ? 'on' : '' }}">📈 Lagu Populer</a>
      <a href="{{ route('labels.index') }}" class="{{ request()->routeIs('labels.*') ? 'on' : '' }}">🏷️ Label Musik</a>
      @auth
        <div class="sep">Koleksi &amp; Studio</div>
        <a href="{{ route('studio.index') }}" class="{{ request()->routeIs('studio.*') ? 'on' : '' }}">🎛️ Studio Musik</a>
        <a href="{{ route('favorites.index') }}" class="{{ request()->routeIs('favorites.*') ? 'on' : '' }}">♥ Favorit</a>
        <a href="{{ route('playlists.index') }}" class="{{ request()->routeIs('playlists.index', 'playlists.show') ? 'on' : '' }}">📂 Daftar Putar</a>
        <a href="{{ route('history.index') }}" class="{{ request()->routeIs('history.*') ? 'on' : '' }}">🕘 Riwayat</a>
        <a href="{{ route('account.index') }}" class="{{ request()->routeIs('account.*') ? 'on' : '' }}">
          {{ auth()->user()->isAdmin() ? '🎛️ Akun & Akses' : '👤 Profil Saya' }}
        </a>
        @if(auth()->user()->isAdmin())
          <div class="sep">Pengelola</div>
          <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'on' : '' }}">⚙️ Ringkasan Admin</a>
          <a href="{{ route('admin.ai.index') }}" class="{{ request()->routeIs('admin.ai.*') ? 'on' : '' }}">🤖 Studio AI & Suara</a>
          <a href="{{ route('admin.labels.index') }}" class="{{ request()->routeIs('admin.labels.*') ? 'on' : '' }}">🏷️ Kelola Label</a>
          <a href="{{ route('admin.logs.index') }}" class="{{ request()->routeIs('admin.logs.*') ? 'on' : '' }}">
            🚨 Catatan Masalah
            <span id="adminAlertBadge" class="badge danger {{ ($unresolvedErrorsCount ?? 0) > 0 ? '' : 'hidden' }}">{{ $unresolvedErrorsCount ?? 0 }}</span>
          </a>
          <a href="{{ route('admin.security.index') }}" class="{{ request()->routeIs('admin.security.*') ? 'on' : '' }}">🛡️ Keamanan</a>
          <a href="{{ route('admin.system.index') }}" class="{{ request()->routeIs('admin.system.*') ? 'on' : '' }}">🖥️ Pengaturan Web</a>
        @endif
      @else
        <div class="sep">Akun</div>
        <a href="{{ route('register') }}" data-turbo="false">🔓 Daftar Gratis</a>
        <a href="{{ route('login') }}" data-turbo="false">🔑 Masuk</a>
      @endauth
    </nav>
    <div style="margin-top:20px;padding:0 8px">
      <button type="button" class="btn ghost sm js-pwa-install" style="width:100%;justify-content:center">📲 Pasang Aplikasi</button>
    </div>
  </aside>

  <div class="main-col">
    <header class="top">
      <form method="GET" action="{{ route('search') }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari judul lagu, penyanyi, atau album…">
      </form>
      <div class="top-right">
        <span id="netStatus" class="net-pill is-online">● Tersambung</span>
        <span id="rtStatus" class="rt-pill is-idle">⚡ Siap</span>
        <div class="notif-wrap">
          <button type="button" id="notifBellBtn" class="notif-btn" title="Pemberitahuan &amp; Alert">
            🔔 <span id="notifBadge" class="badge danger hidden">0</span>
          </button>
          <div id="notifDrawer" class="notif-drawer hidden">
            <div class="notif-head">
              <b>🔔 Pemberitahuan Aktivitas</b>
              <button type="button" id="notifClearBtn" class="btn ghost sm">Bersihkan</button>
            </div>
            <ul id="notifList" class="notif-list"></ul>
          </div>
        </div>
        @auth
          <a href="{{ route('account.index') }}" class="badge {{ auth()->user()->isAdmin() ? 'info' : 'ok' }}" style="gap:6px;padding:3px 10px 3px 4px">
            <span class="avatar-chip sm">
              @if(auth()->user()->avatar_url)
                <img src="{{ auth()->user()->avatar_url }}" alt="{{ auth()->user()->name }}">
              @else
                <span>{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
              @endif
            </span>
            {{ auth()->user()->name }} · {{ auth()->user()->role }}
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
          <b>📲 Pasang Musikku di Perangkat Anda</b>
          <div class="mut" style="font-size:13px">Buka lebih cepat dari layar utama dan dengarkan lagu simpanan tanpa internet.</div>
        </div>
        <div class="actions" style="margin:0">
          <button type="button" class="btn sm js-pwa-install">Pasang Sekarang</button>
          <button type="button" class="btn ghost sm js-pwa-dismiss">Nanti</button>
        </div>
      </div>

      @php
        $flashItems = [];
        if (session('ok'))    $flashItems[] = ['type' => 'ok',    'title' => 'Berhasil',  'message' => session('ok')];
        if (session('info'))  $flashItems[] = ['type' => 'info',  'title' => 'Info',      'message' => session('info')];
        if (session('warn'))  $flashItems[] = ['type' => 'warn',  'title' => 'Perhatian', 'message' => session('warn')];
        if (session('error')) $flashItems[] = ['type' => 'error', 'title' => 'Peringatan','message' => session('error')];
        if ($errors->any()) {
            foreach ($errors->all() as $errMsg) {
                $flashItems[] = ['type' => 'error', 'title' => 'Peringatan', 'message' => $errMsg];
            }
        }
      @endphp
      <div id="flashAlerts" data-flash-items='@json($flashItems)'>
        @if(session('ok'))<div class="flash">✅ {{ session('ok') }}</div>@endif
        @if(session('info'))<div class="flash is-info">ℹ️ {{ session('info') }}</div>@endif
        @if(session('warn'))<div class="flash is-warn">⚠️ {{ session('warn') }}</div>@endif
        @if(session('error'))<div class="flash is-error">🚨 {{ session('error') }}</div>@endif
      </div>
      @yield('content')
    </main>
  </div>
</div>

{{-- Pemutar: tetap menyala saat berpindah halaman --}}
<div id="player" data-turbo-permanent>
  <div class="p-now">
    <div class="cover sm" id="pCover" style="background:#e2e8f0;color:#475569"></div>
    <div class="p-meta"><a id="pTitle" href="#">Belum ada lagu</a><small id="pArtist">Pilih lagu untuk didengarkan</small></div>
    <button id="pFav" class="icon" title="Simpan ke Favorit">♥</button>
    <button id="pOffline" class="icon" title="Simpan agar bisa diputar tanpa internet">📥</button>
    <button id="pEqMobileBtn" class="icon eq-mob-btn" title="Atur Kejernihan & Bass Suara">🎛️</button>
  </div>
  <div class="p-center">
    <div class="p-btns">
      <button id="pShuffle" class="icon" title="Putar Acak">🔀</button>
      <button id="pPrev" class="icon" title="Lagu Sebelumnya">⏮</button>
      <button id="pPlay" class="icon play" title="Putar / Jeda (Spasi)">▶</button>
      <button id="pNext" class="icon" title="Lagu Berikutnya">⏭</button>
      <button id="pRepeat" class="icon" title="Ulangi: mati">🔁</button>
    </div>
    <div class="p-seek"><span id="pCur">0:00</span><input type="range" id="pSeek" min="0" max="1000" value="0"><span id="pDur">0:00</span></div>
  </div>
  <div class="p-right">
    <select id="pEq" title="Gaya Suara (Khusus Anggota)">
      <option value="normal">Suara: Standar @guest 🔒@endguest</option>
      <option value="bass">Suara: Bass Mantap</option>
      <option value="vocal">Suara: Vokal Jernih</option>
      <option value="lofi">Suara: Santai Lembut</option>
      <option value="rock">Suara: Rock Bertenaga</option>
      <option value="acoustic">Suara: Akustik Alami</option>
      <option value="electronic">Suara: Elektronik</option>
      <option value="auto_genre">⚡ Otomatis Sesuai Lagu</option>
      <option value="adaptive">🎯 Haluskan Otomatis</option>
      <option value="custom">🎛️ Atur Sendiri</option>
    </select>
    <button id="pEqStudioBtn" type="button" class="btn ghost sm" title="Buka Studio Musik">🎛️ Studio Musik</button>
    <select id="pSpeed" title="Kecepatan Lagu (Khusus Anggota)">
      <option value="1">1.0x @guest 🔒@endguest</option>
      <option value="0.75">0.75x</option>
      <option value="1.25">1.25x</option>
      <option value="1.5">1.5x</option>
    </select>
    <select id="pTimer" title="Hentikan Musik Otomatis (Khusus Anggota)">
      <option value="0">Timer: Mati @guest 🔒@endguest</option>
      <option value="15">15 menit</option>
      <option value="30">30 menit</option>
      <option value="60">60 menit</option>
    </select>
    <button id="pQueueBtn" class="btn ghost sm">☰ Antrean</button>
    <span>🔊</span><input type="range" id="pVol" min="0" max="100" value="80">
  </div>
  <div id="pQueue" class="queue hidden">
    <h4><span>Antrean Putar</span><button id="pQueueClear" class="btn ghost sm">Kosongkan</button></h4>
    <ol id="pQueueList"></ol>
  </div>
  <audio id="audio" crossorigin="anonymous" preload="metadata"></audio>
</div>

@auth
<dialog id="plDialog">
  <h3>Masukkan ke Daftar Putar</h3>
  @forelse(auth()->user()->playlists()->latest()->get(['id', 'name']) as $pl)
    <button type="button" class="pl-opt" data-pl="{{ $pl->id }}">{{ $pl->name }}</button>
  @empty
    <p class="mut">Anda belum punya daftar putar. <a href="{{ route('playlists.index') }}">Buat sekarang</a>.</p>
  @endforelse
  <form method="dialog" style="margin-top:10px"><button class="btn ghost sm">Tutup</button></form>
</dialog>
@endauth

<dialog id="proLockDialog">
  <h3>🔓 Buka Fitur Akun Terdaftar</h3>
  <p class="mut" style="font-size:14px">Daftar gratis atau masuk untuk menikmati seluruh fitur nyaman ini:</p>
  <ul class="tier-list" style="margin-bottom:14px">
    <li>✅ Simpan lagu ke <b>Favorit</b> &amp; buat <b>Daftar Putar</b> sendiri</li>
    <li>✅ <b>Pengatur Suara Halus &amp; Jernih</b>, Kecepatan Putar &amp; Timer Tidur</li>
    <li>✅ <b>Simpan Lagu Tanpa Internet</b> agar tetap bisa didengar saat offline</li>
    <li>✅ <b>Rekomendasi Lagu Pribadi</b> &amp; Riwayat Putar</li>
  </ul>
  <div class="actions">
    <a class="btn" href="{{ route('register') }}" data-turbo="false">Daftar Gratis</a>
    <a class="btn ghost" href="{{ route('login') }}" data-turbo="false">Masuk</a>
    <form method="dialog"><button class="btn ghost sm">Tutup</button></form>
  </div>
</dialog>

<dialog id="pwaGuideDialog">
  <h3>📲 Pasang Musikku di Perangkat Anda</h3>
  <p class="mut" style="font-size:14px">Pasang Musikku agar mudah dibuka seperti aplikasi di HP atau laptop:</p>
  <ul class="tier-list" style="margin-bottom:14px">
    <li>💻 <b>Laptop (Chrome / Edge):</b> Klik ikon <b>Install / Pasang</b> di ujung kanan kolom alamat browser.</li>
    <li>🤖 <b>HP Android (Chrome):</b> Ketuk menu titik tiga <b>⋮ → Tambah ke layar utama / Pasang aplikasi</b>.</li>
    <li>🍎 <b>iPhone (Safari):</b> Ketuk tombol <b>Bagikan (⬆️) → Tambah ke Layar Utama</b>.</li>
  </ul>
  <form method="dialog"><button class="btn sm">Mengerti</button></form>
</dialog>

<dialog id="eqStudioDialog" class="eq-dialog">
  <div class="eq-head">
    <div>
      <span class="badge ok">🎛️ PENGATUR SUARA HALUS &amp; JERNIH</span>
      <h3 style="margin:4px 0 2px">Pengatur Suara &amp; Kejernihan Vokal</h3>
      <small id="eqTrackBadge" class="mut">Pilih gaya suara favorit Anda atau biarkan sistem menghaluskan suara secara otomatis.</small>
    </div>
    <form method="dialog"><button class="icon" title="Tutup">✕</button></form>
  </div>

  <canvas id="eqCanvas" class="eq-canvas" width="600" height="130"></canvas>

  <div class="eq-controls-grid">
    <label>Cara Kerja Pengatur Suara
      <select id="eqMode">
        <option value="manual">Atur Sendiri</option>
        <option value="auto_genre">⚡ Otomatis Sesuai Jenis Lagu</option>
        <option value="adaptive">🎯 Haluskan &amp; Jernihkan Otomatis</option>
      </select>
    </label>
    <label>Gaya Suara yang Diinginkan
      <select id="eqDirection">
        <option value="balanced">Seimbang &amp; Alami</option>
        <option value="warm_bass">Bass Hangat &amp; Empuk</option>
        <option value="bright_clear">Vokal Halus &amp; Jernih</option>
        <option value="punchy">Bertenaga &amp; Menghentak</option>
        <option value="focus_study">Nyaman untuk Fokus / Belajar</option>
      </select>
    </label>
    <label>Tingkat Penyesuaian Pintar: <b id="eqIntensityVal">70%</b>
      <input type="range" id="eqIntensity" min="0" max="100" value="70">
    </label>
  </div>

  <div class="eq-macro-grid">
    <label>Kekuatan Bass (<span id="eqMacroBassVal">0.0</span>)
      <input type="range" id="eqMacroBass" min="-6" max="6" step="0.5" value="0">
    </label>
    <label>Kejernihan Vokal (<span id="eqMacroVocalVal">0.0</span>)
      <input type="range" id="eqMacroVocal" min="-6" max="6" step="0.5" value="0">
    </label>
    <label>Kehalusan Nada Tinggi (<span id="eqMacroTrebleVal">0.0</span>)
      <input type="range" id="eqMacroTreble" min="-6" max="6" step="0.5" value="0">
    </label>
  </div>

  <div id="eqSliders" class="eq-sliders"></div>

  <div class="eq-foot">
    <label class="inline" style="font-size:13px;cursor:pointer">
      <input type="checkbox" id="eqAutoGain" checked>
      <span>Haluskan suara &amp; cegah suara pecah saat volume keras</span>
    </label>
    <div class="actions" style="margin:0">
      <button type="button" id="eqResetBtn" class="btn ghost sm">↺ Kembalikan Normal</button>
      <button type="button" id="eqSaveBtn" class="btn sm">💾 Simpan Pengaturan</button>
    </div>
  </div>
</dialog>

<div id="toast"></div>
</body>
</html>
