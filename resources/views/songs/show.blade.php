@extends('layouts.musikku')
@section('title', $song->title)
@section('content')
<div class="head">
  @include('partials.cover', ['m' => $song, 'class' => 'lg'])
  <div>
    <div class="tag">Lagu</div>
    <h1>{{ $song->title }}</h1>
    <div><a href="{{ route('artists.show', $song->artist) }}"><b>{{ $song->artist->name }}</b></a>
      @if($song->album) · <a href="{{ route('albums.show', $song->album) }}">{{ $song->album->title }}</a>@endif
      @if($song->genre) · <a href="{{ route('genres.show', $song->genre) }}">{{ $song->genre->name }}</a>@endif
      · {{ $song->duration_label }} · {{ number_format($song->play_count) }}× diputar</div>
  </div>
</div>
<div class="song-list" id="one">@include('partials.song-row', ['song' => $song])</div>
<div class="actions"><button class="btn js-play-all" data-scope="#one">▶ Putar</button></div>

<h2>Lirik</h2>
@if($song->lyrics)<div class="lyrics">{{ $song->lyrics }}</div>@else<p class="mut">Lirik belum tersedia.</p>@endif

@if($related->count())
  <h2>Lagu serupa</h2>
  <div class="song-list" id="rel">@foreach($related as $s)@include('partials.song-row', ['song' => $s])@endforeach</div>
@endif
@endsection
