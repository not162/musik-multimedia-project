<?php

namespace App\Http\Controllers;

use App\Models\{Album, Artist, Genre, Playlist, RecordLabel, Song};
use Illuminate\Http\Response;
use Illuminate\Support\Facades\{Cache, DB};

class SeoController extends Controller
{
    /**
     * Phase 7: Dynamic XML Sitemap for Search Engine Optimization.
     */
    public function sitemap(): Response
    {
        $xml = Cache::remember('seo:sitemap:xml', 600, function () {
            $songs     = Song::select(['id', 'updated_at'])->latest('updated_at')->limit(500)->get();
            $artists   = Artist::select(['id', 'updated_at'])->get();
            $albums    = Album::select(['id', 'updated_at'])->get();
            $genres    = Genre::select(['id', 'updated_at'])->get();
            $labels    = RecordLabel::select(['id', 'slug', 'updated_at'])->get();
            $playlists = Playlist::where('is_public', true)->select(['id', 'updated_at'])->get();

            return view('seo.sitemap', compact('songs', 'artists', 'albums', 'genres', 'labels', 'playlists'))->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * PWA Offline fallback view.
     */
    public function offline()
    {
        return view('offline');
    }

    /**
     * Phase 8: Public health endpoint for load balancers.
     */
    public function health()
    {
        try {
            DB::select('SELECT 1');
            return response()->json([
                'status' => 'ok',
                'node'   => env('APP_NODE_ID', gethostname() ?: 'node-1'),
                'time'   => now()->toIso8601String(),
            ]);
        } catch (\Throwable) {
            return response()->json(['status' => 'degraded'], 503);
        }
    }
}
