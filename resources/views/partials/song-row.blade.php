{{-- Variabel: $song, opsional $n (nomor), $removeUrl (tombol hapus dari playlist) --}}
@php $isFav = auth()->check() && in_array($song->id, auth()->user()->favoriteIds()); @endphp
<div class="song-row" data-id="{{ $song->id }}" data-src="{{ route('songs.stream', $song) }}"
     data-title="{{ $song->title }}" data-artist="{{ $song->artist->name }}" data-href="{{ route('songs.show', $song) }}"
     data-cover="{{ $song->art_url }}" data-grad="{{ $song->gradient }}" data-fav="{{ $isFav ? 1 : 0 }}">
  @isset($n)<span class="num">{{ $n }}</span>@endisset
  @include('partials.cover', ['m' => $song])
  <div class="info">
    <a class="title" href="{{ route('songs.show', $song) }}">{{ $song->title }}</a>
    <a href="{{ route('artists.show', $song->artist) }}"><small>{{ $song->artist->name }}</small></a>
  </div>
  <div class="album">@if($song->album)<a href="{{ route('albums.show', $song->album) }}">{{ $song->album->title }}</a>@endif</div>
  <span class="dur">{{ $song->duration_label }}</span>
  <div class="row-actions">
    <button type="button" class="btn sm track-play js-play-song" aria-label="Putar {{ $song->title }}">▶ Putar</button>
    <button class="icon js-fav {{ $isFav ? 'on' : '' }}" data-id="{{ $song->id }}" title="Favorit">♥</button>
    <button class="icon js-add-pl" data-id="{{ $song->id }}" title="Tambah ke playlist">＋</button>
    <button class="icon js-offline-save" data-id="{{ $song->id }}" title="Simpan offline (PWA)">📥</button>
    <button class="icon js-queue" title="Tambah ke antrean">☰</button>
    @isset($removeUrl)
      <form method="POST" action="{{ $removeUrl }}">@csrf @method('DELETE')<button class="icon" title="Hapus dari playlist">✕</button></form>
    @endisset
  </div>
</div>
