@extends('layouts.musikku')
@section('title', $artist->name)
@section('content')
<div class="head">
  @include('partials.cover', ['m' => $artist, 'class' => 'lg round'])
  <div><div class="tag">Artis</div><h1>{{ $artist->name }}</h1>
    <small>{{ $songs->count() }} lagu · {{ $albums->count() }} album · {{ number_format($songs->sum('play_count')) }}× diputar</small></div>
</div>
@if($artist->bio)<p style="max-width:640px" class="mut">{{ $artist->bio }}</p>@endif
<h2>Lagu populer</h2>
@include('partials.song-list', ['songs' => $songs, 'id' => 'art', 'numbered' => true])
@if($albums->count())
  <h2>Album</h2>
  <div class="grid">@foreach($albums as $a)@include('partials.card', ['m' => $a, 'href' => route('albums.show', $a), 'title' => $a->title, 'sub' => $a->release_year])@endforeach</div>
@endif
@endsection
