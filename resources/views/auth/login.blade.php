@extends('layouts.musikku')
@section('title', 'Masuk')
@section('content')
<div class="authbox">
  <h1>Masuk</h1>
  @include('partials.errors')
  <form method="POST" action="{{ route('login') }}" class="form" data-turbo="false">@csrf
    <label>Email <input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
    <label>Kata sandi <input type="password" name="password" required></label>
    <label class="inline"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
    <button class="btn">Masuk</button>
  </form>
  <p class="mut">Belum punya akun? <a href="{{ route('register') }}" data-turbo="false">Daftar</a></p>
</div>
@endsection
