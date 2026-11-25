@extends('layouts.musikku')
@section('title', $q !== '' ? "Cari: $q" : 'Cari')
@section('content')
<h1>{{ $q !== '' ? "Hasil untuk “{$q}”" : 'Cari musik' }}</h1>
@if($q === '')
  <p class="mut">Ketik judul lagu, nama artis, atau album di kotak pencarian di atas.</p>
@elseif($songs->isEmpty() && $artists->isEmpty() && $albums->isEmpty())
  <p class="mut">Tidak ada hasil. Coba kata kunci lain.</p>
@else
  @if($songs->count())<h2>Lagu</h2>@include('partials.song-list', ['songs' => $songs, 'id' => 'res'])@endif
  @if($artists->count())<h2>Artis</h2><div class="grid">@foreach($artists as $a)@include('partials.card', ['m' => $a, 'round' => true, 'href' => route('artists.show', $a), 'title' => $a->name, 'sub' => 'Artis'])@endforeach</div>@endif
  @if($albums->count())<h2>Album</h2><div class="grid">@foreach($albums as $a)@include('partials.card', ['m' => $a, 'href' => route('albums.show', $a), 'title' => $a->title, 'sub' => $a->artist->name])@endforeach</div>@endif
@endif
@endsection
