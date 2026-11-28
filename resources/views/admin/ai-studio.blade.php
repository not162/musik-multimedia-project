@extends('layouts.musikku')
@section('title', 'Studio AI, Pelatih Suara & Koneksi Data')
@section('content')
<h1>🤖 Studio AI, Alat Bantu Latih &amp; Penghalus Suara <span class="badge info">Khusus Admin</span></h1>
@include('admin._nav')
@include('partials.errors')

<div class="stats">
  <div class="stat"><small>Lagu Sudah Dilabeli</small><b>{{ $trainStats['labeled'] }}</b></div>
  <div class="stat"><small>Data Latih / Data Uji</small><b>{{ $trainStats['train'] }} / {{ $trainStats['validation'] }}</b></div>
  <div class="stat"><small>Lagu Suara Halus &amp; Jernih</small><b>{{ $trainStats['smoothed'] }} / {{ $songs->count() }}</b></div>
  <div class="stat"><small>Rata-Rata Kejernihan</small><b>{{ $trainStats['avg_clarity'] }}/100</b></div>
</div>

{{-- 1. Kunci Model AI & Pengaturan Koneksi --}}
<div class="two">
  <div class="panel">
    <h3>🔑 Konfigurasi API AI Key (Sambungan Model AI)</h3>
    <p class="mut" style="font-size:13px">Masukkan kunci akses AI (seperti Google Gemini atau OpenAI) agar sistem bisa membantu menilai dan menghaluskan suara lagu. Kunci disimpan aman dan tersembunyi.</p>
    <div style="margin-bottom:10px">
      @if($setting->hasApiKey())
        <span class="badge ok">✓ Kunci AI Terpasang ({{ $setting->key_hint ?? 'ENV Lokal' }})</span>
      @else
        <span class="badge warn">○ Kunci AI Belum Diisi (Memakai Pelatih Suara Bawaan)</span>
      @endif
      @if($setting->compute_name || $setting->hasComputeKey())
        <span class="badge info">💻 Compute: {{ $setting->compute_name ?: 'Tersimpan' }}{{ $setting->hasComputeKey() ? ' (' . ($setting->compute_key_hint ?? 'Aktif') . ')' : '' }}</span>
      @endif
      @if($setting->last_status)
        <span class="badge">{{ $setting->last_status }}</span>
      @endif
    </div>

    <form method="POST" action="{{ route('admin.ai.provider') }}" class="form">@csrf
      <div class="row2">
        <label>Layanan AI
          <select name="provider">
            <option value="gemini" @selected($setting->provider === 'gemini')>Google Gemini</option>
            <option value="nvidia" @selected($setting->provider === 'nvidia')>NVIDIA AI (NIM / Riva)</option>
            <option value="openai" @selected($setting->provider === 'openai')>OpenAI (ChatGPT)</option>
            <option value="custom" @selected($setting->provider === 'custom')>Layanan AI Lainnya</option>
          </select>
        </label>
        <label>Nama Model AI <input name="model" value="{{ $setting->model }}" required></label>
      </div>
      <div class="row2">
        <label>Nama Compute AI (opsional) <input name="compute_name" value="{{ $setting->compute_name }}" placeholder="Mis. constant-aquamarine-meerkat"></label>
        <label>Alamat Layanan Khusus (opsional) <input name="base_url" value="{{ $setting->base_url }}" placeholder="https://integrate.api.nvidia.com/v1"></label>
      </div>
      <div class="row2">
        <label>Kunci Rahasia AI (kosongkan jika tidak diganti)
          <input type="password" name="api_key" placeholder="Tempel kunci AI di sini…" autocomplete="new-password">
        </label>
        <label>Kunci Rahasia Compute (kosongkan jika tidak diganti)
          <input type="password" name="compute_key" placeholder="Tempel kunci compute di sini…" autocomplete="new-password">
        </label>
      </div>
      <label class="inline"><input type="checkbox" name="auto_finetune_realtime" value="1" @checked($setting->auto_finetune_realtime)> Otomatis haluskan &amp; jernihkan suara setiap ada lagu baru diunggah</label>
      <label class="inline"><input type="checkbox" name="is_active" value="1" @checked($setting->is_active)> Gunakan bantuan Model AI internet saat melatih lagu</label>
      <div class="actions">
        <button class="btn sm">💾 Simpan Pengaturan AI</button>
      </div>
    </form>
    <form method="POST" action="{{ route('admin.ai.test') }}" style="margin-top:8px">@csrf
      <button class="btn ghost sm">⚡ Uji Sambungan Model AI</button>
    </form>
  </div>

  <div class="panel">
    <h3>🔌 Pengaturan Koneksi Data (Admin ↔ Pengguna ↔ Halaman Depan)</h3>
    <p class="mut" style="font-size:13px">Atur fitur mana saja yang aktif terhubung, batas pemakaian per menit agar server tidak berat, dan apakah perubahan langsung muncul di layar pendengar.</p>
    <table class="table">
      <tr><th>Bagian yang Terhubung</th><th>Batas/Mnt</th><th>Aktif</th><th>Kirim Langsung</th><th></th></tr>
      @foreach($rules as $rule)
        <tr>
          <td>
            <span class="badge {{ $rule->group_name === 'admin' ? 'info' : ($rule->group_name === 'user' ? 'ok' : '') }}">
              {{ $rule->group_name === 'admin' ? 'ADMIN' : ($rule->group_name === 'user' ? 'ANGGOTA' : 'PUBLIK') }}
            </span>
            <br><small><b>{{ $rule->description }}</b></small>
            <br><small class="mut"><code class="inline">{{ $rule->uri_pattern }}</code></small>
          </td>
          <form method="POST" action="{{ route('admin.api.ruleUpdate', $rule) }}">@csrf @method('PATCH')
            <td><input type="number" name="rate_limit_per_min" value="{{ $rule->rate_limit_per_min }}" min="1" max="5000" style="width:72px;padding:5px"></td>
            <td><input type="checkbox" name="is_enabled" value="1" @checked($rule->is_enabled)></td>
            <td><input type="checkbox" name="realtime_broadcast" value="1" @checked($rule->realtime_broadcast)></td>
            <td><button class="btn ghost sm">Simpan</button></td>
          </form>
        </tr>
      @endforeach
    </table>
    <div style="margin-top:12px">
      <b>Kunci Akses Aplikasi Aktif ({{ $tokens->count() }}):</b>
      <div class="chips" style="margin-top:6px">
        @forelse($tokens->take(6) as $tok)
          <span class="chip sm">{{ $tok->tokenable?->name ?? 'Admin' }}: {{ $tok->name }}</span>
        @empty
          <span class="mut" style="font-size:13px">Belum ada kunci akses tambahan.</span>
        @endforelse
      </div>
    </div>
  </div>
</div>

{{-- Permintaan Masuk Real-Time dari Studio Musik Pengguna --}}
<div class="panel">
  <h3 style="margin:0 0 4px">📨 Permintaan Masuk dari Studio Musik Pengguna (Real-Time)</h3>
  <p class="mut" style="font-size:13px;margin:0 0 10px">Permintaan yang dikirim pengguna lewat menu <b>Studio Musik</b> muncul otomatis di sini. Klik proses agar Model AI menghaluskan suara lalu mengirim hasilnya balik ke pengguna secara langsung.</p>
  <table class="table">
    <tr><th>Pengguna</th><th>Lagu</th><th>Karakter Diminta</th><th>Catatan</th><th>Status</th><th>Tindakan</th></tr>
    @forelse($studioRequests as $sr)
      <tr>
        <td><b>{{ $sr->user?->name }}</b></td>
        <td>{{ $sr->song?->title }}</td>
        <td><span class="badge info">{{ $presets[$sr->desired_style]['label'] ?? $sr->desired_style }}</span></td>
        <td><small>{{ $sr->note ?: '—' }}</small></td>
        <td>
          @if($sr->status === 'completed')
            <span class="badge ok">✓ Selesai</span>
          @else
            <span class="badge warn">⏳ Menunggu</span>
          @endif
        </td>
        <td class="act">
          @if($sr->status === 'pending' && $sr->song)
            <form method="POST" action="{{ route('admin.ai.requestProcess', $sr) }}">@csrf
              <button class="btn sm">🪄 Proses Dengan Model AI</button>
            </form>
          @else
            <small class="mut">{{ $sr->processed_at?->diffForHumans() ?? 'Selesai' }}</small>
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="6" class="mut">Belum ada permintaan dari Studio Musik pengguna.</td></tr>
    @endforelse
  </table>
</div>

{{-- 2. Alat Bantu Latih Model AI: Labelling, Train, Validation --}}
<div class="panel">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
    <div>
      <h3 style="margin:0 0 4px">🧪 Alat Bantu Latih Model AI (Labelling → Train → Validation)</h3>
      <p class="mut" style="font-size:13px;margin:0">Ikuti 3 langkah sederhana ini agar sistem belajar cara membuat suara lagu lebih halus, lembut di vokal, dan bebas bising.</p>
    </div>
    <form method="POST" action="{{ route('admin.ai.autoLabel') }}">@csrf
      <button class="btn sm">🏷️ Beri Label Otomatis Semua Lagu</button>
    </form>
  </div>

  <div class="steps-grid" style="margin-top:14px">
    {{-- Langkah 1: Labelling --}}
    <div class="step-card">
      <span class="step-num">Langkah 1 · Labelling</span>
      <h3>Beri Contoh &amp; Bagi Data</h3>
      <p class="mut" style="font-size:13px">Pilih lagu lalu tentukan contoh karakter suara yang diinginkan, apakah masuk <b>Data Latih (Train)</b> atau <b>Data Uji (Validation)</b>.</p>
      @if($songs->count())
      <form method="POST" id="labelQuickForm" action="{{ route('admin.ai.label', $songs->first()) }}" class="form" style="gap:8px;margin-top:10px">@csrf
        <label>Pilih Lagu
          <select id="labelSongSelect" onchange="document.getElementById('labelQuickForm').action='{{ url('/admin/ai-studio/songs') }}/' + this.value + '/label'">
            @foreach($songs as $s)
              <option value="{{ $s->id }}">{{ $s->title }} ({{ $s->training_split === 'validation' ? 'Data Uji' : ($s->training_split === 'train' ? 'Data Latih' : 'Belum Dilabeli') }})</option>
            @endforeach
          </select>
        </label>
        <div class="row2">
          <label>Contoh Karakter Suara
            <select name="preset_key">
              @foreach($presets as $key => $p)
                <option value="{{ $key }}">{{ $p['label'] }}</option>
              @endforeach
            </select>
          </label>
          <label>Masuk Kelompok
            <select name="dataset_split">
              <option value="train">Data Latih (Train)</option>
              <option value="validation">Data Uji (Validation)</option>
            </select>
          </label>
        </div>
        <div class="row2">
          <label>Target Kejernihan (50–100) <input type="number" name="target_clarity" value="94" min="50" max="100" required></label>
          <label>Target Kehalusan (50–100) <input type="number" name="target_smoothness" value="95" min="50" max="100" required></label>
        </div>
        <label>Catatan Contoh (opsional) <input name="notes" placeholder="Mis. vokal terdengar lembut tanpa desis"></label>
        <label class="inline" style="font-size:12.5px"><input type="checkbox" name="use_ai" value="1" checked> Minta bantuan AI memperkirakan setelan</label>
        <button class="btn sm">🏷️ Simpan Label Contoh</button>
      </form>
      @endif
    </div>

    {{-- Langkah 2: Train --}}
    <div class="step-card">
      <span class="step-num">Langkah 2 · Train</span>
      <h3>Latih Model Penjernih Suara</h3>
      <p class="mut" style="font-size:13px">Model akan mempelajari seluruh contoh di kelompok <b>Data Latih</b> agar tahu cara meredam suara kasar dan memperjelas vokal.</p>
      <form method="POST" action="{{ route('admin.ai.train') }}" class="form" style="gap:8px;margin-top:10px">@csrf
        <label>Nama Model AI <input name="model_name" value="Model Penjernih Suara Musikku" required></label>
        <label>Putaran Belajar (Epochs) <input type="number" name="epochs" value="120" min="20" max="500" required></label>
        <button class="btn sm">🧠 Latih Model (Train) Sekarang</button>
      </form>
      @if($latestRun)
        <div style="margin-top:12px;padding:10px;background:#ffffff;border:1px solid var(--line);border-radius:10px;font-size:13px">
          <b>Hasil Latihan Terakhir:</b><br>
          Model: <b>{{ $latestRun->model_name }}</b><br>
          Contoh Dipelajari: <b>{{ $latestRun->train_count }} lagu</b> · {{ $latestRun->epochs }} putaran<br>
          Selisih Kesalahan Latihan: <b>{{ $latestRun->train_loss }}</b> <span class="mut">(makin kecil makin baik)</span>
          @if(!empty($latestRun->metrics_detail['summary']))
            <div class="mut" style="margin-top:6px;font-size:12.5px">{{ $latestRun->metrics_detail['summary'] }}</div>
          @endif
        </div>
      @endif
    </div>

    {{-- Langkah 3: Validation --}}
    <div class="step-card">
      <span class="step-num">Langkah 3 · Validation</span>
      <h3>Uji Ketepatan Model</h3>
      <p class="mut" style="font-size:13px">Menguji model ke lagu di kelompok <b>Data Uji</b> yang tidak ikut dilatih, untuk memastikan hasilnya benar-benar jernih.</p>
      <form method="POST" action="{{ route('admin.ai.validate') }}" class="form" style="gap:8px;margin-top:10px">@csrf
        <button class="btn sm">✅ Jalankan Uji Validasi (Validation)</button>
      </form>
      @if($latestRun && $latestRun->status === 'validated')
        <div style="margin-top:12px;padding:10px;background:#ecfdf5;border:1px solid #86efac;border-radius:10px;font-size:13px">
          <b>Hasil Uji Validasi:</b><br>
          Ketepatan Model: <b style="color:var(--acc2)">{{ $latestRun->val_accuracy }}%</b><br>
          Rata-rata Peningkatan Kejernihan: <b>+{{ $latestRun->clarity_gain_pct }} poin</b><br>
          Jumlah Lagu Diuji: <b>{{ $latestRun->val_count }} lagu</b> · Selisih Uji: <b>{{ $latestRun->val_loss }}</b>
        </div>
        @if(!empty($latestRun->metrics_detail['comparisons']))
          <div style="margin-top:8px;font-size:12.5px">
            @foreach($latestRun->metrics_detail['comparisons'] as $cmp)
              <div style="padding:4px 0;border-bottom:1px dashed var(--line)">
                <b>{{ $cmp['song_title'] }}</b> ({{ $cmp['label'] }}):
                Kejernihan {{ $cmp['before_clarity'] }} → <b>{{ $cmp['after_estimated'] }}/100</b>
              </div>
            @endforeach
          </div>
        @endif
      @else
        <p class="mut" style="font-size:12.5px;margin-top:12px">Belum ada uji validasi. Tekan tombol di atas setelah selesai melatih model.</p>
      @endif
    </div>
  </div>

  @if($samples->count())
    <details style="margin-top:14px">
      <summary class="btn ghost sm" style="display:inline-flex">📋 Lihat Daftar Contoh Berlabel ({{ $samples->count() }} Lagu)</summary>
      <table class="table" style="margin-top:10px">
        <tr><th>Lagu</th><th>Label Karakter</th><th>Kelompok Data</th><th>Skor Suara Awal</th><th>Target Halus / Jernih</th><th>Catatan</th></tr>
        @foreach($samples as $sp)
          <tr>
            <td><b>{{ $sp->song?->title }}</b><br><small class="mut">{{ $sp->song?->artist?->name }}</small></td>
            <td><span class="badge info">{{ $sp->label_name }}</span></td>
            <td>
              @if($sp->dataset_split === 'validation')
                <span class="badge warn">Data Uji (Validation)</span>
              @else
                <span class="badge ok">Data Latih (Train)</span>
              @endif
            </td>
            <td>
              Kejernihan {{ $sp->extracted_features['clarity_score'] ?? '-' }}/100 ·
              Kehalusan {{ $sp->extracted_features['smoothness_score'] ?? '-' }}/100
            </td>
            <td>Halus {{ $sp->target_smoothness }}% · Jernih {{ $sp->target_clarity }}%</td>
            <td><small>{{ $sp->notes }}</small></td>
          </tr>
        @endforeach
      </table>
    </details>
  @endif
</div>

{{-- 3. Langkah 4: Latih & Haluskan Suara Tiap Lagu --}}
<div class="panel">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:10px">
    <div>
      <h3 style="margin:0 0 4px">🪄 Langkah 4 · Latih Suara Agar Lebih Halus dan Jernih</h3>
      <p class="mut" style="font-size:13px;margin:0">Terapkan hasil belajar model AI ke file lagu untuk mengurangi bising/desis, melembutkan vokal yang tajam, dan meratakan volume secara otomatis ke halaman pengguna dan publik.</p>
    </div>
    <form method="POST" action="{{ route('admin.ai.polishAll') }}" class="actions" style="margin:0;flex-wrap:wrap">@csrf
      <select name="preset_key" style="width:auto;padding:6px 10px">
        @foreach($presets as $key => $p)
          <option value="{{ $key }}">{{ $p['label'] }}</option>
        @endforeach
      </select>
      <input type="number" name="target_sample_rate_khz" min="100" max="1100" step="10" placeholder="Master 100–1100 kHz" style="width:165px;padding:6px 10px" title="Kosongkan untuk standar 48 kHz, atau isi 100–1100 kHz">
      <button class="btn sm">🪄 Latih &amp; Haluskan Semua Lagu</button>
    </form>
  </div>

  @foreach($songs as $song)
    <div class="panel" style="background:#f8fafc;margin:12px 0">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
        <div>
          <b style="font-size:16px">{{ $song->title }}</b>
          <span class="mut"> — {{ $song->artist?->name }}</span>
          <span class="badge">{{ strtoupper($song->media_format ?: pathinfo((string)$song->file_path, PATHINFO_EXTENSION) ?: 'MP3') }}</span>
          @if($song->genre)<span class="badge">{{ $song->genre->name }}</span>@endif
          @if($song->recordLabel)<span class="badge info">🏷️ {{ $song->recordLabel->name }}</span>@endif
          @if($song->target_sample_rate_khz)
            <span class="badge info">🔊 {{ $song->target_sample_rate_khz }} kHz</span>
          @endif
          @if($song->is_voice_smoothed)
            <span class="badge ok">✨ Suara Halus &amp; Jernih · Kejernihan {{ $song->voice_clarity_score }}/100 · Kehalusan {{ $song->voice_smoothness_score }}/100</span>
          @elseif($song->is_ai_finetuned)
            <span class="badge ok">✨ Sudah Disesuaikan AI · {{ $song->ai_mood }}</span>
          @else
            <span class="badge warn">Belum Dilatih Suaranya</span>
          @endif
          @if($song->ai_mastering_notes)
            <div class="mut" style="font-size:13px;margin-top:5px">💬 {{ $song->ai_mastering_notes }}</div>
          @endif
          @if($song->hires_file_path)
            <div style="margin-top:4px"><a class="btn ghost sm" href="{{ route('songs.hires', $song) }}" data-turbo="false">⬇ Unduh Master {{ $song->target_sample_rate_khz }} kHz (.WAV)</a></div>
          @endif
        </div>

        <form method="POST" action="{{ route('admin.ai.polish', $song) }}" class="actions" style="margin:0;flex-wrap:wrap">@csrf
          <select name="preset_key" style="width:auto;padding:6px 10px">
            @foreach($presets as $key => $p)
              <option value="{{ $key }}" @selected(($song->voice_preset ?? 'halus_jernih') === $key)>{{ $p['label'] }}</option>
            @endforeach
          </select>
          <select name="noise_strength" style="width:auto;padding:6px 10px">
            <option value="light">Peredam Bising: Ringan</option>
            <option value="medium" selected>Peredam Bising: Sedang</option>
            <option value="strong">Peredam Bising: Kuat</option>
          </select>
          <input type="number" name="target_sample_rate_khz" min="100" max="1100" step="10" value="{{ $song->target_sample_rate_khz }}" placeholder="100–1100 kHz" style="width:130px;padding:6px 10px" title="Opsional: ekspor arsip WAV 100–1100 kHz">
          <input name="custom_prompt" placeholder="Keinginan Anda (mis. suara vokal lebih lembut & bersih)" style="width:220px;padding:6px 10px">
          <input type="hidden" name="render_audio" value="1">
          <button class="btn sm">🪄 Latih Suara Halus &amp; Jernih</button>
        </form>
      </div>

      <details style="margin-top:10px">
        <summary class="btn ghost sm" style="display:inline-flex">🎛️ Atur Sendiri Suara Secara Manual</summary>
        <form method="POST" action="{{ route('admin.ai.manual', $song) }}" style="margin-top:12px">@csrf @method('PUT')
          <div class="row2" style="margin-bottom:10px">
            <label>Label Musik
              <select name="record_label_id">
                <option value="">—</option>
                @foreach($labels as $lb)
                  <option value="{{ $lb->id }}" @selected($song->record_label_id == $lb->id)>{{ $lb->name }}</option>
                @endforeach
              </select>
            </label>
            <label>Suasana Suara <input name="ai_mood" value="{{ $song->ai_mood ?? 'Halus & Jernih' }}" required></label>
            <label>Tempo Ketukan <input type="number" name="ai_tempo_bpm" value="{{ $song->ai_tempo_bpm ?? 100 }}" min="40" max="240" required></label>
            <label>Gaya Utama
              <select name="ai_direction">
                @foreach(['balanced' => 'Seimbang & Alami', 'warm_bass' => 'Bass Hangat & Empuk', 'bright_clear' => 'Vokal Halus & Jernih', 'punchy' => 'Bertenaga & Bersih', 'focus_study' => 'Tenang & Nyaman'] as $dir => $dirLabel)
                  <option value="{{ $dir }}" @selected(($song->ai_direction ?? 'balanced') === $dir)>{{ $dirLabel }}</option>
                @endforeach
              </select>
            </label>
            <label>Kenyaringan Dasar <input type="number" step="0.1" name="ai_preamp_db" value="{{ $song->ai_preamp_db ?? 0 }}" min="-6" max="3" required></label>
            <label>Kecepatan Nada <input type="number" step="0.01" name="ai_pitch_rate" value="{{ $song->ai_pitch_rate ?? 1.0 }}" min="0.85" max="1.15" required></label>
          </div>
          @php
            $bands = $song->ai_eq_bands ?? [0,0,0,0,0,0,0,0,0,0];
            $bandLabels = ['Bass Dalam','Bass','Hangat','Vokal Rendah','Vokal','Jelas','Detail','Lembut','Desir','Udara'];
          @endphp
          <div class="eq-sliders" style="margin-bottom:10px">
            @foreach($bandLabels as $idx => $lbl)
              <div class="eq-band">
                <b>{{ $bands[$idx] ?? 0 }}</b>
                <input type="range" name="bands[{{ $idx }}]" min="-12" max="12" step="0.5" value="{{ $bands[$idx] ?? 0 }}" oninput="this.previousElementSibling.textContent=this.value">
                <small>{{ $lbl }}</small>
              </div>
            @endforeach
          </div>
          <label style="display:block;margin-bottom:10px;font-weight:600;font-size:13px">Catatan Untuk Pendengar
            <input name="ai_mastering_notes" value="{{ $song->ai_mastering_notes }}" placeholder="Mis. Suara dibuat lebih lembut dan nyaman di telinga">
          </label>
          <div class="eq-foot">
            <label class="inline" style="font-size:13px"><input type="checkbox" name="render_ffmpeg" value="1"> Proses file audio agar suara halus &amp; merata permanen</label>
            <button class="btn sm">💾 Simpan &amp; Kirim ke Pemutar</button>
          </div>
        </form>
      </details>
    </div>
  @endforeach
</div>
@endsection
