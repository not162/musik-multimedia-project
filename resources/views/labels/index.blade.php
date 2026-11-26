@extends('layouts.musikku')
@section('title', 'Daftar Label Musik Baru')
@section('content')
<div class="hero">
  <span class="badge info">🏷️ KATALOG LABEL REKAMAN</span>
  <h1>Daftar Label Musik</h1>
  <p class="mut" style="margin:0">Jelajahi label musik independen dan studio produksi yang terhubung secara langsung dengan rilisan lagu di Musikku.</p>
</div>

@if($labels->count())
  <div class="grid">
    @foreach($labels as $lb)
      @include('partials.card', [
        'm'     => $lb,
        'href'  => route('labels.show', $lb),
        'title' => $lb->name,
        'sub'   => ($lb->country ?? 'Global') . ' · ' . $lb->songs_count . ' lagu'
      ])
    @endforeach
  </div>
  {{ $labels->links('pagination::default') }}
@else
  <p class="mut">Belum ada label musik terdaftar.</p>
@endif
@endsection
