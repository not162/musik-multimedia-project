<?php
/**
 * Phase 7: SEO & Structured Data Service
 */

namespace App\Services;

use App\Models\{Album, Artist, SeoMetadata, Song};
use Illuminate\Support\Facades\Cache;

class SeoService
{
    public static function resolve(?string $routeName, array $overrides = []): array
    {
        $dbMeta = null;
        if ($routeName) {
            try {
                $dbMeta = Cache::remember("seo:route:{$routeName}", 600, function () use ($routeName) {
                    return SeoMetadata::where('route_name', $routeName)->first();
                });
            } catch (\Throwable) {
                $dbMeta = null;
            }
        }

        $title = $overrides['title'] ?? $dbMeta?->title ?? 'Musikku — Pemutar Musik Interaktif';
        $desc  = $overrides['description'] ?? $dbMeta?->description ?? config('app.seo.default_description');
        $image = $overrides['og_image'] ?? $dbMeta?->og_image ?? asset('images/og-default.png');
        $type  = $overrides['og_type'] ?? 'website';
        $url   = $overrides['canonical'] ?? url()->current();

        return [
            'title'       => $title,
            'description' => $desc,
            'og_image'    => str_starts_with((string) $image, 'http') ? $image : url($image),
            'og_type'     => $type,
            'canonical'   => $url,
            'json_ld'     => $overrides['json_ld'] ?? [
                '@context' => 'https://schema.org',
                '@type'    => 'WebSite',
                'name'     => 'Musikku',
                'url'      => url('/'),
                'potentialAction' => [
                    '@type'       => 'SearchAction',
                    'target'      => url('/cari') . '?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];
    }
}
