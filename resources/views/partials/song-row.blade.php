{{-- Variabel: $song, opsional $n (nomor), $removeUrl (tombol hapus dari playlist) --}}
@php $isFav = auth()->check() && in_array($song->id, auth()->user()->favoriteIds()); @endphp
<div class="song-row" data-id="{{ $song->id }}" data-src="{{ route('songs.stream', $song) }}"
     data-title="{{ $song->title }}" data-artist="{{ $song->artist->name }}"
     data-genre="{{ $song->genre?->name ?? '' }}" data-label="{{ $song->recordLabel?->name ?? '' }}"
     data-ai-eq='@json($song->ai_eq_bands)' data-ai-mood="{{ $song->ai_mood ?? '' }}"
     data-ai-preamp="{{ $song->ai_preamp_db ?? 0 }}" data-ai-pitch="{{ $song->ai_pitch_rate ?? 1 }}"
     data-href="{{ route('songs.show', $song) }}"
     data-cover="{{ $song->art_url }}" data-grad="{{ $song->gradient }}" data-fav="{{ $isFav ? 1 : 0 }}">
  @isset($n)<span class="num">{{ $n }}</span>@endisset
  @include('partials.cover', ['m' => $song])
  <div class="info">
    <a class="title" href="{{ route('songs.show', $song) }}">{{ $song->title }}</a>
    <small>
      <a href="{{ route('artists.show', $song->artist) }}">{{ $song->artist->name }}</a>
      @if($song->recordLabel)
        · <a href="{{ route('labels.show', $song->recordLabel) }}">🏷️ {{ $song->recordLabel->name }}</a>
      @endif
      <span class="js-ai-badge" style="margin-left:4px">@if($song->is_ai_finetuned)<span class="badge ok">✨ AI{{ $song->ai_mood ? ' · '.$song->ai_mood : '' }}</span>@endif</span>
    </small>
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
