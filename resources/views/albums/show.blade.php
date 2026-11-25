@extends('layouts.musikku')
@section('title', $album->title)
@section('content')
<div class="head">
  @include('partials.cover', ['m' => $album, 'class' => 'lg'])
  <div><div class="tag">Album</div><h1>{{ $album->title }}</h1>
    <a href="{{ route('artists.show', $album->artist) }}"><b>{{ $album->artist->name }}</b></a>
    <small> · {{ $album->release_year ?: '—' }} · {{ $songs->count() }} lagu</small></div>
</div>
@include('partials.song-list', ['songs' => $songs, 'id' => 'alb', 'numbered' => true])
@endsection
