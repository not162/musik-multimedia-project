<?php

namespace App\Services;

use App\Events\CatalogUpdatedEvent;
use App\Support\SafeBroadcast;
use Illuminate\Support\Facades\Cache;

class CatalogSyncService
{
    public static function version(): int
    {
        return (int) Cache::get('musikku:catalog_version', 1);
    }

    public static function bump(string $action, string $type, int $id, string $title, array $extra = []): int
    {
        $ver = self::version() + 1;
        Cache::forever('musikku:catalog_version', $ver);
        Cache::put('musikku:last_catalog_event', array_merge([
            'version'    => $ver,
            'action'     => $action,
            'type'       => $type,
            'id'         => $id,
            'title'      => $title,
            'updated_at' => now()->toIso8601String(),
        ], $extra), 3600);

        Cache::forget('home:stats');
        Cache::forget('home:labels');
        Cache::forget('api:charts');
        Cache::forget('api:genres');
        Cache::forget('seo:sitemap:xml');

        SafeBroadcast::dispatch(new CatalogUpdatedEvent($action, $type, $id, $title));

        return $ver;
    }

    public static function lastEvent(): ?array
    {
        return Cache::get('musikku:last_catalog_event');
    }
}
