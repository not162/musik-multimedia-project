@extends('layouts.musikku')
@section('title', 'Error Tracking & Logs Alert')
@section('content')
<h1>🚨 Error Tracking & Real-time Logs Alert <span class="badge info">Admin Only</span></h1>
@include('admin._nav')

<div class="stats">
  <div class="stat"><small>Belum Diselesaikan</small><b style="color:var(--danger)">{{ $summary['unresolved'] }}</b></div>
  <div class="stat"><small>24 Jam Terakhir</small><b>{{ $summary['last_24h'] }}</b></div>
  <div class="stat"><small>Critical / Error Open</small><b>{{ $summary['critical'] }}</b></div>
  <div class="stat"><small>Total Log Tercatat</small><b>{{ $summary['total'] }}</b></div>
</div>

<div class="actions" style="justify-content:space-between">
  <form method="GET" class="actions" style="margin:0">
    <select name="level" style="width:auto">
      <option value="">Semua Level</option>
      @foreach(['critical','error','warning','alert','emergency'] as $lvl)
        <option value="{{ $lvl }}" @selected(request('level') === $lvl)>{{ strtoupper($lvl) }}</option>
      @endforeach
    </select>
    <select name="status" style="width:auto">
      <option value="">Semua Status</option>
      <option value="open" @selected(request('status') === 'open')>Open (Belum selesai)</option>
      <option value="resolved" @selected(request('status') === 'resolved')>Resolved</option>
    </select>
    <button class="btn ghost sm">Filter</button>
  </form>

  <div class="actions" style="margin:0">
    <form method="POST" action="{{ route('admin.logs.testAlert') }}">@csrf
      <button class="btn sm">⚡ Simulasi Alert (Reverb/Pusher)</button>
    </form>
    @if($summary['unresolved'] > 0)
      <form method="POST" action="{{ route('admin.logs.resolveAll') }}">@csrf
        <button class="btn ghost sm">✓ Selesaikan Semua</button>
      </form>
    @endif
  </div>
</div>

<table class="table">
  <thead>
    <tr><th>ID</th><th>Level</th><th>Pesan Error</th><th>Channel / URL</th><th>Waktu</th><th>Status</th><th>Aksi</th></tr>
  </thead>
  <tbody id="errorLogsBody">
    @forelse($logs as $l)
      <tr>
        <td>#{{ $l->id }}</td>
        <td><span class="badge {{ in_array($l->level, ['critical','emergency','alert','error']) ? 'danger' : 'warn' }}">{{ strtoupper($l->level) }}</span></td>
        <td>
          <b>{{ $l->message }}</b>
          @if($l->stack_trace)
            <details style="margin-top:4px"><summary class="mut" style="cursor:pointer;font-size:12px">Lihat Stack Trace</summary>
              <pre style="white-space:pre-wrap;font-size:11px;background:var(--panel);padding:8px;border-radius:8px;max-height:180px;overflow:auto">{{ $l->stack_trace }}</pre>
            </details>
          @endif
        </td>
        <td><small>{{ $l->channel }}<br>{{ $l->method }} {{ $l->url }}</small></td>
        <td><small>{{ $l->created_at?->format('d/m H:i:s') }}</small></td>
        <td>
          @if($l->is_resolved)
            <span class="badge ok">Resolved</span>
          @else
            <span class="badge warn">Open</span>
          @endif
        </td>
        <td class="act">
          @unless($l->is_resolved)
            <form method="POST" action="{{ route('admin.logs.resolve', $l) }}">@csrf @method('PATCH')<button class="btn ghost sm">✓ Resolve</button></form>
          @endunless
          <form method="POST" action="{{ route('admin.logs.destroy', $l) }}" data-turbo-confirm="Hapus log ini?">@csrf @method('DELETE')<button class="btn danger sm">Hapus</button></form>
        </td>
      </tr>
    @empty
      <tr><td colspan="7" class="mut">Belum ada error tercatat — sistem berjalan normal.</td></tr>
    @endforelse
  </tbody>
</table>
{{ $logs->links('pagination::default') }}
@endsection
