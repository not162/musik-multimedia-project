@extends('layouts.musikku')
@section('title', 'Kelola genre')
@section('content')
<h1>Kelola genre</h1>
@include('admin._nav')
@include('partials.errors')
<form method="POST" action="{{ route('admin.genres.store') }}" class="actions" style="max-width:420px">@csrf
  <input name="name" placeholder="Nama genre baru" required maxlength="60"><button class="btn">Tambah</button></form>
<table class="table">
  <tr><th>Genre</th><th>Lagu</th><th></th></tr>
  @foreach($genres as $g)
  <tr><td>{{ $g->name }}</td><td>{{ $g->songs_count }}</td>
    <td class="act"><form method="POST" action="{{ route('admin.genres.destroy', $g) }}" data-turbo-confirm="Hapus genre? (lagu tetap ada)">@csrf @method('DELETE')<button class="btn danger sm">Hapus</button></form></td></tr>
  @endforeach
</table>
@endsection
