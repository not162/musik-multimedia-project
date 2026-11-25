@extends('layouts.musikku')
@section('title', $genre->name)
@section('content')
<h1>Genre: {{ $genre->name }}</h1>
@include('partials.song-list', ['songs' => $songs, 'id' => 'gen'])
{{ $songs->links('pagination::default') }}
@endsection
