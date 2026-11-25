@extends('layouts.musikku')
@section('title', 'Daftar')
@section('content')
<div class="authbox">
  <span class="badge ok">🔓 GRATIS · BUKA SEMUA FITUR USER</span>
  <h1 style="margin-top:8px">Buat akun Musikku</h1>
  <p class="mut" style="font-size:13px;margin-top:0">Buka Favorit, Playlist Kustom, Audio Equalizer, Mode Offline PWA, Rekomendasi Personal, dan API Token.</p>
  @include('partials.errors')
  <form method="POST" action="{{ route('register') }}" class="form" data-turbo="false">@csrf
    <label>Nama <input name="name" value="{{ old('name') }}" required maxlength="80"></label>
    <label>Email <input type="email" name="email" value="{{ old('email') }}" required maxlength="120"></label>
    <label>Kata sandi (min. 8 karakter) <input type="password" name="password" required minlength="8"></label>
    <label>Ulangi kata sandi <input type="password" name="password_confirmation" required></label>
    <button class="btn">Daftar & Buka Fitur</button>
  </form>
  <p class="mut" style="margin-top:14px">Sudah punya akun? <a href="{{ route('login') }}" data-turbo="false">Masuk</a></p>
</div>
@endsection
