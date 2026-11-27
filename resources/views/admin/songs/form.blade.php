@extends('layouts.musikku')
@section('title', $song->exists ? 'Edit Lagu' : 'Tambah Lagu Baru')
@section('content')
<h1>{{ $song->exists ? 'Edit Lagu & Suara' : 'Tambah Lagu Baru (.mp3 / .mp4)' }}</h1>
@include('admin._nav')
@include('partials.errors')
@if($artists->isEmpty())<p class="mut">Tambahkan <a href="{{ route('admin.artists.create') }}">artis</a> terlebih dahulu.</p>@endif
<form class="form" method="POST" enctype="multipart/form-data"
      action="{{ $song->exists ? route('admin.songs.update', $song) : route('admin.songs.store') }}">
  @csrf @if($song->exists) @method('PUT') @endif
  <label>Judul Lagu <input name="title" value="{{ old('title', $song->title) }}" required></label>
  <div class="row2">
    <label>Penyanyi / Artis <select name="artist_id" required>@foreach($artists as $a)<option value="{{ $a->id }}" @selected(old('artist_id', $song->artist_id) == $a->id)>{{ $a->name }}</option>@endforeach</select></label>
    <label>Jenis Musik <select name="genre_id"><option value="">—</option>@foreach($genres as $g)<option value="{{ $g->id }}" @selected(old('genre_id', $song->genre_id) == $g->id)>{{ $g->name }}</option>@endforeach</select></label>
  </div>
  <div class="row2">
    <label>Album <select name="album_id"><option value="">— (Lagu Tunggal)</option>@foreach($albums as $al)<option value="{{ $al->id }}" @selected(old('album_id', $song->album_id) == $al->id)>{{ $al->title }} — {{ $al->artist->name }}</option>@endforeach</select></label>
    <label>Label Musik <select name="record_label_id"><option value="">— (Independen)</option>@foreach($labels as $lb)<option value="{{ $lb->id }}" @selected(old('record_label_id', $song->record_label_id) == $lb->id)>{{ $lb->name }}</option>@endforeach</select></label>
  </div>
  <div class="row2">
    <label>Durasi (detik, terisi otomatis) <input type="number" id="duration" name="duration" min="0" value="{{ old('duration', $song->duration) }}"></label>
    <label>Frekuensi Arsip Master (100 – 1.100 kHz, opsional)
      <input type="number" name="target_sample_rate_khz" min="100" max="1100" step="10"
             value="{{ old('target_sample_rate_khz', $song->target_sample_rate_khz) }}"
             placeholder="Kosongkan = standar 48 kHz (atau isi 100–1100)">
    </label>
  </div>
  <p class="mut" style="font-size:12.5px;margin:-4px 0 6px">
    Catatan: Browser &amp; file .mp3/.mp4 standar memutar paling tinggi 48–96 kHz, jadi jika Anda mengisi <b>100–1.100 kHz</b>, sistem otomatis menyiapkan berkas WAV resolusi tinggi tersebut sekaligus tetap menyediakan versi halus agar lancar diputar pengunjung dan anggota.
  </p>
  <label>File Lagu atau Video Musik (<b>.mp3</b>, <b>.mp4</b>, wav, m4a, flac — maks 100 MB){{ $song->exists ? ' — kosongkan bila tidak diganti' : '' }}
    <input type="file" id="audioFile" name="audio" accept="audio/*,video/mp4,.mp3,.mp4" {{ $song->exists ? '' : 'required' }}></label>
  <label>Gambar Sampul (opsional, maks 4 MB) <input type="file" name="cover" accept="image/*"></label>
  <label>Lirik (opsional) <textarea name="lyrics">{{ old('lyrics', $song->lyrics) }}</textarea></label>
  <label class="inline" style="font-size:13px">
    <input type="checkbox" name="polish_voice" value="1" {{ !$song->exists ? 'checked' : '' }}>
    Haluskan suara, kurangi bising, dan perbarui ke halaman Pengunjung Publik &amp; Pengguna secara otomatis
  </label>
  <div><button class="btn">Simpan &amp; Terbitkan</button> <a class="btn ghost" href="{{ route('admin.songs.index') }}">Batal</a></div>
</form>
<script>
  document.getElementById('audioFile').addEventListener('change', function () {
    const f = this.files[0]; if (!f) return;
    const url = URL.createObjectURL(f);
    const el = f.name.toLowerCase().endsWith('.mp4') ? document.createElement('video') : new Audio();
    el.preload = 'metadata'; el.src = url;
    el.addEventListener('loadedmetadata', () => {
      if (isFinite(el.duration)) document.getElementById('duration').value = Math.round(el.duration);
      URL.revokeObjectURL(url);
    });
  });
</script>
@endsection
