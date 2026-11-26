@extends('layouts.musikku')
@section('title', 'Sedang Tanpa Internet')
@section('content')
<div class="hero">
  <span class="badge warn">○ TIDAK ADA INTERNET</span>
  <h1>Anda sedang tidak terhubung ke internet</h1>
  <p class="mut" style="margin:0">Koneksi internet belum tersambung. Lagu yang pernah Anda simpan ke perangkat tetap dapat diputar di bawah ini.</p>
</div>

<div class="panel">
  <h3>📥 Lagu Tersimpan di Perangkat Anda</h3>
  <div id="offlineSongList" class="song-list" style="margin-top:10px">
    <p class="mut">Memuat daftar lagu tersimpan…</p>
  </div>
</div>

<div class="actions">
  <button type="button" class="btn" onclick="location.reload()">↻ Coba Sambungkan Lagi</button>
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
    box.innerHTML = '<p class="mut">Belum ada lagu yang disimpan. Saat ada internet, tekan tombol 📥 pada lagu mana saja untuk menyimpannya.</p>';
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
