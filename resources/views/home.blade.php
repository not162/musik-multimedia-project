@extends('layouts.musikku')
@section('title', 'Beranda')
@section('content')
<div class="hero">
  <div class="hero-grid">
    <div>
      @auth
        <span class="badge ok">🔓 AKUN TERDAFTAR · {{ strtoupper(auth()->user()->role) }} AKTIF</span>
        <h1>Halo, {{ auth()->user()->name }} 👋</h1>
        <p class="mut" style="margin:0 0 16px">
          Seluruh fitur lanjutan telah aktif: Equalizer 5-Band, Playlist Kustom, Mode Offline PWA, Rekomendasi Personal, serta Sinkronisasi Real-Time.
        </p>
        <div class="actions" style="margin:0">
          <button class="btn js-play-all" data-scope="#trend">▶ Putar Populer</button>
          <a class="btn ghost" href="{{ route('playlists.index') }}">📂 Playlist Saya</a>
          <a class="btn ghost" href="{{ route('account.index') }}">🎛️ Pengaturan & API</a>
        </div>
      @else
        <span class="badge info">✨ PLATFORM MUSIK INTERAKTIF & APLIKASI PWA</span>
        <h1>Pengalaman Mendengarkan Musik Modern, Cepat, & Real-Time</h1>
        <p class="mut" style="margin:0 0 16px">
          Musikku adalah platform pemutar musik interaktif berbasis web dan aplikasi progresif (PWA) yang menghadirkan streaming audio berkecepatan tinggi, pembaruan aktivitas langsung, serta fondasi sistem terstruktur untuk mengelola katalog audio digital secara aman dan andal.
        </p>
        <div class="actions" style="margin:0">
          <button class="btn js-play-all" data-scope="#trend">▶ Putar Sekarang</button>
          <a class="btn ghost" href="{{ route('register') }}" data-turbo="false">🔓 Daftar Gratis</a>
          <button type="button" class="btn ghost js-pwa-install">📲 Pasang Aplikasi</button>
          <a class="btn ghost" href="#cara-kerja">Cara Kerja ↓</a>
        </div>
      @endauth
    </div>
    <div class="hero-stats">
      <div class="hero-stat"><small>Total Lagu</small><b>{{ number_format($platformStats['songs'] ?? 0) }}</b></div>
      <div class="hero-stat"><small>Total Pemutaran</small><b>{{ number_format($platformStats['plays'] ?? 0) }}</b></div>
      <div class="hero-stat"><small>Artis & Album</small><b>{{ ($platformStats['artists'] ?? 0) }} / {{ ($platformStats['albums'] ?? 0) }}</b></div>
      <div class="hero-stat"><small>Pendengar Online</small><b id="onlineListenersCount">{{ auth()->check() ? 1 : 'Publik' }}</b></div>
    </div>
  </div>
</div>

@guest
<section id="cara-kerja">
  <div class="section-head">
    <h2>🧭 Cara Kerja Platform</h2>
    <p class="mut" style="margin:0">Mulai dalam hitungan detik dari browser atau pasang langsung sebagai aplikasi mandiri.</p>
  </div>
  <div class="steps-grid">
    <div class="step-card">
      <span class="step-num">Langkah 01</span>
      <h3>Jelajahi Katalog Publik</h3>
      <p class="mut" style="margin:0;font-size:13.5px">Cari lagu, artis, album, pantau chart populer, dan dengarkan playlist publik komunitas tanpa perlu login.</p>
    </div>
    <div class="step-card">
      <span class="step-num">Langkah 02</span>
      <h3>Buka Fitur Akun Terdaftar</h3>
      <p class="mut" style="margin:0;font-size:13.5px">Daftar akun gratis untuk membuka Favorit, Playlist Kustom, Equalizer 5-Band, Sleep Timer, Simpan Offline, dan API Token.</p>
    </div>
    <div class="step-card">
      <span class="step-num">Langkah 03</span>
      <h3>Gunakan di Semua Perangkat</h3>
      <p class="mut" style="margin:0;font-size:13.5px">Pasang sebagai aplikasi PWA di laptop maupun ponsel agar selalu siap diakses dengan cepat dan hemat kuota.</p>
    </div>
  </div>
</section>
@endguest

{{-- Perbandingan Akses Publik vs Akun Terdaftar --}}
<div class="tier-grid">
  <div class="tier-card">
    <h3>🌐 Akses Publik <span class="badge">Gratis</span></h3>
    <ul class="tier-list">
      <li>✅ Streaming katalog lagu & membaca lirik</li>
      <li>✅ Pencarian instan & tangga lagu (Chart)</li>
      <li>✅ Putar playlist publik yang dibagikan</li>
      <li>✅ Pasang aplikasi PWA ke perangkat</li>
      <li class="mut">🔒 Favorit, Playlist, Equalizer, Offline Cache terkunci</li>
    </ul>
  </div>
  <div class="tier-card unlocked">
    <h3>
      🔓 Fitur Akun Terdaftar
      @auth
        <span class="badge ok">TERBUKA</span>
      @else
        <a href="{{ route('register') }}" class="badge info" data-turbo="false">Daftar untuk Buka →</a>
      @endauth
    </h3>
    @auth
      <ul class="tier-list">
        <li>✅ <b>{{ $userStats['favorites'] ?? 0 }}</b> Lagu Favorit & <b>{{ $userStats['playlists'] ?? 0 }}</b> Playlist Kustom</li>
        <li>✅ <b>{{ $userStats['histories'] ?? 0 }}</b> Riwayat Putar & Rekomendasi Personal</li>
        <li>✅ <b>Audio Equalizer 5-Band</b>, Kecepatan Putar & Sleep Timer</li>
        <li>✅ <b>Simpan Offline (PWA Cache)</b> & <b>Personal API Token</b></li>
      </ul>
    @else
      <ul class="tier-list">
        <li>🔓 Favorit tanpa batas & Playlist pribadi/publik</li>
        <li>🔓 Audio Equalizer 5-Band, Kecepatan Putar & Sleep Timer</li>
        <li>🔓 Simpan lagu untuk diputar saat <b>Offline (PWA)</b></li>
        <li>🔓 Rekomendasi personal, Riwayat & Akses API Token</li>
      </ul>
    @endauth
  </div>
</div>

{{-- Live Realtime Activity via Laravel Reverb / Pusher --}}
<div class="panel">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h3 style="margin:0">⚡ Aktivitas Pemutaran Langsung</h3>
    @auth
      <div id="onlineListenersList" class="chips"></div>
    @else
      <span class="mut" style="font-size:13px">Masuk untuk melihat daftar pendengar yang sedang online secara langsung</span>
    @endauth
  </div>
  <ul id="liveFeed" class="rt-feed">
    @forelse($recentPlays as $rp)
      <li class="rt-item">
        <span class="rt-dot"></span>
        <div>
          <b>{{ $rp->song->title }} — {{ $rp->song->artist?->name }}</b>
          <small>diputar oleh {{ $rp->user?->name ?? 'Pendengar Publik' }} ({{ number_format($rp->song->play_count) }}×)</small>
        </div>
        <span class="mut rt-time">{{ $rp->played_at->format('H:i') }}</span>
      </li>
    @empty
      <li class="mut rt-empty">Belum ada aktivitas pemutaran terbaru. Putar lagu di bawah untuk mencoba pembaruan langsung!</li>
    @endforelse
  </ul>
</div>

@if($recommended->count())
  <h2>✨ Rekomendasi Untuk Kamu <span class="badge ok">Personal</span></h2>
  <div class="song-list" id="rec">@foreach($recommended as $s)@include('partials.song-row', ['song' => $s])@endforeach</div>
@endif

<h2>🔥 Lagi populer <button class="btn sm js-play-all" data-scope="#trend">▶ Putar</button></h2>
<div class="song-list" id="trend">@forelse($trending as $s)@include('partials.song-row', ['song' => $s, 'n' => $loop->iteration])@empty<p class="mut">Belum ada lagu.</p>@endforelse</div>

<h2>🆕 Rilis terbaru <button class="btn sm js-play-all" data-scope="#new">▶ Putar</button></h2>
<div class="song-list" id="new">@foreach($newest as $s)@include('partials.song-row', ['song' => $s])@endforeach</div>

@if($publicPlaylists->count())
  <h2>📻 Playlist Publik Komunitas</h2>
  <div class="grid">
    @foreach($publicPlaylists as $p)
      @include('partials.card', ['m' => $p, 'href' => route('playlists.public', $p), 'title' => $p->name, 'sub' => ($p->user?->name ?? 'Musikku').' · '.$p->songs_count.' lagu'])
    @endforeach
  </div>
@endif

@if($genres->count())
  <h2>Genre</h2>
  <div class="chips">@foreach($genres as $g)<a class="chip" href="{{ route('genres.show', $g) }}">{{ $g->name }} <small>{{ $g->songs_count }}</small></a>@endforeach</div>
@endif

@if($albums->count())
  <h2>Album baru</h2>
  <div class="grid">@foreach($albums as $a)@include('partials.card', ['m' => $a, 'href' => route('albums.show', $a), 'title' => $a->title, 'sub' => $a->artist->name])@endforeach</div>
@endif

@if($artists->count())
  <h2>Artis</h2>
  <div class="grid">@foreach($artists as $a)@include('partials.card', ['m' => $a, 'round' => true, 'href' => route('artists.show', $a), 'title' => $a->name, 'sub' => $a->songs_count.' lagu'])@endforeach</div>
@endif

@guest
<div class="cta-panel">
  <div>
    <h3 style="margin:0 0 4px">Siap Menjelajahi Seluruh Fitur Musikku?</h3>
    <p class="mut" style="margin:0;font-size:14px">Buat akun gratis untuk menyimpan favorit, menyusun playlist, mengaktifkan equalizer, serta mengakses mode offline PWA.</p>
  </div>
  <div class="actions" style="margin:0">
    <a class="btn" href="{{ route('register') }}" data-turbo="false">Daftar Sekarang</a>
    <a class="btn ghost" href="{{ route('songs.index') }}">Jelajahi Katalog</a>
  </div>
</div>
@endguest
@endsection
