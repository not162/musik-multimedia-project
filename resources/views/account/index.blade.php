@extends('layouts.musikku')
@section('title', $user->isAdmin() ? 'Akun & Akses Admin' : 'Profil Saya')
@section('content')
<h1>{{ $user->isAdmin() ? '🎛️ Akun Admin, Keamanan & Kunci Akses' : '👤 Profil Saya & Pengaturan Suara' }}</h1>
@include('partials.errors')

@php
  $featureLabels = [
    'favorites'           => 'Lagu Favorit',
    'playlists'           => 'Daftar Putar Pribadi',
    'history'             => 'Riwayat Putar',
    'recommendations'     => 'Rekomendasi Lagu',
    'equalizer'           => 'Pengatur Suara Halus',
    'playback_speed'      => 'Kecepatan Putar',
    'sleep_timer'         => 'Penghenti Otomatis (Timer)',
    'offline_pwa_audio'   => 'Simpan Lagu Tanpa Internet',
    'realtime_presence'   => 'Pembaruan Lagu Langsung',
    'api_tokens'          => 'Kunci Akses Aplikasi (Admin)',
    'api_management'      => 'Pengaturan Sambungan Data',
    'ai_music_finetuning' => 'Pelatih Suara AI',
    'record_labels_crud'  => 'Kelola Label Musik',
    'admin_catalog_crud'  => 'Kelola Lagu & Artis',
    'admin_users'         => 'Kelola Pengguna',
    'admin_error_alerts'  => 'Catatan Masalah Sistem',
    'admin_lrs_audit'     => 'Catatan Keamanan Akun',
    'admin_backups'       => 'Cadangan Data',
    'admin_seo'           => 'Judul Pencarian Web',
    'admin_health'        => 'Pantauan Server',
  ];
  $modeLabels = [
    'manual'     => 'Atur Sendiri',
    'auto_genre' => 'Otomatis Sesuai Jenis Lagu',
    'adaptive'   => 'Halus & Jernih Otomatis',
  ];
  $dirLabels = [
    'balanced'     => 'Seimbang & Alami',
    'warm_bass'    => 'Bass Hangat & Empuk',
    'bright_clear' => 'Vokal Halus & Jernih',
    'punchy'       => 'Bertenaga & Menghentak',
    'focus_study'  => 'Nyaman untuk Fokus / Belajar',
  ];
  $eqModeNow = $user->preferences['eq_mode'] ?? 'manual';
  $eqDirNow  = $user->preferences['eq_direction'] ?? 'balanced';
@endphp

<div class="panel">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div>
      <span class="badge ok">STATUS AKUN: {{ $user->isAdmin() ? 'ADMIN' : 'ANGGOTA' }}</span>
      @if($user->isAdmin())
        <span class="badge info">HASH: {{ strtoupper(config('hashing.driver')) }} (Rounds {{ config('hashing.bcrypt.rounds') }})</span>
        @if($user->password_salt)
          <span class="badge">SALT ID: {{ substr($user->password_salt, 0, 12) }}…</span>
        @endif
      @endif
    </div>
    <small class="mut">Terakhir masuk: {{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Baru saja' }}</small>
  </div>
  <h3 style="margin-top:12px">🔓 Fasilitas Akun Anda</h3>
  <div class="chips" style="margin-top:8px">
    @foreach($features as $feat)
      <span class="chip sm">✅ {{ $featureLabels[$feat] ?? ucwords(str_replace('_', ' ', $feat)) }}</span>
    @endforeach
  </div>
  <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div>
      <b>🎛️ Pengaturan Suara Aktif:</b>
      <span class="badge info">{{ $modeLabels[$eqModeNow] ?? 'Atur Sendiri' }}</span>
      <span class="badge ok">{{ $dirLabels[$eqDirNow] ?? 'Seimbang & Alami' }}</span>
    </div>
    <button type="button" class="btn ghost sm" onclick="document.getElementById('pEqStudioBtn')?.click()">🎛️ Buka Pengatur Suara</button>
  </div>
</div>

<div class="two">
  <div class="panel">
    <h3>Profil Saya</h3>
    <form method="POST" action="{{ route('account.update') }}" class="form">@csrf @method('PUT')
      <label>Nama Lengkap <input name="name" value="{{ old('name', $user->name) }}" required maxlength="80"></label>
      <label>Alamat Email <input value="{{ $user->email }}" disabled></label>
      <label>Nomor Telepon <input name="phone" value="{{ old('phone', $user->phone) }}" maxlength="20" placeholder="08xxxxxxxxxx"></label>
      <button class="btn sm">Simpan Profil</button>
    </form>
  </div>

  <div class="panel">
    <h3>Ubah Kata Sandi</h3>
    <form method="POST" action="{{ route('account.password') }}" class="form">@csrf @method('PUT')
      <label>Kata sandi saat ini <input type="password" name="current_password" required></label>
      <label>Kata sandi baru (minimal 8 karakter, pakai huruf besar, huruf kecil &amp; angka) <input type="password" name="password" required minlength="8"></label>
      <label>Ketik ulang kata sandi baru <input type="password" name="password_confirmation" required></label>
      <button class="btn sm">Simpan Kata Sandi Baru</button>
    </form>
  </div>
</div>

@if($user->isAdmin())
<div class="panel">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div>
      <h3 style="margin:0">🔑 Personal API Token (Khusus Admin)</h3>
      <p class="mut" style="font-size:13px;margin:4px 0 0">Kunci rahasia untuk menghubungkan aplikasi luar dengan Musikku, lalu kelola AI di <a href="{{ route('admin.ai.index') }}"><b>Studio AI &amp; Pelatih Suara</b></a>.</p>
    </div>
    <a class="btn ghost sm" href="{{ route('admin.ai.index') }}">🤖 Buka Studio AI &amp; Suara</a>
  </div>

  @if(session('new_api_token'))
    <div class="flash" style="margin-top:12px">
      <b>Salin Kunci Baru Anda (hanya tampil sekali ini):</b><br>
      <code class="inline" style="word-break:break-all;display:inline-block;margin-top:6px">{{ session('new_api_token') }}</code>
    </div>
  @endif

  <form method="POST" action="{{ route('account.tokens.create') }}" class="actions" style="max-width:440px;margin-top:12px">@csrf
    <input name="token_name" placeholder="Nama aplikasi atau perangkat (mis. Panel Admin)" required maxlength="60">
    <button class="btn sm">＋ Buat Kunci Akses</button>
  </form>

  <table class="table">
    <tr><th>Nama Kunci</th><th>Izin</th><th>Terakhir Dipakai</th><th>Dibuat</th><th></th></tr>
    @forelse($tokens as $t)
      <tr>
        <td><b>{{ $t->name }}</b></td>
        <td><code class="inline">{{ implode(', ', $t->abilities ?? []) }}</code></td>
        <td>{{ $t->last_used_at?->diffForHumans() ?? 'Belum pernah' }}</td>
        <td>{{ $t->created_at->format('d/m/Y H:i') }}</td>
        <td class="act">
          <form method="POST" action="{{ route('account.tokens.revoke', $t->id) }}">@csrf @method('DELETE')
            <button class="btn danger sm">Hapus</button>
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="5" class="mut">Belum ada kunci akses tersimpan.</td></tr>
    @endforelse
  </table>
</div>

<div class="panel">
  <h3>🕒 Riwayat Masuk &amp; Aktivitas Akun Terbaru</h3>
  <table class="table">
    <tr><th>Kegiatan</th><th>Keterangan</th><th>Alamat Jaringan</th><th>Waktu</th></tr>
    @forelse($recentAudits as $a)
      <tr>
        <td><span class="badge">{{ $a->action }}</span></td>
        <td>{{ $a->description ?? $a->resource_type }}</td>
        <td>{{ $a->ip_address }}</td>
        <td>{{ $a->created_at?->format('d/m/Y H:i:s') }}</td>
      </tr>
    @empty
      <tr><td colspan="4" class="mut">Belum ada aktivitas tercatat.</td></tr>
    @endforelse
  </table>
</div>
@endif
@endsection
