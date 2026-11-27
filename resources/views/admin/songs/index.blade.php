@extends('layouts.musikku')
@section('title', 'Kelola Lagu')
@section('content')
<h1>Kelola Lagu (.mp3 &amp; .mp4)</h1>
@include('admin._nav')
<form method="GET" class="actions" style="max-width:420px"><input name="q" value="{{ request('q') }}" placeholder="Cari lagu/artis…"><button class="btn ghost">Cari</button></form>
<div class="actions"><a class="btn" href="{{ route('admin.songs.create') }}">＋ Tambah Lagu (.mp3 / .mp4)</a></div>
<table class="table">
  <tr><th>Lagu</th><th>Format &amp; Frekuensi</th><th>Jenis</th><th>Durasi</th><th>Diputar</th><th></th></tr>
  @forelse($songs as $s)
  <tr>
    <td><div class="cell">@include('partials.cover', ['m' => $s])<div><b>{{ $s->title }}</b><br><small>{{ $s->artist->name }}</small></div></div></td>
    <td>
      <span class="badge">{{ strtoupper($s->media_format ?: pathinfo((string)$s->file_path, PATHINFO_EXTENSION) ?: 'MP3') }}</span>
      @if($s->target_sample_rate_khz)<span class="badge info">{{ $s->target_sample_rate_khz }} kHz</span>@endif
      @if($s->is_voice_smoothed)<span class="badge ok">Halus {{ $s->voice_clarity_score }}/100</span>@endif
    </td>
    <td>{{ $s->genre?->name ?? '—' }}</td><td>{{ $s->duration_label }}</td><td>{{ number_format($s->play_count) }}</td>
    <td class="act">
      <a class="btn ghost sm" href="{{ route('admin.songs.edit', $s) }}">Edit</a>
      <form method="POST" action="{{ route('admin.songs.destroy', $s) }}" data-turbo-confirm="Hapus lagu ini beserta filenya?">@csrf @method('DELETE')<button class="btn danger sm">Hapus</button></form>
    </td>
  </tr>
  @empty<tr><td colspan="6" class="mut">Belum ada lagu.</td></tr>@endforelse
</table>
{{ $songs->links('pagination::default') }}
@endsection
