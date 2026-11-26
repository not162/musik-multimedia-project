@extends('layouts.musikku')
@section('title', $label->name)
@section('content')
<div class="head">
  @include('partials.cover', ['m' => $label, 'class' => 'lg'])
  <div>
    <div class="tag">Label Musik {{ $label->country ? '· '.$label->country : '' }} {{ $label->founded_year ? '· '.$label->founded_year : '' }}</div>
    <h1>{{ $label->name }}</h1>
    @if($label->description)<p class="mut" style="max-width:620px;margin:4px 0 8px">{{ $label->description }}</p>@endif
    <div class="actions" style="margin:0">
      <span class="badge ok">{{ $songs->count() }} Lagu</span>
      <span class="badge info">{{ $albums->count() }} Album</span>
      @if($label->website)<a class="btn ghost sm" href="{{ $label->website }}" target="_blank" rel="noopener">🌐 Situs Resmi</a>@endif
    </div>
  </div>
</div>

<h2>🎵 Rilisan Lagu dari {{ $label->name }}</h2>
@include('partials.song-list', ['songs' => $songs, 'id' => 'lblSongs', 'numbered' => true])

@if($albums->count())
  <h2>💿 Album Label Ini</h2>
  <div class="grid">@foreach($albums as $a)@include('partials.card', ['m' => $a, 'href' => route('albums.show', $a), 'title' => $a->title, 'sub' => $a->artist->name])@endforeach</div>
@endif
@endsection
