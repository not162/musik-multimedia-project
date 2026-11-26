@extends('layouts.musikku')
@section('title', $album->exists ? 'Edit album' : 'Album baru')
@section('content')
<h1>{{ $album->exists ? 'Edit album' : 'Album baru' }}</h1>
@include('admin._nav')
@include('partials.errors')
<form class="form" method="POST" enctype="multipart/form-data" action="{{ $album->exists ? route('admin.albums.update', $album) : route('admin.albums.store') }}">
  @csrf @if($album->exists) @method('PUT') @endif
  <label>Judul <input name="title" value="{{ old('title', $album->title) }}" required></label>
  <div class="row2">
    <label>Artis <select name="artist_id" required>@foreach($artists as $a)<option value="{{ $a->id }}" @selected(old('artist_id', $album->artist_id) == $a->id)>{{ $a->name }}</option>@endforeach</select></label>
    <label>Label Musik <select name="record_label_id"><option value="">—</option>@foreach($labels ?? [] as $lb)<option value="{{ $lb->id }}" @selected(old('record_label_id', $album->record_label_id) == $lb->id)>{{ $lb->name }}</option>@endforeach</select></label>
  </div>
  <label>Tahun rilis <input type="number" name="release_year" value="{{ old('release_year', $album->release_year) }}" min="1900" max="{{ date('Y') + 1 }}"></label>
  <label>Cover (opsional, maks 2 MB) <input type="file" name="cover" accept="image/*"></label>
  <div><button class="btn">Simpan</button> <a class="btn ghost" href="{{ route('admin.albums.index') }}">Batal</a></div>
</form>
@endsection
