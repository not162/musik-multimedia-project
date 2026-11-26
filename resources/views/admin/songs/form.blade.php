@extends('layouts.musikku')
@section('title', $song->exists ? 'Edit lagu' : 'Unggah lagu')
@section('content')
<h1>{{ $song->exists ? 'Edit lagu' : 'Unggah lagu' }}</h1>
@include('admin._nav')
@include('partials.errors')
@if($artists->isEmpty())<p class="mut">Tambahkan <a href="{{ route('admin.artists.create') }}">artis</a> terlebih dahulu.</p>@endif
<form class="form" method="POST" enctype="multipart/form-data"
      action="{{ $song->exists ? route('admin.songs.update', $song) : route('admin.songs.store') }}">
  @csrf @if($song->exists) @method('PUT') @endif
  <label>Judul <input name="title" value="{{ old('title', $song->title) }}" required></label>
  <div class="row2">
    <label>Artis <select name="artist_id" required>@foreach($artists as $a)<option value="{{ $a->id }}" @selected(old('artist_id', $song->artist_id) == $a->id)>{{ $a->name }}</option>@endforeach</select></label>
    <label>Genre <select name="genre_id"><option value="">—</option>@foreach($genres as $g)<option value="{{ $g->id }}" @selected(old('genre_id', $song->genre_id) == $g->id)>{{ $g->name }}</option>@endforeach</select></label>
  </div>
  <div class="row2">
    <label>Album <select name="album_id"><option value="">— (single)</option>@foreach($albums as $al)<option value="{{ $al->id }}" @selected(old('album_id', $song->album_id) == $al->id)>{{ $al->title }} — {{ $al->artist->name }}</option>@endforeach</select></label>
    <label>Label Musik <select name="record_label_id"><option value="">— (independen)</option>@foreach($labels as $lb)<option value="{{ $lb->id }}" @selected(old('record_label_id', $song->record_label_id) == $lb->id)>{{ $lb->name }}</option>@endforeach</select></label>
  </div>
  <label>Durasi (detik, terisi otomatis) <input type="number" id="duration" name="duration" min="0" value="{{ old('duration', $song->duration) }}"></label>
  <label>File audio (mp3/wav/ogg/m4a/aac/flac, maks 30 MB){{ $song->exists ? ' — kosongkan bila tidak diganti' : '' }}
    <input type="file" id="audioFile" name="audio" accept="audio/*" {{ $song->exists ? '' : 'required' }}></label>
  <label>Cover (opsional, maks 2 MB) <input type="file" name="cover" accept="image/*"></label>
  <label>Lirik (opsional) <textarea name="lyrics">{{ old('lyrics', $song->lyrics) }}</textarea></label>
  <div><button class="btn">Simpan</button> <a class="btn ghost" href="{{ route('admin.songs.index') }}">Batal</a></div>
</form>
<script>
  // Baca durasi otomatis dari file audio di browser
  document.getElementById('audioFile').addEventListener('change', function () {
    const f = this.files[0]; if (!f) return;
    const a = new Audio(); a.preload = 'metadata'; a.src = URL.createObjectURL(f);
    a.addEventListener('loadedmetadata', () => { document.getElementById('duration').value = Math.round(a.duration); URL.revokeObjectURL(a.src); });
  });
</script>
@endsection
