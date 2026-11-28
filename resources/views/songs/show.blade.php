@extends('layouts.musikku')
@section('title', $song->title)
@section('content')
@php $fmt = strtolower($song->media_format ?: pathinfo((string) $song->file_path, PATHINFO_EXTENSION) ?: 'mp3'); @endphp
<div class="head">
  @include('partials.cover', ['m' => $song, 'class' => 'lg'])
  <div>
    <div class="tag">{{ $fmt === 'mp4' ? 'Musik Video / MP4' : 'Lagu MP3' }}</div>
    <h1>{{ $song->title }}</h1>
    <div><a href="{{ route('artists.show', $song->artist) }}"><b>{{ $song->artist->name }}</b></a>
      @if($song->album) · <a href="{{ route('albums.show', $song->album) }}">{{ $song->album->title }}</a>@endif
      @if($song->genre) · <a href="{{ route('genres.show', $song->genre) }}">{{ $song->genre->name }}</a>@endif
      @if($song->recordLabel) · <a href="{{ route('labels.show', $song->recordLabel) }}">🏷️ {{ $song->recordLabel->name }}</a>@endif
      · {{ $song->duration_label }} · {{ number_format($song->play_count) }}× diputar</div>
    @if($song->is_voice_smoothed || $song->is_ai_finetuned || $song->target_sample_rate_khz || $song->voice_bitrate_kbps)
      <div style="margin-top:8px">
        @if($song->is_voice_smoothed)
          <span class="badge ok">✨ Suara Halus &amp; Jernih · Kejernihan {{ $song->voice_clarity_score }}/100</span>
        @elseif($song->is_ai_finetuned)
          <span class="badge ok">✨ Studio Musik{{ $song->ai_mood ? ' · '.$song->ai_mood : '' }}</span>
        @endif
        @if($song->target_sample_rate_khz)
          <span class="badge info">🔊 Master {{ $song->target_sample_rate_khz }} kHz</span>
        @endif
        @if($song->voice_bitrate_kbps)
          <span class="badge info">🎙️ Pita Suara {{ $song->voice_bitrate_kbps }} kbps</span>
        @endif
        @if($song->ai_mastering_notes)<small class="mut" style="display:block;margin-top:4px">{{ $song->ai_mastering_notes }}</small>@endif
      </div>
    @endif
  </div>
</div>

@if($fmt === 'mp4')
  <div class="panel" style="padding:12px">
    <b>🎬 Pemutar Video &amp; Audio MP4</b>
    <video controls preload="metadata" style="width:100%;max-height:380px;border-radius:12px;margin-top:8px;background:#0f172a" src="{{ route('songs.stream', $song) }}"></video>
  </div>
@endif

<div class="song-list" id="one">@include('partials.song-row', ['song' => $song])</div>
<div class="actions">
  <button class="btn js-play-all" data-scope="#one">▶ Putar</button>
  @if($song->hires_file_path)
    <a class="btn ghost" href="{{ route('songs.hires', $song) }}" data-turbo="false">⬇ Unduh Master {{ $song->target_sample_rate_khz }} kHz (.WAV)</a>
  @endif
</div>

<h2>Lirik</h2>
@if($song->lyrics)<div class="lyrics">{{ $song->lyrics }}</div>@else<p class="mut">Lirik belum tersedia.</p>@endif

@if($related->count())
  <h2>Lagu serupa</h2>
  <div class="song-list" id="rel">@foreach($related as $s)@include('partials.song-row', ['song' => $s])@endforeach</div>
@endif
@endsection
