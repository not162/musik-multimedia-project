@extends('layouts.musikku')
@section('title', 'Playlist')
@section('content')
<h1>📂 Playlist saya</h1>
@include('partials.errors')
<form method="POST" action="{{ route('playlists.store') }}" class="actions" style="max-width:480px">@csrf
  <input name="name" placeholder="Nama playlist baru" required maxlength="100"><button class="btn">Buat</button>
</form>
@if($playlists->count())
  <div class="grid" style="margin-top:16px">
    @foreach($playlists as $p)@include('partials.card', ['m' => $p, 'href' => route('playlists.show', $p), 'title' => $p->name, 'sub' => $p->songs_count.' lagu'.($p->is_public ? ' · publik' : '')])@endforeach
  </div>
@else<p class="mut">Belum ada playlist.</p>@endif
@endsection
