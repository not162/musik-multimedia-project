<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pemutar Musik Interaktif</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<header class="top">
    <h1>♪ Pemutar Musik Interaktif</h1>
    <p>Dirancang untuk mahasiswa: atur musik belajarmu sendiri.</p>
</header>

<main class="layout">
    <section class="panel stage" aria-label="Pemutar">
        <div class="now">
            <div class="now-title" id="nowTitle">Belum ada lagu dipilih</div>
            <div class="now-artist" id="nowArtist">Pilih lagu dari pustaka di samping</div>
        </div>

        <canvas id="viz" aria-label="Visualisasi audio"></canvas>

        <div class="viz-toolbar">
            <label for="vizMode">Mode visualisasi</label>
            <select id="vizMode" aria-label="Mode visualisasi">
                <option value="bars">Batang frekuensi</option>
                <option value="wave">Gelombang</option>
                <option value="circle">Lingkaran</option>
            </select>
        </div>

        <details class="equalizer">
            <summary>Equalizer audio</summary>
            <label class="eq-preset">Preset
                <select id="eqPreset" aria-label="Preset equalizer">
                    <option value="flat">Flat</option>
                    <option value="bass">Bass Boost</option>
                    <option value="vocal">Vokal</option>
                    <option value="lofi">Lofi</option>
                    <option value="custom">Kustom</option>
                </select>
            </label>
            <div class="eq-bands">
                <label for="eqLow">Bass <output id="eqLowValue">0 dB</output>
                    <input type="range" id="eqLow" min="-12" max="12" value="0" step="1">
                </label>
                <label for="eqMid">Mid <output id="eqMidValue">0 dB</output>
                    <input type="range" id="eqMid" min="-12" max="12" value="0" step="1">
                </label>
                <label for="eqHigh">Treble <output id="eqHighValue">0 dB</output>
                    <input type="range" id="eqHigh" min="-12" max="12" value="0" step="1">
                </label>
            </div>
        </details>

        <div class="progress">
            <span id="cur">0:00</span>
            <input type="range" id="seek" min="0" max="1000" value="0" aria-label="Posisi lagu">
            <span id="dur">0:00</span>
        </div>

        <div class="controls">
            <button id="btnShuffle" class="ctl" title="Acak" aria-pressed="false">🔀</button>
            <button id="btnPrev" class="ctl" title="Sebelumnya">⏮</button>
            <button id="btnPlay" class="ctl main" title="Putar / Jeda">▶</button>
            <button id="btnNext" class="ctl" title="Berikutnya">⏭</button>
            <button id="btnRepeat" class="ctl" title="Ulangi: mati">🔁</button>
        </div>

        <div class="volume">
            <span id="volIcon">🔊</span>
            <input type="range" id="vol" min="0" max="100" value="80" aria-label="Volume">
            <span id="volVal">80%</span>
        </div>

        <p class="hint">Pintasan: <kbd>Spasi</kbd> putar/jeda · <kbd>←</kbd><kbd>→</kbd> maju/mundur 5 detik · <kbd>Shift</kbd>+<kbd>←</kbd>/<kbd>→</kbd> lagu sebelumnya/berikutnya · <kbd>↑</kbd><kbd>↓</kbd> volume</p>
    </section>

    <section class="panel side">
        <nav class="tabs" role="tablist">
            <button class="tab active" data-tab="library">Pustaka</button>
            <button class="tab" data-tab="favorites">Favorit <span id="favCount" class="badge">0</span></button>
            <button class="tab" data-tab="top">Top 10</button>
            <button class="tab" data-tab="queue">Antrean <span id="qCount" class="badge">0</span></button>
            <button class="tab" data-tab="playlists">Playlist</button>
            <button class="tab" data-tab="upload">Tambah Lagu</button>
        </nav>

        <div class="tabpane" id="tab-library">
            <div class="filters">
                <input type="search" id="search" placeholder="Cari judul atau artis…">
                <select id="genre"><option value="">Semua genre</option></select>
            </div>
            <ul class="list" id="songList"></ul>
        </div>

        <div class="tabpane" id="tab-favorites" hidden>
            <div class="bar"><span>Lagu favorit kamu</span></div>
            <ul class="list" id="favoritesList"></ul>
        </div>

        <div class="tabpane" id="tab-top" hidden>
            <div class="bar"><span>10 lagu paling sering diputar</span></div>
            <ul class="list" id="topList"></ul>
        </div>

        <div class="tabpane" id="tab-queue" hidden>
            <div class="bar"><span>Lagu yang akan diputar</span><button class="link" id="clearQueue">Kosongkan</button></div>
            <ul class="list" id="queueList"></ul>
        </div>

        <div class="tabpane" id="tab-playlists" hidden>
            <form id="plForm" class="inline-form">
                <input type="text" id="plName" placeholder="Nama playlist baru" maxlength="100" required>
                <button class="btn">Buat</button>
            </form>
            <ul class="list" id="playlistList"></ul>
            <div id="playlistDetail"></div>
        </div>

        <div class="tabpane" id="tab-upload" hidden>
            <form id="upForm" class="stack">
                <label>Judul<input type="text" name="title" required maxlength="150"></label>
                <label>Artis<input type="text" name="artist" maxlength="100"></label>
                <label>Genre<input type="text" name="genre" maxlength="50" placeholder="mis. Lofi"></label>
                <label>Berkas audio (mp3, wav, ogg, m4a, aac — maks 30 MB)<input type="file" name="file" id="upFile" accept="audio/*" required></label>
                <button class="btn">Unggah</button>
                <small class="muted">Gunakan lagu milik sendiri atau berlisensi bebas (royalty-free).</small>
            </form>
        </div>
    </section>
</main>

<dialog id="dlg">
    <h3 id="dlgTitle">Tambah ke playlist</h3>
    <div id="dlgBody" class="stack"></div>
    <button class="btn ghost" id="dlgClose">Tutup</button>
</dialog>

<div id="toast" role="status" aria-live="polite"></div>
<audio id="audio" preload="metadata"></audio>

<script>
    window.APP = {
        base: @json(url('/')),
        songs: @json($songs),
        genres: @json($genres),
        playlists: @json($playlists)
    };
</script>
<script src="{{ asset('js/player.js') }}?v={{ filemtime(public_path('js/player.js')) }}"></script>
</body>
</html>
