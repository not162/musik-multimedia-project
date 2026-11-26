@extends('layouts.musikku')
@section('title', 'Kelola Label Musik')
@section('content')
<h1>🏷️ Daftar Label Musik</h1>
@include('admin._nav')
<div class="actions"><a class="btn" href="{{ route('admin.labels.create') }}">＋ Label Musik Baru</a></div>
<table class="table">
  <tr><th>Label</th><th>Negara / Tahun</th><th>Lagu</th><th>Album</th><th>Status</th><th></th></tr>
  @forelse($labels as $l)
    <tr>
      <td><div class="cell">@include('partials.cover', ['m' => $l])<div><b>{{ $l->name }}</b><br><small>{{ $l->slug }}</small></div></div></td>
      <td>{{ $l->country ?? '—' }} {{ $l->founded_year ? '· '.$l->founded_year : '' }}</td>
      <td>{{ $l->songs_count }}</td>
      <td>{{ $l->albums_count }}</td>
      <td>{!! $l->is_featured ? '<span class="badge ok">Unggulan Publik</span>' : '<span class="badge">Standar</span>' !!}</td>
      <td class="act">
        <a class="btn ghost sm" href="{{ route('labels.show', $l) }}" target="_blank">Lihat Publik</a>
        <a class="btn ghost sm" href="{{ route('admin.labels.edit', $l) }}">Edit</a>
        <form method="POST" action="{{ route('admin.labels.destroy', $l) }}" data-turbo-confirm="Hapus label musik ini?">@csrf @method('DELETE')
          <button class="btn danger sm">Hapus</button>
        </form>
      </td>
    </tr>
  @empty
    <tr><td colspan="6" class="mut">Belum ada label musik.</td></tr>
  @endforelse
</table>
{{ $labels->links('pagination::default') }}
@endsection
