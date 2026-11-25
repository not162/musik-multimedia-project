@extends('layouts.musikku')
@section('title', 'Kelola artis')
@section('content')
<h1>Kelola artis</h1>
@include('admin._nav')
<form method="GET" class="actions" style="max-width:420px"><input name="q" value="{{ request('q') }}" placeholder="Cari artis…"><button class="btn ghost">Cari</button></form>
<div class="actions"><a class="btn" href="{{ route('admin.artists.create') }}">＋ Artis baru</a></div>
<table class="table">
  <tr><th>Artis</th><th>Lagu</th><th></th></tr>
  @forelse($artists as $a)
  <tr>
    <td><div class="cell">@include('partials.cover', ['m' => $a, 'class' => 'round'])<b>{{ $a->name }}</b></div></td><td>{{ $a->songs_count }}</td>
    <td class="act"><a class="btn ghost sm" href="{{ route('admin.artists.edit', $a) }}">Edit</a>
      <form method="POST" action="{{ route('admin.artists.destroy', $a) }}" data-turbo-confirm="Hapus artis beserta SEMUA lagu & album-nya?">@csrf @method('DELETE')<button class="btn danger sm">Hapus</button></form></td>
  </tr>
  @empty<tr><td colspan="3" class="mut">Belum ada artis.</td></tr>@endforelse
</table>
{{ $artists->links('pagination::default') }}
@endsection
