@extends('layouts.musikku')
@section('title', 'Beranda')
@section('content')
<div class="hero">
  <div class="hero-grid">
    <div>
      @auth
        <span class="badge ok">🔓 AKUN AKTIF · {{ auth()->user()->isAdmin() ? 'PENGELOLA' : 'ANGGOTA' }}</span>
        <h1>Halo, {{ auth()->user()->name }} 👋</h1>
        <p class="mut" style="margin:0 0 16px">
          Semua fitur nyaman sudah terbuka: Pengatur Suara Halus &amp; Jernih, Daftar Putar Pribadi, Simpan Lagu Tanpa Internet, serta Pembaruan Lagu Langsung.
        </p>
        <div class="actions" style="margin:0">
          <button class="btn js-play-all" data-scope="#trend">▶ Putar Lagu Populer</button>
          <a class="btn ghost" href="{{ route('playlists.index') }}">📂 Daftar Putar Saya</a>
          <a class="btn ghost" href="{{ route('account.index') }}">🎛️ {{ auth()->user()->isAdmin() ? 'Akun & Pengaturan' : 'Profil & Pengaturan Suara' }}</a>
        </div>
      @else
        <span class="badge info">✨ PEMUTAR MUSIK JERNIH &amp; HALUS</span>
        <h1>Dengarkan Musik Lebih Nyaman, Halus, dan Jernih</h1>
        <p class="mut" style="margin:0 0 16px">
          Musikku adalah tempat mendengarkan lagu dengan suara yang telah dirapikan oleh kecerdasan buatan agar lebih halus dan jernih, langsung update otomatis, serta dilengkapi koleksi label musik terbaru.
        </p>
        <div class="actions" style="margin:0">
          <button class="btn js-play-all" data-scope="#trend">▶ Putar Sekarang</button>
          <a class="btn ghost" href="{{ route('register') }}" data-turbo="false">🔓 Daftar Gratis</a>
          <button type="button" class="btn ghost js-pwa-install">📲 Pasang Aplikasi</button>
          <a class="btn ghost" href="#cara-kerja">Cara Pakai ↓</a>
        </div>
      @endauth
    </div>
    <div class="hero-stats">
      <div class="hero-stat"><small>Total Lagu</small><b>{{ number_format($platformStats['songs'] ?? 0) }}</b></div>
      <div class="hero-stat"><small>Total Diputar</small><b>{{ number_format($platformStats['plays'] ?? 0) }}</b></div>
      <div class="hero-stat"><small>Penyanyi &amp; Album</small><b>{{ ($platformStats['artists'] ?? 0) }} / {{ ($platformStats['albums'] ?? 0) }}</b></div>
      <div class="hero-stat"><small>Sedang Mendengarkan</small><b id="onlineListenersCount">{{ auth()->check() ? 1 : 'Publik' }}</b></div>
    </div>
  </div>
</div>

@guest
<section id="cara-kerja">
  <div class="section-head">
    <h2>🧭 Cara Mulai Mendengarkan</h2>
    <p class="mut" style="margin:0">Langsung putar dari browser atau pasang sebagai aplikasi di HP maupun laptop Anda.</p>
  </div>
  <div class="steps-grid">
    <div class="step-card">
      <span class="step-num">Langkah 01</span>
      <h3>Pilih Lagu Sesuka Hati</h3>
      <p class="mut" style="margin:0;font-size:13.5px">Cari lagu, penyanyi, album, pantau daftar terpopuler, dan putar daftar lagu komunitas tanpa harus login.</p>
    </div>
    <div class="step-card">
      <span class="step-num">Langkah 02</span>
      <h3>Daftar Akun Gratis</h3>
      <p class="mut" style="margin:0;font-size:13.5px">Buat akun untuk menyimpan lagu favorit, membuat daftar putar sendiri, mengatur suara vokal &amp; bass, timer tidur, serta menyimpan lagu tanpa kuota.</p>
    </div>
    <div class="step-card">
      <span class="step-num">Langkah 03</span>
      <h3>Nyaman di Semua Perangkat</h3>
      <p class="mut" style="margin:0;font-size:13.5px">Pasang di layar utama HP atau laptop Anda agar mudah dibuka kapan saja dan lebih hemat paket data.</p>
    </div>
  </div>
</section>
@endguest

{{-- Perbandingan Akses Publik vs Akun Terdaftar --}}
<div class="tier-grid">
  <div class="tier-card">
    <h3>🌐 Akses Publik <span class="badge">Gratis</span></h3>
    <ul class="tier-list">
      <li>✅ Putar seluruh koleksi lagu &amp; baca lirik</li>
      <li>✅ Cari lagu cepat &amp; lihat daftar lagu populer</li>
      <li>✅ Dengarkan daftar putar bersama komunitas</li>
      <li>✅ Pasang aplikasi Musikku di HP atau laptop</li>
      <li class="mut">🔒 Fitur Favorit, Daftar Putar, Pengatur Suara, &amp; Simpan Offline khusus pemilik akun</li>
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
        <li>✅ <b>{{ $userStats['favorites'] ?? 0 }}</b> Lagu Favorit &amp; <b>{{ $userStats['playlists'] ?? 0 }}</b> Daftar Putar Pribadi</li>
        <li>✅ <b>{{ $userStats['histories'] ?? 0 }}</b> Riwayat Putar &amp; Rekomendasi Lagu Untuk Anda</li>
        <li>✅ <b>Pengatur Suara Halus &amp; Jernih</b>, Kecepatan Putar &amp; Penghenti Otomatis</li>
        <li>✅ <b>Simpan Lagu Tanpa Internet</b> &amp; Sinkronisasi Setelan di Akun</li>
      </ul>
    @else
      <ul class="tier-list">
        <li>🔓 Simpan Lagu Favorit &amp; buat Daftar Putar pribadi</li>
        <li>🔓 Pengatur Suara Halus &amp; Jernih, Kecepatan Putar &amp; Penghenti Otomatis</li>
        <li>🔓 Simpan lagu agar tetap bisa diputar <b>tanpa internet</b></li>
        <li>🔓 Rekomendasi lagu sesuai selera &amp; Riwayat Putar tersimpan</li>
      </ul>
    @endauth
  </div>
</div>

{{-- Aktivitas Mendengarkan Langsung --}}
<div class="panel">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h3 style="margin:0">⚡ Aktivitas Pemutaran Langsung</h3>
    @auth
      <div id="onlineListenersList" class="chips"></div>
    @else
      <span class="mut" style="font-size:13px">Masuk ke akun untuk melihat teman yang sedang mendengarkan musik</span>
    @endauth
  </div>
  <ul id="liveFeed" class="rt-feed">
    @forelse($recentPlays as $rp)
      <li class="rt-item">
        <span class="rt-dot"></span>
        <div>
          <b>{{ $rp->song->title }} — {{ $rp->song->artist?->name }}</b>
          <small>diputar oleh {{ $rp->user?->name ?? 'Pendengar Tamu' }} ({{ number_format($rp->song->play_count) }}×)</small>
        </div>
        <span class="mut rt-time">{{ $rp->played_at->format('H:i') }}</span>
      </li>
    @empty
      <li class="mut rt-empty">Belum ada yang memutar lagu barusan. Putar lagu di bawah ini!</li>
    @endforelse
  </ul>
</div>

@if($recommended->count())
  <h2>✨ Rekomendasi Untuk Kamu <span class="badge ok">Sesuai Selera</span></h2>
  <div class="song-list" id="rec">@foreach($recommended as $s)@include('partials.song-row', ['song' => $s])@endforeach</div>
@endif

<h2>🔥 Lagi populer <button class="btn sm js-play-all" data-scope="#trend">▶ Putar</button></h2>
<div class="song-list" id="trend">@forelse($trending as $s)@include('partials.song-row', ['song' => $s, 'n' => $loop->iteration])@empty<p class="mut">Belum ada lagu.</p>@endforelse</div>

<h2>🆕 Rilis terbaru <button class="btn sm js-play-all" data-scope="#new">▶ Putar</button></h2>
<div class="song-list" id="new">@foreach($newest as $s)@include('partials.song-row', ['song' => $s])@endforeach</div>

@if($publicPlaylists->count())
  <h2>📻 Daftar Putar Komunitas</h2>
  <div class="grid">
    @foreach($publicPlaylists as $p)
      @include('partials.card', ['m' => $p, 'href' => route('playlists.public', $p), 'title' => $p->name, 'sub' => ($p->user?->name ?? 'Musikku').' · '.$p->songs_count.' lagu'])
    @endforeach
  </div>
@endif

@if(isset($labels) && $labels->count())
  <h2>🏷️ Daftar Label Musik Baru <a class="btn ghost sm" href="{{ route('labels.index') }}">Lihat Semua →</a></h2>
  <div class="grid" id="liveLabelsGrid">
    @foreach($labels as $lb)
      @include('partials.card', ['m' => $lb, 'href' => route('labels.show', $lb), 'title' => $lb->name, 'sub' => ($lb->country ?? 'Label').' · '.$lb->songs_count.' lagu'])
    @endforeach
  </div>
@endif

@if($genres->count())
  <h2>Jenis Musik</h2>
  <div class="chips">@foreach($genres as $g)<a class="chip" href="{{ route('genres.show', $g) }}">{{ $g->name }} <small>{{ $g->songs_count }}</small></a>@endforeach</div>
@endif

@if($albums->count())
  <h2>Album baru</h2>
  <div class="grid">@foreach($albums as $a)@include('partials.card', ['m' => $a, 'href' => route('albums.show', $a), 'title' => $a->title, 'sub' => $a->artist->name])@endforeach</div>
@endif

@if($artists->count())
  <h2>Penyanyi &amp; Musisi</h2>
  <div class="grid">@foreach($artists as $a)@include('partials.card', ['m' => $a, 'round' => true, 'href' => route('artists.show', $a), 'title' => $a->name, 'sub' => $a->songs_count.' lagu'])@endforeach</div>
@endif

@guest
<div class="cta-panel">
  <div>
    <h3 style="margin:0 0 4px">Ingin Menyimpan Lagu Favorit &amp; Mengatur Suara Sendiri?</h3>
    <p class="mut" style="margin:0;font-size:14px">Daftar gratis hanya dalam beberapa detik untuk menikmati semua fitur lengkap Musikku.</p>
  </div>
  <div class="actions" style="margin:0">
    <a class="btn" href="{{ route('register') }}" data-turbo="false">Daftar Sekarang</a>
    <a class="btn ghost" href="{{ route('songs.index') }}">Lihat Semua Lagu</a>
  </div>
</div>
@endguest
@endsection
