@extends('layouts.musikku')
@section('title', 'Akun & API')
@section('content')
<h1>🎛️ Akun, Fitur Terbuka & Akses API</h1>
@include('partials.errors')

<div class="panel">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div>
      <span class="badge ok">PERAN: {{ strtoupper($user->role) }}</span>
      <span class="badge info">HASH: {{ strtoupper(config('hashing.driver')) }} (Rounds {{ config('hashing.bcrypt.rounds') }})</span>
      @if($user->password_salt)
        <span class="badge">SALT ID: {{ substr($user->password_salt, 0, 12) }}…</span>
      @endif
    </div>
    <small class="mut">Login terakhir: {{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Baru saja' }} ({{ $user->last_login_ip ?? request()->ip() }})</small>
  </div>
  <h3 style="margin-top:12px">🔓 Fitur yang Terbuka untuk Akun Anda</h3>
  <div class="chips" style="margin-top:8px">
    @foreach($features as $feat)
      <span class="chip sm">✅ {{ str_replace('_', ' ', $feat) }}</span>
    @endforeach
  </div>
  <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div>
      <b>🎛️ Profil Equalizer Aktif:</b>
      <span class="badge info">{{ strtoupper($user->preferences['eq_mode'] ?? 'manual') }}</span>
      <span class="badge">{{ $user->preferences['eq_direction'] ?? 'balanced' }}</span>
      <span class="badge ok">{{ $user->preferences['eq_preset'] ?? 'normal' }}</span>
    </div>
    <button type="button" class="btn ghost sm" onclick="document.getElementById('pEqStudioBtn')?.click()">Buka Studio EQ 10-Band</button>
  </div>
</div>

<div class="two">
  <div class="panel">
    <h3>Profil Saya</h3>
    <form method="POST" action="{{ route('account.update') }}" class="form">@csrf @method('PUT')
      <label>Nama <input name="name" value="{{ old('name', $user->name) }}" required maxlength="80"></label>
      <label>Email <input value="{{ $user->email }}" disabled></label>
      <label>Nomor Telepon <input name="phone" value="{{ old('phone', $user->phone) }}" maxlength="20" placeholder="08xxxxxxxxxx"></label>
      <button class="btn sm">Simpan Profil</button>
    </form>
  </div>

  <div class="panel">
    <h3>Ubah Kata Sandi (Hashing & Salting)</h3>
    <form method="POST" action="{{ route('account.password') }}" class="form">@csrf @method('PUT')
      <label>Kata sandi saat ini <input type="password" name="current_password" required></label>
      <label>Kata sandi baru (min. 8, huruf besar/kecil & angka) <input type="password" name="password" required minlength="8"></label>
      <label>Ulangi kata sandi baru <input type="password" name="password_confirmation" required></label>
      <button class="btn sm">Perbarui Sandi</button>
    </form>
  </div>
</div>

<div class="panel">
  <h3>🔑 Personal API Token (Sanctum Bearer Auth)</h3>
  <p class="mut" style="font-size:13px">Gunakan token ini untuk mengakses endpoint REST API <code class="inline">/api/v1/*</code> sesuai peran <code class="inline">{{ $user->role }}</code>.</p>

  @if(session('new_api_token'))
    <div class="flash">
      <b>Salin Token Baru Anda:</b><br>
      <code class="inline" style="word-break:break-all;display:inline-block;margin-top:6px">{{ session('new_api_token') }}</code>
    </div>
  @endif

  <form method="POST" action="{{ route('account.tokens.create') }}" class="actions" style="max-width:440px">@csrf
    <input name="token_name" placeholder="Nama perangkat / aplikasi (mis. Mobile App)" required maxlength="60">
    <button class="btn sm">＋ Buat Token</button>
  </form>

  <table class="table">
    <tr><th>Nama Token</th><th>Abilities</th><th>Terakhir Dipakai</th><th>Dibuat</th><th></th></tr>
    @forelse($tokens as $t)
      <tr>
        <td><b>{{ $t->name }}</b></td>
        <td><code class="inline">{{ implode(', ', $t->abilities ?? []) }}</code></td>
        <td>{{ $t->last_used_at?->diffForHumans() ?? 'Belum pernah' }}</td>
        <td>{{ $t->created_at->format('d/m/Y H:i') }}</td>
        <td class="act">
          <form method="POST" action="{{ route('account.tokens.revoke', $t->id) }}">@csrf @method('DELETE')
            <button class="btn danger sm">Cabut</button>
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="5" class="mut">Belum ada API token.</td></tr>
    @endforelse
  </table>
</div>

<div class="panel">
  <h3>🕒 Aktivitas Keamanan Akun Terbaru</h3>
  <table class="table">
    <tr><th>Aksi</th><th>Keterangan</th><th>IP</th><th>Waktu</th></tr>
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
@endsection
