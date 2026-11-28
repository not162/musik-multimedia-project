@extends('layouts.musikku')
@section('title', 'Studio Musik')
@section('content')
<div class="hero">
  <div class="hero-grid">
    <div>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
        <div class="avatar-lg">
          @if($user->avatar_url)
            <img src="{{ $user->avatar_url }}" alt="Logo {{ $user->name }}">
          @else
            <span>{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
          @endif
        </div>
        <div>
          <span class="badge ok">🎛️ STUDIO MUSIK ANGGOTA · {{ $user->role }}</span>
          <div style="font-weight:700;font-size:15px;margin-top:4px">{{ $user->name }}</div>
        </div>
      </div>
      <h1>Studio Musik Saya</h1>
      <p class="mut" style="margin:0 0 12px">
        Atur kenyamanan suara saat mendengarkan musik sesuai selera Anda, putar lagu pilihan, atau kirim masukan perbaikan suara ke tim pengelola secara langsung.
      </p>
      <div class="actions" style="margin:0">
        <button class="btn js-play-all" data-scope="#studioSongs">▶ Putar Semua di Studio</button>
        <button type="button" class="btn ghost" onclick="document.getElementById('pEqStudioBtn')?.click()">🎚️ Pengatur Nada 10-Tingkat</button>
      </div>
    </div>
    <div class="hero-stats">
      <div class="hero-stat"><small>Lagu di Studio</small><b>{{ $songs->count() }}</b></div>
      <div class="hero-stat"><small>Suara Halus &amp; Jernih</small><b>{{ $songs->where('is_voice_smoothed', true)->count() }}</b></div>
      <div class="hero-stat"><small>Status Sambungan</small><b>{{ $studioEnabled ? '● Aktif' : '○ Terbatas' }}</b></div>
      <div class="hero-stat"><small>Masukan Terkirim</small><b>{{ $myRequests->count() }}</b></div>
    </div>
  </div>
</div>

@include('partials.errors')

<div class="panel">
  <h3>⬆️ Impor Lagu Custom (Pita Suara 128 – 250 kbps)</h3>
  <p class="mut" style="font-size:13px">
    Unggah lagu pribadi Anda (.mp3, .mp4, .wav, .m4a, .ogg, .aac, .flac) lalu pilih kerapatan pita suara antara <b>128 kbps hingga 250 kbps</b> agar suara nyanyian terdengar lebih halus, bersih, dan jernih.
  </p>
  <form method="POST" action="{{ route('studio.import') }}" enctype="multipart/form-data" class="form">@csrf
    <div class="row2">
      <label>Judul Lagu *
        <input name="title" value="{{ old('title') }}" maxlength="150" placeholder="Mis. Rekaman Akustik Sore" required>
      </label>
      <label>Nama Penyanyi / Pembuat
        <input name="artist_name" value="{{ old('artist_name') }}" maxlength="120" placeholder="Boleh kosong — otomatis memakai {{ $user->name }}">
      </label>
    </div>
    <div class="row2">
      <label>Pilih File Audio / Video *
        <input type="file" name="audio" accept=".mp3,.mp4,.wav,.ogg,.m4a,.aac,.flac" required>
      </label>
      <label>Kategori Musik (Opsional)
        <select name="genre_id">
          <option value="">-- Pilih Kategori --</option>
          @foreach($genres ?? [] as $g)
            <option value="{{ $g->id }}" @selected((string) old('genre_id') === (string) $g->id)>{{ $g->name }}</option>
          @endforeach
        </select>
      </label>
    </div>
    <div class="row2">
      <label>
        <span>Frekuensi Pita Suara (<b>128 – 250 kbps</b>): <b id="bitrateBadge" class="badge ok">{{ old('voice_bitrate_kbps', 192) }} kbps</b></span>
        <div style="display:flex;align-items:center;gap:10px;margin-top:6px">
          <input type="range" id="bitrateSlider" min="128" max="250" step="1" value="{{ old('voice_bitrate_kbps', 192) }}" style="flex:1">
          <input type="number" id="bitrateNumber" name="voice_bitrate_kbps" min="128" max="250" value="{{ old('voice_bitrate_kbps', 192) }}" style="width:92px" required>
        </div>
        <small class="mut" id="bitrateHint">128 kbps hemat ruang · 192 kbps halus seimbang · 250 kbps detail pita suara tertinggi.</small>
      </label>
      <label>Karakter Kehalusan Suara
        <select name="voice_style">
          @foreach($styles as $k => $st)
            <option value="{{ $k }}" @selected(old('voice_style', 'halus_jernih') === $k)>{{ $st['label'] }}</option>
          @endforeach
        </select>
      </label>
    </div>
    <label>Lirik Lagu (Opsional)
      <textarea name="lyrics" rows="2" maxlength="20000" placeholder="Tuliskan lirik jika ada...">{{ old('lyrics') }}</textarea>
    </label>
    <button class="btn">⬆️ Impor &amp; Haluskan Pita Suara</button>
  </form>
</div>

<div class="two">
  {{-- Panel Kiri: Pengaturan Suara Pribadi --}}
  <div class="panel">
    <h3>🎚️ Pengaturan Suara Pribadi</h3>
    <p class="mut" style="font-size:13px">Setelan ini langsung diterapkan pada pemutar lagu Anda dan tersimpan otomatis di akun Anda.</p>
    @php
      $modeNow = $preferences['eq_mode'] ?? 'auto_genre';
      $dirNow  = $preferences['eq_direction'] ?? 'balanced';
      $intNow  = $preferences['eq_intensity'] ?? 70;
      $macro   = $preferences['eq_macro'] ?? ['bass_tilt' => 0, 'vocal_focus' => 0, 'treble_air' => 0];
      $spdNow  = (string) ($preferences['speed'] ?? 1);
    @endphp
    <form method="POST" action="{{ route('studio.preferences') }}" class="form">@csrf
      <div class="row2">
        <label>Cara Kerja Suara
          <select name="eq_mode">
            <option value="auto_genre" @selected($modeNow === 'auto_genre')>⚡ Otomatis Sesuai Lagu</option>
            <option value="adaptive" @selected($modeNow === 'adaptive')>🎯 Halus &amp; Jernih Otomatis</option>
            <option value="manual" @selected($modeNow === 'manual')>🎛️ Atur Sendiri</option>
          </select>
        </label>
        <label>Gaya Suara Favorit
          <select name="eq_direction">
            <option value="balanced" @selected($dirNow === 'balanced')>Seimbang &amp; Alami</option>
            <option value="warm_bass" @selected($dirNow === 'warm_bass')>Bass Hangat &amp; Empuk</option>
            <option value="bright_clear" @selected($dirNow === 'bright_clear')>Vokal Halus &amp; Jernih</option>
            <option value="punchy" @selected($dirNow === 'punchy')>Bertenaga &amp; Menghentak</option>
            <option value="focus_study" @selected($dirNow === 'focus_study')>Nyaman untuk Fokus / Belajar</option>
          </select>
        </label>
      </div>
      <div class="row2">
        <label>Kekuatan Penyesuaian (0–100%)
          <input type="number" name="eq_intensity" value="{{ $intNow }}" min="0" max="100" required>
        </label>
        <label>Kecepatan Putar
          <select name="speed">
            <option value="0.75" @selected($spdNow === '0.75')>0.75x (Lebih Pelan)</option>
            <option value="1" @selected($spdNow === '1' || $spdNow === '1.0')>1.0x (Normal)</option>
            <option value="1.25" @selected($spdNow === '1.25')>1.25x (Lebih Cepat)</option>
            <option value="1.5" @selected($spdNow === '1.5')>1.5x (Cepat)</option>
          </select>
        </label>
      </div>
      <div class="row2">
        <label>Kekuatan Bass (-6 s/d +6)
          <input type="number" step="0.5" min="-6" max="6" name="bass_tilt" value="{{ $macro['bass_tilt'] ?? 0 }}">
        </label>
        <label>Kejernihan Vokal (-6 s/d +6)
          <input type="number" step="0.5" min="-6" max="6" name="vocal_focus" value="{{ $macro['vocal_focus'] ?? 0 }}">
        </label>
      </div>
      <label>Kehalusan Nada Tinggi (-6 s/d +6)
        <input type="number" step="0.5" min="-6" max="6" name="treble_air" value="{{ $macro['treble_air'] ?? 0 }}">
      </label>
      <label class="inline" style="font-size:13px">
        <input type="checkbox" name="eq_auto_gain" value="1" @checked($preferences['eq_auto_gain'] ?? true)>
        Haluskan volume suara &amp; cegah suara pecah secara otomatis
      </label>
      <button class="btn sm">💾 Simpan Pengaturan Studio</button>
    </form>
  </div>

  {{-- Panel Kanan: Kirim Masukan ke Pengelola & Pantau Status Real-Time --}}
  <div class="panel">
    <h3>📨 Minta Perapihan Suara ke Pengelola Studio</h3>
    <p class="mut" style="font-size:13px">Jika ada lagu yang suaranya terasa kurang jelas atau ingin lebih lembut, kirim permintaan Anda di sini. Setelah dirapikan pengelola, pembaruan langsung masuk ke daftar lagu Anda.</p>
    <form method="POST" action="{{ route('studio.requests.store') }}" class="form">@csrf
      <label>Pilih Lagu
        <select name="song_id" required>
          @foreach($songs as $s)
            <option value="{{ $s->id }}">{{ $s->title }} — {{ $s->artist?->name }}</option>
          @endforeach
        </select>
      </label>
      <label>Karakter Suara yang Diinginkan
        <select name="desired_style" required>
          @foreach($styles as $k => $st)
            <option value="{{ $k }}">{{ $st['label'] }}</option>
          @endforeach
        </select>
      </label>
      <label>Catatan Singkat (opsional)
        <input name="note" maxlength="400" placeholder="Mis. tolong perhalus suara vokal dan kurangi desis latar">
      </label>
      <button class="btn sm">📨 Kirim ke Pengelola Studio</button>
    </form>

    <div style="margin-top:14px;border-top:1px solid var(--line);padding-top:10px">
      <b>Status Permintaan Anda Terbaru</b>
      <table class="table" style="margin-top:6px">
        <tr><th>Lagu</th><th>Permintaan</th><th>Status</th><th>Balasan</th></tr>
        @forelse($myRequests as $req)
          <tr>
            <td><b>{{ $req->song?->title }}</b></td>
            <td>{{ $styles[$req->desired_style]['label'] ?? $req->desired_style }}@if($req->note)<br><small class="mut">{{ $req->note }}</small>@endif</td>
            <td>
              @if($req->status === 'completed')
                <span class="badge ok">✓ Selesai &amp; Aktif</span>
              @else
                <span class="badge warn">⏳ Menunggu Pengelola</span>
              @endif
            </td>
            <td><small>{{ $req->admin_reply ?? 'Sedang antre diperiksa pengelola' }}</small></td>
          </tr>
        @empty
          <tr><td colspan="4" class="mut">Belum ada permintaan yang dikirim.</td></tr>
        @endforelse
      </table>
    </div>
  </div>
</div>

<div class="panel">
  <h3>🎧 Daftar Lagu Tersedia di Studio Musik</h3>
  <div class="song-list" id="studioSongs">
    @foreach($songs as $s)
      @include('partials.song-row', [
        'song' => $s,
        'deleteCustomUrl' => ($s->uploaded_by && (int) $s->uploaded_by === (int) $user->id) ? route('studio.songs.destroy', $s) : null,
      ])
    @endforeach
  </div>
</div>

@push('scripts')
<script>
(() => {
  const slider = document.getElementById('bitrateSlider');
  const num = document.getElementById('bitrateNumber');
  const badge = document.getElementById('bitrateBadge');
  if (!slider || !num || !badge) return;
  const sync = (val) => {
    const n = Math.max(128, Math.min(250, parseInt(val || 192, 10) || 192));
    slider.value = n;
    num.value = n;
    badge.textContent = n + ' kbps';
  };
  slider.addEventListener('input', () => sync(slider.value));
  num.addEventListener('change', () => sync(num.value));
})();
</script>
@endpush
@endsection
