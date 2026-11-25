@extends('layouts.musikku')
@section('title', 'Riwayat')
@section('content')
<h1>🕘 Riwayat putar</h1>
@if($items->count())
  <form method="POST" action="{{ route('history.clear') }}" data-turbo-confirm="Hapus seluruh riwayat?">@csrf @method('DELETE')<button class="btn danger sm">Hapus riwayat</button></form>
  <div class="song-list" id="hist" style="margin-top:12px">
    @foreach($items as $h)@include('partials.song-row', ['song' => $h->song])@endforeach
  </div>
  {{ $items->links('pagination::default') }}
@else
  <p class="mut">Belum ada riwayat.</p>
@endif
@endsection
