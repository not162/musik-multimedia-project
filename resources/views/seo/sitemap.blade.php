{!! '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>{{ url('/') }}</loc><changefreq>daily</changefreq><priority>1.0</priority></url>
  <url><loc>{{ route('songs.index') }}</loc><changefreq>daily</changefreq><priority>0.9</priority></url>
  <url><loc>{{ route('charts') }}</loc><changefreq>daily</changefreq><priority>0.9</priority></url>
  @foreach($songs as $s)
  <url><loc>{{ route('songs.show', $s) }}</loc><lastmod>{{ $s->updated_at->toAtomString() }}</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>
  @endforeach
  @foreach($artists as $a)
  <url><loc>{{ route('artists.show', $a) }}</loc><lastmod>{{ $a->updated_at->toAtomString() }}</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>
  @endforeach
  @foreach($albums as $al)
  <url><loc>{{ route('albums.show', $al) }}</loc><lastmod>{{ $al->updated_at->toAtomString() }}</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>
  @endforeach
  @foreach($genres as $g)
  <url><loc>{{ route('genres.show', $g) }}</loc><changefreq>weekly</changefreq><priority>0.6</priority></url>
  @endforeach
  @foreach($playlists as $p)
  <url><loc>{{ route('playlists.public', $p) }}</loc><lastmod>{{ $p->updated_at->toAtomString() }}</lastmod><changefreq>weekly</changefreq><priority>0.6</priority></url>
  @endforeach
</urlset>
