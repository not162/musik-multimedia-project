@extends('layouts.musikku')
@section('title', $label->exists ? 'Edit Label Musik' : 'Label Musik Baru')
@section('content')
<h1>{{ $label->exists ? 'Edit Label Musik' : 'Tambah Label Musik Baru' }}</h1>
@include('admin._nav')
@include('partials.errors')
<form class="form" method="POST" enctype="multipart/form-data"
      action="{{ $label->exists ? route('admin.labels.update', $label) : route('admin.labels.store') }}">
  @csrf @if($label->exists) @method('PUT') @endif
  <label>Nama Label Musik <input name="name" value="{{ old('name', $label->name) }}" required maxlength="120"></label>
  <div class="row2">
    <label>Negara <input name="country" value="{{ old('country', $label->country ?? 'Indonesia') }}" maxlength="80"></label>
    <label>Tahun Berdiri <input type="number" name="founded_year" value="{{ old('founded_year', $label->founded_year) }}" min="1900" max="{{ date('Y') + 1 }}"></label>
  </div>
  <label>Website Resmi (opsional) <input type="url" name="website" value="{{ old('website', $label->website) }}" placeholder="https://..."></label>
  <label>Logo Label (opsional, maks 2 MB) <input type="file" name="logo" accept="image/*"></label>
  <label>Deskripsi <textarea name="description">{{ old('description', $label->description) }}</textarea></label>
  <label class="inline"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $label->exists ? $label->is_featured : true))> Tampilkan di Daftar Label Musik Baru (Halaman Publik)</label>
  <div><button class="btn">Simpan &amp; Siarkan Real-Time</button> <a class="btn ghost" href="{{ route('admin.labels.index') }}">Batal</a></div>
</form>
@endsection
