@extends('layouts.musikku')
@section('title', 'Daftar')
@section('content')
<div class="authbox">
  <span class="badge ok">🔓 GRATIS · BUKA SEMUA FITUR ANGGOTA</span>
  <h1 style="margin-top:8px">Buat Akun Musikku</h1>
  <p class="mut" style="font-size:13px;margin-top:0">Nikmati fitur Lagu Favorit, Daftar Putar Pribadi, Pengatur Suara Halus &amp; Jernih, Simpan Lagu Tanpa Internet, dan Rekomendasi Musik.</p>
  @include('partials.errors')
  <form method="POST" action="{{ route('register') }}" class="form" data-turbo="false">@csrf
    <label>Nama Lengkap <input name="name" value="{{ old('name') }}" required maxlength="80"></label>
    <label>Alamat Email <input type="email" name="email" value="{{ old('email') }}" required maxlength="120"></label>
    <label>Kata Sandi (minimal 8 karakter) <input type="password" name="password" required minlength="8"></label>
    <label>Ketik Ulang Kata Sandi <input type="password" name="password_confirmation" required></label>
    <button class="btn">Daftar &amp; Buka Semua Fitur</button>
  </form>
  <p class="mut" style="margin-top:14px">Sudah punya akun? <a href="{{ route('login') }}" data-turbo="false">Masuk di sini</a></p>
</div>
@endsection
