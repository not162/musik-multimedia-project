@extends('layouts.musikku')
@section('title', 'Jelajah')
@section('content')
<h1>Jelajah lagu</h1>
<form method="GET" class="actions" style="max-width:700px">
  <input name="q" value="{{ request('q') }}" placeholder="Cari…" style="flex:2">
  <select name="genre" style="flex:1"><option value="">Semua genre</option>
    @foreach($genres as $g)<option value="{{ $g->id }}" @selected(request('genre') == $g->id)>{{ $g->name }}</option>@endforeach</select>
  <select name="sort" style="flex:1">
    <option value="terbaru" @selected($sort === 'terbaru')>Terbaru</option>
    <option value="populer" @selected($sort === 'populer')>Terpopuler</option>
    <option value="judul" @selected($sort === 'judul')>Judul A–Z</option>
  </select>
  <button class="btn">Terapkan</button>
</form>
@include('partials.song-list', ['songs' => $songs, 'id' => 'all'])
{{ $songs->links('pagination::default') }}
@endsection
