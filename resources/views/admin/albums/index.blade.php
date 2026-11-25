@extends('layouts.musikku')
@section('title', 'Kelola album')
@section('content')
<h1>Kelola album</h1>
@include('admin._nav')
<form method="GET" class="actions" style="max-width:420px"><input name="q" value="{{ request('q') }}" placeholder="Cari album…"><button class="btn ghost">Cari</button></form>
<div class="actions"><a class="btn" href="{{ route('admin.albums.create') }}">＋ Album baru</a></div>
<table class="table">
  <tr><th>Album</th><th>Tahun</th><th>Lagu</th><th></th></tr>
  @forelse($albums as $al)
  <tr>
    <td><div class="cell">@include('partials.cover', ['m' => $al])<div><b>{{ $al->title }}</b><br><small>{{ $al->artist->name }}</small></div></div></td>
    <td>{{ $al->release_year ?: '—' }}</td><td>{{ $al->songs_count }}</td>
    <td class="act"><a class="btn ghost sm" href="{{ route('admin.albums.edit', $al) }}">Edit</a>
      <form method="POST" action="{{ route('admin.albums.destroy', $al) }}" data-turbo-confirm="Hapus album? (lagu tetap ada)">@csrf @method('DELETE')<button class="btn danger sm">Hapus</button></form></td>
  </tr>
  @empty<tr><td colspan="4" class="mut">Belum ada album.</td></tr>@endforelse
</table>
{{ $albums->links('pagination::default') }}
@endsection
