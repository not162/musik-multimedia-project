{{-- Variabel: $songs, $id (id elemen), opsional $numbered --}}
@if($songs->count())
  <div class="actions">
    <button class="btn js-play-all" data-scope="#{{ $id }}">▶ Putar</button>
    <button class="btn ghost js-shuffle-all" data-scope="#{{ $id }}">🔀 Acak</button>
  </div>
  <div class="song-list" id="{{ $id }}">
    @foreach($songs as $s)@include('partials.song-row', ['song' => $s, 'n' => ($numbered ?? false) ? $loop->iteration : null])@endforeach
  </div>
@else
  <p class="mut">Belum ada lagu.</p>
@endif
