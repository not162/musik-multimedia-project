@extends('layouts.musikku')
@section('title', 'Pengaturan Web & Cadangan Data')
@section('content')
<h1>🖥️ Pengaturan Web, Cadangan Data &amp; Pencarian Google</h1>
@include('admin._nav')

<div class="stats">
  <div class="stat"><small>Penyimpanan Data Utama</small><b style="font-size:18px">{{ $health['db_ok'] ? '✓ Tersambung (' . $health['db_version'] . ')' : '✗ Terputus' }}</b></div>
  <div class="stat"><small>Nama Server</small><b style="font-size:18px">{{ $health['node_id'] }}</b></div>
  <div class="stat"><small>Siaran Langsung</small><b style="font-size:18px">{{ strtoupper($health['broadcast']) }}</b></div>
  <div class="stat"><small>Memori Cepat</small><b style="font-size:18px">{{ strtoupper($health['cache_driver']) }}</b></div>
  <div class="stat"><small>Ruang Penyimpanan Tersisa</small><b style="font-size:18px">{{ $health['free_disk_gb'] }} GB</b></div>
</div>

<div class="two">
  <div class="panel">
    <h3>⚙️ Kondisi Sistem Saat Ini</h3>
    <table class="table">
      <tr><th>Bagian</th><th>Keterangan</th></tr>
      <tr><td>Server Utama Data</td><td><code class="inline">{{ $health['db_host'] }}</code></td></tr>
      <tr><td>Server Pendamping Baca</td><td><code class="inline">{{ $health['db_read_host'] }}</code></td></tr>
      <tr><td>Pengaman Kata Sandi</td><td><code class="inline">{{ strtoupper($health['hash_driver']) }}</code></td></tr>
      <tr><td>Jalur Pengirim Gambar/Audio</td><td><code class="inline">{{ $health['cdn_url'] }}</code> ({{ $health['cdn_enabled'] ? 'Aktif' : 'Standar' }})</td></tr>
      <tr><td>Versi Sistem</td><td>PHP {{ $health['php_version'] }} / Laravel {{ $health['laravel'] }}</td></tr>
      <tr><td>Peta Situs &amp; Cek Kondisi</td><td><a href="{{ route('sitemap') }}" target="_blank">/sitemap.xml</a> · <a href="{{ route('health') }}" target="_blank">/health</a></td></tr>
    </table>
    <div class="actions">
      <form method="POST" action="{{ route('admin.system.cacheClear') }}">@csrf
        <button class="btn ghost sm">🧹 Segarkan Tampilan &amp; Cache</button>
      </form>
    </div>
  </div>

  <div class="panel">
    <h3>💾 Cadangan Data (Backup)</h3>
    <form method="POST" action="{{ route('admin.system.backup') }}" class="actions">@csrf
      <button class="btn sm">＋ Simpan Cadangan Data Sekarang</button>
    </form>
    <table class="table">
      <tr><th>Nama Berkas</th><th>Ukuran</th><th>Status</th><th></th></tr>
      @forelse($backups as $b)
        <tr>
          <td><small>{{ $b->filename }}</small></td>
          <td>{{ $b->size_human }}</td>
          <td><span class="badge {{ $b->status === 'completed' ? 'ok' : 'danger' }}">{{ $b->status === 'completed' ? 'Selesai' : 'Gagal' }}</span></td>
          <td class="act">
            @if($b->status === 'completed')
              <a class="btn ghost sm" href="{{ route('admin.system.backupDownload', $b) }}" data-turbo="false">Unduh</a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="mut">Belum ada cadangan data tersimpan.</td></tr>
      @endforelse
    </table>
  </div>
</div>

<div class="panel">
  <h3>🔎 Judul &amp; Ringkasan di Mesin Pencari (Google)</h3>
  @include('partials.errors')
  <form method="POST" action="{{ route('admin.system.seoSave') }}" class="actions" style="align-items:flex-end;flex-wrap:wrap">@csrf
    <label style="flex:1;min-width:140px">Kode Halaman <input name="route_name" placeholder="home / songs.index / charts" required></label>
    <label style="flex:2;min-width:200px">Judul di Pencarian <input name="title" placeholder="Judul halaman yang tampil di Google"></label>
    <label style="flex:2;min-width:220px">Ringkasan Halaman <input name="description" placeholder="Penjelasan singkat tentang halaman ini"></label>
    <button class="btn sm">Simpan</button>
  </form>

  <table class="table">
    <tr><th>Halaman</th><th>Judul</th><th>Ringkasan</th><th></th></tr>
    @foreach($seos as $seo)
      <tr>
        <td><code class="inline">{{ $seo->route_name }}</code></td>
        <td>{{ $seo->title }}</td>
        <td><small>{{ $seo->description }}</small></td>
        <td class="act">
          <form method="POST" action="{{ route('admin.system.seoDelete', $seo) }}">@csrf @method('DELETE')<button class="btn danger sm">Hapus</button></form>
        </td>
      </tr>
    @endforeach
  </table>
</div>
@endsection
