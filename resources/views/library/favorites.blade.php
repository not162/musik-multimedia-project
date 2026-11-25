@extends('layouts.musikku')
@section('title', 'Favorit')
@section('content')
<h1>♥ Lagu favorit</h1>
@include('partials.song-list', ['songs' => $songs, 'id' => 'fav'])
@if($songs->isEmpty())<p class="mut">Tekan ♥ pada lagu untuk menyimpannya di sini.</p>@endif
@endsection
