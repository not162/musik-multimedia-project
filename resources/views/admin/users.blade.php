@extends('layouts.musikku')
@section('title', 'Pengguna')
@section('content')
<h1>Pengguna</h1>
@include('admin._nav')
<form method="GET" class="actions" style="max-width:420px"><input name="q" value="{{ request('q') }}" placeholder="Cari nama/email…"><button class="btn ghost">Cari</button></form>
<table class="table">
  <tr><th>Pengguna</th><th>Email</th><th>Playlist</th><th>Peran</th><th>Status</th><th>Daftar</th><th></th></tr>
  @foreach($users as $u)
  <tr>
    <td>
      <div class="cell">
        <div class="avatar-chip">
          @if($u->avatar_url)
            <img src="{{ $u->avatar_url }}" alt="{{ $u->name }}">
          @else
            <span>{{ strtoupper(mb_substr($u->name, 0, 1)) }}</span>
          @endif
        </div>
        <span>{{ $u->name }}</span>
      </div>
    </td>
    <td>{{ $u->email }}</td>
    <td>{{ $u->playlists_count }}</td>
    <td><span class="badge {{ $u->isAdmin() ? 'info' : '' }}">{{ $u->role }}</span></td>
    <td>
      @if($u->isLocked())
        <span class="badge danger">Terkunci</span>
      @elseif($u->is_active)
        <span class="badge ok">Aktif</span>
      @else
        <span class="badge warn">Nonaktif</span>
      @endif
    </td>
    <td>{{ $u->created_at->format('d/m/Y') }}</td>
    <td class="act">
      @if($u->isLocked())
        <form method="POST" action="{{ route('admin.users.unlock', $u) }}">@csrf @method('PATCH')<button class="btn sm">Buka Kunci</button></form>
      @endif
      @if($u->id !== auth()->id())
        <form method="POST" action="{{ route('admin.users.role', $u) }}" data-turbo-confirm="Ubah peran {{ $u->name }}?">@csrf @method('PATCH')<button class="btn ghost sm">{{ $u->isAdmin() ? 'Jadikan user' : 'Jadikan admin' }}</button></form>
        <form method="POST" action="{{ route('admin.users.status', $u) }}">@csrf @method('PATCH')<button class="btn ghost sm">{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
        <form method="POST" action="{{ route('admin.users.destroy', $u) }}" data-turbo-confirm="Hapus pengguna {{ $u->name }} secara permanen?">@csrf @method('DELETE')<button class="btn danger sm">Hapus</button></form>
      @endif
    </td>
  </tr>
  @endforeach
</table>
{{ $users->links('pagination::default') }}
@endsection
