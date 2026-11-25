<div class="subnav">
  <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'on' : '' }}">Dashboard</a>
  <a href="{{ route('admin.songs.index') }}" class="{{ request()->routeIs('admin.songs.*') ? 'on' : '' }}">Lagu</a>
  <a href="{{ route('admin.artists.index') }}" class="{{ request()->routeIs('admin.artists.*') ? 'on' : '' }}">Artis</a>
  <a href="{{ route('admin.albums.index') }}" class="{{ request()->routeIs('admin.albums.*') ? 'on' : '' }}">Album</a>
  <a href="{{ route('admin.genres.index') }}" class="{{ request()->routeIs('admin.genres.*') ? 'on' : '' }}">Genre</a>
  <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'on' : '' }}">Pengguna</a>
  <a href="{{ route('admin.logs.index') }}" class="{{ request()->routeIs('admin.logs.*') ? 'on' : '' }}">🚨 Error & Alert</a>
  <a href="{{ route('admin.security.index') }}" class="{{ request()->routeIs('admin.security.*') ? 'on' : '' }}">🛡️ Security & LRS</a>
  <a href="{{ route('admin.system.index') }}" class="{{ request()->routeIs('admin.system.*') ? 'on' : '' }}">🖥️ Sistem, Backup & SEO</a>
</div>
