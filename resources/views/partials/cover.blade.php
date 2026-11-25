<div class="cover {{ $class ?? '' }}" style="{{ $m->art_url ? 'background-image:url('.$m->art_url.')' : 'background:'.$m->gradient }}">@unless($m->art_url){{ $m->initial }}@endunless</div>
