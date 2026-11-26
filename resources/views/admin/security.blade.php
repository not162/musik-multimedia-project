@extends('layouts.musikku')
@section('title', 'Keamanan & Catatan Aktivitas')
@section('content')
<h1>🛡️ Keamanan Akun &amp; Catatan Aktivitas</h1>
@include('admin._nav')

<div class="stats">
  <div class="stat"><small>Catatan Aktivitas Terjaga</small><b>{{ number_format($lrsStats['total_statements']) }}</b></div>
  <div class="stat"><small>Riwayat Perubahan Penting</small><b>{{ number_format($lrsStats['total_audits']) }}</b></div>
  <div class="stat"><small>Akun Terkunci / Nonaktif</small><b>{{ $lrsStats['locked_accounts'] }}</b></div>
  <div class="stat"><small>Ukuran Berkas Catatan</small><b>{{ $lrsStats['jsonl_size'] }}</b></div>
</div>

@if($lockedUsers->count())
<div class="panel">
  <h3>🔒 Akun Terkunci / Nonaktif</h3>
  <table class="table">
    <tr><th>Nama</th><th>Email</th><th>Salah Sandi</th><th>Terkunci Sampai</th><th>Tindakan</th></tr>
    @foreach($lockedUsers as $lu)
      <tr>
        <td>{{ $lu->name }}</td>
        <td>{{ $lu->email }}</td>
        <td>{{ $lu->failed_login_attempts }}×</td>
        <td>{{ $lu->locked_until?->format('d/m H:i') ?? 'Dinonaktifkan' }}</td>
        <td class="act">
          <form method="POST" action="{{ route('admin.users.unlock', $lu) }}">@csrf @method('PATCH')<button class="btn sm">Buka Kunci</button></form>
          @if(!$lu->is_active)
            <form method="POST" action="{{ route('admin.users.status', $lu) }}">@csrf @method('PATCH')<button class="btn ghost sm">Aktifkan</button></form>
          @endif
        </td>
      </tr>
    @endforeach
  </table>
</div>
@endif

<div class="panel">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <h3 style="margin:0">📚 Catatan Aktivitas Sistem (Anti-Ubah)</h3>
    <a class="btn sm" href="{{ route('admin.security.lrsExport') }}" data-turbo="false">⬇ Unduh Catatan</a>
  </div>
  <table class="table" style="margin-top:10px">
    <tr><th>Pelaku</th><th>Kegiatan</th><th>Objek</th><th>Keaslian Data</th><th>Alamat Jaringan</th><th>Waktu</th></tr>
    @forelse($lrsStatements as $st)
      <tr>
        <td>{{ $st->actor_name }}</td>
        <td><span class="badge info">{{ $st->verb }}</span></td>
        <td>{{ $st->object_type }}: <b>{{ $st->object_name ?? ('#'.$st->object_id) }}</b></td>
        <td>{!! $st->verifySignature() ? '<span class="badge ok">✓ Asli</span>' : '<span class="badge danger">✕ Berubah</span>' !!}</td>
        <td><small>{{ $st->ip_address }}</small></td>
        <td><small>{{ $st->stored_at?->format('d/m H:i:s') }}</small></td>
      </tr>
    @empty
      <tr><td colspan="6" class="mut">Belum ada aktivitas tercatat.</td></tr>
    @endforelse
  </table>
  {{ $lrsStatements->links('pagination::default') }}
</div>

<div class="panel">
  <h3>🔍 Riwayat Perubahan Oleh Pengguna &amp; Admin</h3>
  <table class="table">
    <tr><th>Pengguna</th><th>Aksi</th><th>Bagian</th><th>Keterangan</th><th>Alamat Jaringan</th><th>Waktu</th></tr>
    @forelse($audits as $au)
      <tr>
        <td>{{ $au->user?->name ?? 'Sistem / Tamu' }}</td>
        <td><span class="badge">{{ $au->action }}</span></td>
        <td>{{ $au->resource_type }} {{ $au->resource_id ? '#'.$au->resource_id : '' }}</td>
        <td>{{ $au->description }}</td>
        <td><small>{{ $au->ip_address }}</small></td>
        <td><small>{{ $au->created_at?->format('d/m H:i:s') }}</small></td>
      </tr>
    @empty
      <tr><td colspan="6" class="mut">Belum ada riwayat perubahan.</td></tr>
    @endforelse
  </table>
  {{ $audits->links('pagination::default') }}
</div>
@endsection
