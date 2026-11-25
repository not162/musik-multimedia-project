@extends('layouts.musikku')
@section('title', 'Admin')
@section('content')
<h1>⚙️ Panel admin</h1>
@include('admin._nav')
<div class="stats">@foreach($stats as $label => $v)<div class="stat"><small>{{ $label }}</small><b>{{ number_format($v) }}</b></div>@endforeach</div>

<h2>Pemutaran 7 hari terakhir</h2>
<div class="bars">@foreach($days as $d)<div><small>{{ $d['count'] }}</small><i style="height:{{ max(2, $d['count'] / $max * 85) }}%"></i><small>{{ $d['label'] }}</small></div>@endforeach</div>

<h2>Lagu terpopuler</h2>
<table class="table">
  <tr><th>#</th><th>Judul</th><th>Artis</th><th>Diputar</th></tr>
  @foreach($top as $s)<tr><td>{{ $loop->iteration }}</td><td>{{ $s->title }}</td><td>{{ $s->artist->name }}</td><td>{{ number_format($s->play_count) }}</td></tr>@endforeach
</table>
<div class="actions"><a class="btn" href="{{ route('admin.songs.create') }}">＋ Unggah lagu</a></div>
@endsection
