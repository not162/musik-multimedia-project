@extends('layouts.musikku')
@section('title', 'Sistem, Backup, Cache & SEO')
@section('content')
<h1>🖥️ Sistem Enterprise, Cache, Load Balancing, Backup & SEO</h1>
@include('admin._nav')

<div class="stats">
  <div class="stat"><small>Database MySQL</small><b style="font-size:18px">{{ $health['db_ok'] ? '✓ ' . $health['db_version'] : '✗ Offline' }}</b></div>
  <div class="stat"><small>Node ID (Load Balancer)</small><b style="font-size:18px">{{ $health['node_id'] }}</b></div>
  <div class="stat"><small>Broadcast Driver</small><b style="font-size:18px">{{ strtoupper($health['broadcast']) }}</b></div>
  <div class="stat"><small>Cache Store</small><b style="font-size:18px">{{ strtoupper($health['cache_driver']) }}</b></div>
  <div class="stat"><small>Disk Tersedia</small><b style="font-size:18px">{{ $health['free_disk_gb'] }} GB</b></div>
</div>

<div class="two">
  <div class="panel">
    <h3>⚙️ Konfigurasi Infrastruktur (Phase 2–8)</h3>
    <table class="table">
      <tr><th>Komponen</th><th>Status / Nilai</th></tr>
      <tr><td>MySQL Write Host</td><td><code class="inline">{{ $health['db_host'] }}</code></td></tr>
      <tr><td>MySQL Read Replica</td><td><code class="inline">{{ $health['db_read_host'] }}</code></td></tr>
      <tr><td>Hashing & Salting</td><td><code class="inline">{{ $health['hash_driver'] }}</code></td></tr>
      <tr><td>CDN Asset URL</td><td><code class="inline">{{ $health['cdn_url'] }}</code> ({{ $health['cdn_enabled'] ? 'Aktif' : 'Standby' }})</td></tr>
      <tr><td>PHP / Laravel</td><td>{{ $health['php_version'] }} / {{ $health['laravel'] }}</td></tr>
      <tr><td>Sitemap & Health Probe</td><td><a href="{{ route('sitemap') }}" target="_blank">/sitemap.xml</a> · <a href="{{ route('health') }}" target="_blank">/health</a></td></tr>
    </table>
    <div class="actions">
      <form method="POST" action="{{ route('admin.system.cacheClear') }}">@csrf
        <button class="btn ghost sm">🧹 Bersihkan Cache Aplikasi</button>
      </form>
    </div>
  </div>

  <div class="panel">
    <h3>💾 Availability & Recovery (Backup MySQL)</h3>
    <form method="POST" action="{{ route('admin.system.backup') }}" class="actions">@csrf
      <button class="btn sm">＋ Buat Backup Database Sekarang</button>
    </form>
    <table class="table">
      <tr><th>File</th><th>Ukuran</th><th>Status</th><th></th></tr>
      @forelse($backups as $b)
        <tr>
          <td><small>{{ $b->filename }}</small></td>
          <td>{{ $b->size_human }}</td>
          <td><span class="badge {{ $b->status === 'completed' ? 'ok' : 'danger' }}">{{ $b->status }}</span></td>
          <td class="act">
            @if($b->status === 'completed')
              <a class="btn ghost sm" href="{{ route('admin.system.backupDownload', $b) }}" data-turbo="false">Unduh</a>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="mut">Belum ada backup database.</td></tr>
      @endforelse
    </table>
  </div>
</div>

<div class="panel">
  <h3>🔎 Manajemen SEO Metadata (Phase 7)</h3>
  @include('partials.errors')
  <form method="POST" action="{{ route('admin.system.seoSave') }}" class="actions" style="align-items:flex-end;flex-wrap:wrap">@csrf
    <label style="flex:1;min-width:140px">Route Name <input name="route_name" placeholder="home / songs.index / charts" required></label>
    <label style="flex:2;min-width:200px">Meta Title <input name="title" placeholder="Judul SEO halaman"></label>
    <label style="flex:2;min-width:220px">Meta Description <input name="description" placeholder="Deskripsi singkat untuk mesin pencari"></label>
    <button class="btn sm">Simpan SEO</button>
  </form>

  <table class="table">
    <tr><th>Route</th><th>Title</th><th>Description</th><th></th></tr>
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
