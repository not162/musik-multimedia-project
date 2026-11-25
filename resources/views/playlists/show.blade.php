@extends('layouts.musikku')
@section('title', $playlist->name)
@section('content')
<div class="head">
  @include('partials.cover', ['m' => $playlist, 'class' => 'lg'])
  <div><div class="tag">Playlist{{ $playlist->is_public ? ' publik' : '' }}</div><h1>{{ $playlist->name }}</h1>
    <small>{{ $owner ? 'Milikmu' : 'oleh '.$playlist->user->name }} · {{ $songs->count() }} lagu</small></div>
</div>

@if($owner)
  @include('partials.errors')
  <details style="margin-bottom:12px"><summary class="btn ghost sm" style="display:inline-flex">✎ Pengaturan & bagikan</summary>
    <form method="POST" action="{{ route('playlists.update', $playlist) }}" class="form" style="margin-top:12px">@csrf @method('PUT')
      <label>Nama <input name="name" value="{{ old('name', $playlist->name) }}" required></label>
      <label class="inline"><input type="checkbox" name="is_public" value="1" @checked($playlist->is_public)> Publik (siapa pun dengan link bisa mendengarkan)</label>
      <div><button class="btn sm">Simpan</button></div>
    </form>
    @if($playlist->is_public)<p class="mut">Link: <a href="{{ route('playlists.public', $playlist) }}">{{ route('playlists.public', $playlist) }}</a></p>@endif
    <form method="POST" action="{{ route('playlists.destroy', $playlist) }}" data-turbo-confirm="Hapus playlist ini?">@csrf @method('DELETE')<button class="btn danger sm">Hapus playlist</button></form>
  </details>
@endif

@if($songs->count())
  <div class="actions">
    <button class="btn js-play-all" data-scope="#pl">▶ Putar</button>
    <button class="btn ghost js-shuffle-all" data-scope="#pl">🔀 Acak</button>
  </div>
  <div class="song-list" id="pl">
    @foreach($songs as $s)
      @include('partials.song-row', ['song' => $s, 'n' => $loop->iteration] + ($owner ? ['removeUrl' => route('playlists.songs.remove', [$playlist, $s])] : []))
    @endforeach
  </div>
@else
  <p class="mut">Playlist masih kosong. Tekan tombol ＋ pada lagu mana pun untuk menambahkannya.</p>
@endif
@endsection
