@extends('layouts.musikku')
@section('title', 'Chart')
@section('content')
<h1>📈 Chart</h1>
<div class="two">
  <div><h2>Top minggu ini</h2>@include('partials.song-list', ['songs' => $week, 'id' => 'wk', 'numbered' => true])</div>
  <div><h2>Top sepanjang masa</h2>@include('partials.song-list', ['songs' => $all, 'id' => 'al', 'numbered' => true])</div>
</div>
@endsection
