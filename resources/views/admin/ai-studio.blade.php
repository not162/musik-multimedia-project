@extends('layouts.musikku')
@section('title', 'AI Audio Studio & API Management')
@section('content')
<h1>🤖 AI Fine-Tuning Musik, Pengolahan Audio & API Management <span class="badge info">Khusus Admin</span></h1>
@include('admin._nav')
@include('partials.errors')

{{-- 1. API AI Key Configuration --}}
<div class="two">
  <div class="panel">
    <h3>🔑 Konfigurasi API AI Key (Terenkripsi AES-256)</h3>
    <p class="mut" style="font-size:13px">API key dienkripsi menggunakan <code class="inline">APP_KEY</code> sebelum disimpan ke MySQL dan tidak pernah ditampilkan ulang secara terbuka.</p>
    <div style="margin-bottom:10px">
      @if($setting->hasApiKey())
        <span class="badge ok">✓ Key Terpasang ({{ $setting->key_hint ?? 'ENV' }})</span>
      @else
        <span class="badge warn">○ Belum Ada API AI Key (Mode DSP Lokal aktif)</span>
      @endif
      @if($setting->last_status)
        <span class="badge">{{ $setting->last_status }}</span>
      @endif
    </div>

    <form method="POST" action="{{ route('admin.ai.provider') }}" class="form">@csrf
      <div class="row2">
        <label>Provider AI
          <select name="provider">
            <option value="gemini" @selected($setting->provider === 'gemini')>Google Gemini</option>
            <option value="openai" @selected($setting->provider === 'openai')>OpenAI</option>
            <option value="custom" @selected($setting->provider === 'custom')>Custom (OpenAI-Compatible)</option>
          </select>
        </label>
        <label>Model AI <input name="model" value="{{ $setting->model }}" required></label>
      </div>
      <label>Base URL (opsional untuk custom/proxy) <input name="base_url" value="{{ $setting->base_url }}" placeholder="https://api.openai.com/v1"></label>
      <label>API AI Key Baru (kosongkan jika tidak ingin mengganti)
        <input type="password" name="api_key" placeholder="Tempel API key di sini…" autocomplete="new-password">
      </label>
      <label class="inline"><input type="checkbox" name="auto_finetune_realtime" value="1" @checked($setting->auto_finetune_realtime)> Otomatis Fine-Tune & Siarkan Real-Time saat lagu baru diunggah</label>
      <label class="inline"><input type="checkbox" name="is_active" value="1" @checked($setting->is_active)> Aktifkan Mesin AI Eksternal</label>
      <div class="actions">
        <button class="btn sm">💾 Simpan API AI</button>
      </div>
    </form>
    <form method="POST" action="{{ route('admin.ai.test') }}" style="margin-top:8px">@csrf
      <button class="btn ghost sm">⚡ Uji Koneksi API AI</button>
    </form>
  </div>

  {{-- 2. Connected API Management (Admin <-> User <-> Public) --}}
  <div class="panel">
    <h3>🔌 API Management & Koneksi Real-Time (Admin ↔ User ↔ Publik)</h3>
    <p class="mut" style="font-size:13px">Kendalikan endpoint aktif dan batas laju real-time. Perubahan langsung berlaku ke API publik, user, dan admin.</p>
    <table class="table">
      <tr><th>Endpoint / Grup</th><th>Limit/mnt</th><th>Aktif</th><th>Siaran</th><th></th></tr>
      @foreach($rules as $rule)
        <tr>
          <td>
            <span class="badge {{ $rule->group_name === 'admin' ? 'info' : ($rule->group_name === 'user' ? 'ok' : '') }}">{{ strtoupper($rule->group_name) }}</span>
            <br><code class="inline">{{ $rule->uri_pattern }}</code>
          </td>
          <form method="POST" action="{{ route('admin.api.ruleUpdate', $rule) }}">@csrf @method('PATCH')
            <td><input type="number" name="rate_limit_per_min" value="{{ $rule->rate_limit_per_min }}" min="1" max="5000" style="width:72px;padding:5px"></td>
            <td><input type="checkbox" name="is_enabled" value="1" @checked($rule->is_enabled)></td>
            <td><input type="checkbox" name="realtime_broadcast" value="1" @checked($rule->realtime_broadcast)></td>
            <td><button class="btn ghost sm">Terapkan</button></td>
          </form>
        </tr>
      @endforeach
    </table>
    <div style="margin-top:12px">
      <b>Token API Aktif ({{ $tokens->count() }}):</b>
      <div class="chips" style="margin-top:6px">
        @forelse($tokens->take(6) as $tok)
          <span class="chip sm">{{ $tok->tokenable?->name ?? 'User' }}: {{ $tok->name }}</span>
        @empty
          <span class="mut" style="font-size:13px">Belum ada token aktif.</span>
        @endforelse
      </div>
    </div>
  </div>
</div>

{{-- 3. AI Fine-Tuning & Audio Mastering Per Song --}}
<div class="panel">
  <h3>🎚️ Fine-Tuning Musik AI & Pengolahan Audio FFmpeg (Real-Time Sync ke User & Publik)</h3>
  <p class="mut" style="font-size:13px">Setiap hasil fine-tuning langsung tersimpan di MySQL, tersedia di API <code class="inline">/api/v1/songs/{id}/ai-profile</code>, dan disiarkan ke player & halaman publik via Reverb/Pusher.</p>

  @foreach($songs as $song)
    <div class="panel" style="background:#f8fafc;margin:12px 0">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
        <div>
          <b style="font-size:16px">{{ $song->title }}</b>
          <span class="mut"> — {{ $song->artist?->name }}</span>
          @if($song->genre)<span class="badge">{{ $song->genre->name }}</span>@endif
          @if($song->recordLabel)<span class="badge info">🏷️ {{ $song->recordLabel->name }}</span>@endif
          @if($song->is_ai_finetuned)
            <span class="badge ok">✨ AI Fine-Tuned · {{ $song->ai_mood }} · {{ $song->ai_tempo_bpm }} BPM</span>
          @else
            <span class="badge warn">Belum Di-Fine-Tune</span>
          @endif
          @if($song->ai_mastering_notes)
            <div class="mut" style="font-size:12.5px;margin-top:4px">{{ $song->ai_mastering_notes }}</div>
          @endif
        </div>

        <form method="POST" action="{{ route('admin.ai.finetune', $song) }}" class="actions" style="margin:0">@csrf
          <select name="record_label_id" style="width:auto;padding:6px 10px">
            <option value="">Label: (Tetap)</option>
            @foreach($labels as $lb)
              <option value="{{ $lb->id }}" @selected($song->record_label_id == $lb->id)>{{ $lb->name }}</option>
            @endforeach
          </select>
          <select name="direction_hint" style="width:auto;padding:6px 10px">
            <option value="">Arah: Otomatis AI</option>
            <option value="balanced" @selected($song->ai_direction === 'balanced')>Seimbang Studio</option>
            <option value="warm_bass" @selected($song->ai_direction === 'warm_bass')>Hangat &amp; Bass</option>
            <option value="bright_clear" @selected($song->ai_direction === 'bright_clear')>Jernih &amp; Vokal</option>
            <option value="punchy" @selected($song->ai_direction === 'punchy')>Punchy Dinamis</option>
            <option value="focus_study" @selected($song->ai_direction === 'focus_study')>Fokus Nyaman</option>
          </select>
          <input name="target_prompt" placeholder="Instruksi AI (mis. kuatkan bass & haluskan vokal)" style="width:220px;padding:6px 10px">
          <button class="btn sm">✨ Auto Fine-Tune AI</button>
        </form>
      </div>

      <details style="margin-top:10px">
        <summary class="btn ghost sm" style="display:inline-flex">🎛️ Fine-Tuning Manual 10-Band &amp; Render FFmpeg</summary>
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
            <label>Mood / Karakter <input name="ai_mood" value="{{ $song->ai_mood ?? 'Seimbang Studio' }}" required></label>
            <label>Tempo (BPM) <input type="number" name="ai_tempo_bpm" value="{{ $song->ai_tempo_bpm ?? 100 }}" min="40" max="240" required></label>
            <label>Arah Kurva
              <select name="ai_direction">
                @foreach(['balanced','warm_bass','bright_clear','punchy','focus_study'] as $dir)
                  <option value="{{ $dir }}" @selected(($song->ai_direction ?? 'balanced') === $dir)>{{ $dir }}</option>
                @endforeach
              </select>
            </label>
            <label>Preamp (dB) <input type="number" step="0.1" name="ai_preamp_db" value="{{ $song->ai_preamp_db ?? 0 }}" min="-6" max="3" required></label>
            <label>Pitch / Rate <input type="number" step="0.01" name="ai_pitch_rate" value="{{ $song->ai_pitch_rate ?? 1.0 }}" min="0.85" max="1.15" required></label>
          </div>
          @php $bands = $song->ai_eq_bands ?? [0,0,0,0,0,0,0,0,0,0]; $hzLbl = ['32','64','125','250','500','1k','2k','4k','8k','16k']; @endphp
          <div class="eq-sliders" style="margin-bottom:10px">
            @foreach($hzLbl as $idx => $hz)
              <div class="eq-band">
                <b>{{ $bands[$idx] ?? 0 }}</b>
                <input type="range" name="bands[{{ $idx }}]" min="-12" max="12" step="0.5" value="{{ $bands[$idx] ?? 0 }}" oninput="this.previousElementSibling.textContent=this.value">
                <small>{{ $hz }}Hz</small>
              </div>
            @endforeach
          </div>
          <label style="display:block;margin-bottom:10px;font-weight:600;font-size:13px">Catatan Mastering
            <input name="ai_mastering_notes" value="{{ $song->ai_mastering_notes }}">
          </label>
          <div class="eq-foot">
            <label class="inline" style="font-size:13px"><input type="checkbox" name="render_ffmpeg" value="1"> Render ulang file audio fisik via FFmpeg (-14 LUFS Loudness Normalization)</label>
            <button class="btn sm">💾 Simpan &amp; Siarkan Real-Time</button>
          </div>
        </form>
      </details>
    </div>
  @endforeach
</div>
@endsection
