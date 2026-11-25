@extends('layouts.musikku')
@section('title', 'Mode Offline')
@section('content')
<div class="hero">
  <span class="badge warn">○ OFFLINE MODE · PWA</span>
  <h1>Anda sedang offline</h1>
  <p class="mut" style="margin:0">Koneksi internet tidak terdeteksi. Lagu yang sudah Anda simpan ke penyimpanan offline PWA tetap dapat diputar di bawah ini.</p>
</div>

<div class="panel">
  <h3>📥 Lagu Tersimpan Offline</h3>
  <div id="offlineSongList" class="song-list" style="margin-top:10px">
    <p class="mut">Memuat daftar lagu offline…</p>
  </div>
</div>

<div class="actions">
  <button type="button" class="btn" onclick="location.reload()">↻ Coba Hubungkan Lagi</button>
  <a class="btn ghost" href="{{ route('home') }}">← Kembali ke Beranda</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
  const box = document.getElementById('offlineSongList');
  if (!box || !window.musikkuOffline) return;
  const items = window.musikkuOffline.getMetaList();
  const cached = new Set(await window.musikkuOffline.listUrls());
  const available = items.filter(i => cached.has(i.src));
  if (!available.length) {
    box.innerHTML = '<p class="mut">Belum ada lagu yang disimpan offline. Saat online, tekan tombol 📥 pada lagu mana pun (khusus akun terdaftar).</p>';
    return;
  }
  box.innerHTML = '';
  available.forEach((s, idx) => {
    const div = document.createElement('div');
    div.className = 'song-row';
    div.dataset.id = s.id;
    div.dataset.src = s.src;
    div.dataset.title = s.title;
    div.dataset.artist = s.artist;
    div.dataset.href = s.href || '#';
    div.dataset.grad = 'linear-gradient(135deg,#1ed760,#145a2c)';
    div.innerHTML = `<span class="num">${idx + 1}</span><div class="info"><b class="title"></b><small></small></div><button type="button" class="btn sm track-play js-play-song">▶ Putar</button>`;
    div.querySelector('.title').textContent = s.title;
    div.querySelector('small').textContent = s.artist;
    box.appendChild(div);
  });
});
</script>
@endsection
