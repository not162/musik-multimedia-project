<div class="subnav">
  <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'on' : '' }}">Ringkasan</a>
  <a href="{{ route('admin.songs.index') }}" class="{{ request()->routeIs('admin.songs.*') ? 'on' : '' }}">Lagu</a>
  <a href="{{ route('admin.artists.index') }}" class="{{ request()->routeIs('admin.artists.*') ? 'on' : '' }}">Artis</a>
  <a href="{{ route('admin.albums.index') }}" class="{{ request()->routeIs('admin.albums.*') ? 'on' : '' }}">Album</a>
  <a href="{{ route('admin.labels.index') }}" class="{{ request()->routeIs('admin.labels.*') ? 'on' : '' }}">🏷️ Label Musik</a>
  <a href="{{ route('admin.genres.index') }}" class="{{ request()->routeIs('admin.genres.*') ? 'on' : '' }}">Jenis Musik</a>
  <a href="{{ route('admin.ai.index') }}" class="{{ request()->routeIs('admin.ai.*') ? 'on' : '' }}">🤖 Studio AI & Suara</a>
  <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'on' : '' }}">Pengguna</a>
  <a href="{{ route('admin.logs.index') }}" class="{{ request()->routeIs('admin.logs.*') ? 'on' : '' }}">🚨 Catatan Masalah</a>
  <a href="{{ route('admin.security.index') }}" class="{{ request()->routeIs('admin.security.*') ? 'on' : '' }}">🛡️ Keamanan & Catatan</a>
  <a href="{{ route('admin.system.index') }}" class="{{ request()->routeIs('admin.system.*') ? 'on' : '' }}">🖥️ Pengaturan Web</a>
</div>
