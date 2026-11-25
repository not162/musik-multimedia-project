@extends('layouts.musikku')
@section('title', 'Kelola lagu')
@section('content')
<h1>Kelola lagu</h1>
@include('admin._nav')
<form method="GET" class="actions" style="max-width:420px"><input name="q" value="{{ request('q') }}" placeholder="Cari lagu/artis…"><button class="btn ghost">Cari</button></form>
<div class="actions"><a class="btn" href="{{ route('admin.songs.create') }}">＋ Unggah lagu</a></div>
<table class="table">
  <tr><th>Lagu</th><th>Genre</th><th>Durasi</th><th>Diputar</th><th></th></tr>
  @forelse($songs as $s)
  <tr>
    <td><div class="cell">@include('partials.cover', ['m' => $s])<div><b>{{ $s->title }}</b><br><small>{{ $s->artist->name }}</small></div></div></td>
    <td>{{ $s->genre?->name ?? '—' }}</td><td>{{ $s->duration_label }}</td><td>{{ number_format($s->play_count) }}</td>
    <td class="act">
      <a class="btn ghost sm" href="{{ route('admin.songs.edit', $s) }}">Edit</a>
      <form method="POST" action="{{ route('admin.songs.destroy', $s) }}" data-turbo-confirm="Hapus lagu ini beserta filenya?">@csrf @method('DELETE')<button class="btn danger sm">Hapus</button></form>
    </td>
  </tr>
  @empty<tr><td colspan="5" class="mut">Belum ada lagu.</td></tr>@endforelse
</table>
{{ $songs->links('pagination::default') }}
@endsection
