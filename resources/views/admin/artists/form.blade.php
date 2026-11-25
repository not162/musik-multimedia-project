@extends('layouts.musikku')
@section('title', $artist->exists ? 'Edit artis' : 'Artis baru')
@section('content')
<h1>{{ $artist->exists ? 'Edit artis' : 'Artis baru' }}</h1>
@include('admin._nav')
@include('partials.errors')
<form class="form" method="POST" enctype="multipart/form-data" action="{{ $artist->exists ? route('admin.artists.update', $artist) : route('admin.artists.store') }}">
  @csrf @if($artist->exists) @method('PUT') @endif
  <label>Nama <input name="name" value="{{ old('name', $artist->name) }}" required></label>
  <label>Bio <textarea name="bio">{{ old('bio', $artist->bio) }}</textarea></label>
  <label>Foto (opsional, maks 2 MB) <input type="file" name="photo" accept="image/*"></label>
  <div><button class="btn">Simpan</button> <a class="btn ghost" href="{{ route('admin.artists.index') }}">Batal</a></div>
</form>
@endsection
