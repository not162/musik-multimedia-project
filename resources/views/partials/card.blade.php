<a class="card" href="{{ $href }}">
  @include('partials.cover', ['m' => $m, 'class' => ($round ?? false) ? 'round' : ''])
  <b>{{ $title }}</b><small>{{ $sub ?? '' }}</small>
</a>
