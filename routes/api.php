<?php
/**
 * Phase 1: RESTful API routes — versioned under /api/v1
 * Connected with Guest (Public), User, and Admin roles.
 */

use App\Http\Controllers\Api\V1;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\{DB, Route};

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ── Phase 8: Load Balancer / Uptime Probe ────────────────────────
    Route::get('health', function () {
        try {
            DB::select('SELECT 1');
            return response()->json([
                'status' => 'ok',
                'node'   => env('APP_NODE_ID', gethostname() ?: 'node-1'),
                'time'   => now()->toIso8601String(),
            ]);
        } catch (\Throwable) {
            return response()->json(['status' => 'error'], 503);
        }
    })->name('health');

    // ── Auth (Guest) ─────────────────────────────────────────────────
    Route::post('register', [V1\AuthController::class, 'register'])
        ->middleware('throttle:api-auth')
        ->name('register');

    Route::post('login', [V1\AuthController::class, 'login'])
        ->middleware('throttle:api-auth')
        ->name('login');

    // ── Public Catalog (cached via ETag + server cache, rate-limited) ─
    Route::middleware(['throttle:api-public', 'cache.response'])->group(function () {
        Route::get('songs', [V1\SongController::class, 'index'])->name('songs.index');
        Route::get('songs/{song}', [V1\SongController::class, 'show'])->name('songs.show');
        Route::get('artists', [V1\ArtistController::class, 'index'])->name('artists.index');
        Route::get('artists/{artist}', [V1\ArtistController::class, 'show'])->name('artists.show');
        Route::get('albums', [V1\AlbumController::class, 'index'])->name('albums.index');
        Route::get('albums/{album}', [V1\AlbumController::class, 'show'])->name('albums.show');
        Route::get('genres', [V1\GenreController::class, 'index'])->name('genres.index');
        Route::get('genres/{genre}', [V1\GenreController::class, 'show'])->name('genres.show');
        Route::get('charts', [V1\ChartController::class, 'index'])->name('charts');
        Route::get('search', [V1\SearchController::class, 'index'])->name('search');
    });

    // Public audio stream & play tracking
    Route::get('songs/{song}/stream', [V1\SongController::class, 'stream'])
        ->middleware('throttle:api-public')
        ->name('songs.stream');
    Route::post('songs/{song}/played', [V1\SongController::class, 'played'])
        ->middleware('throttle:api-public')
        ->name('songs.played');

    // ── Registered User Role (Bearer Token + active check) ───────────
    Route::middleware(['auth:sanctum', 'active.user', 'throttle:api-user'])->group(function () {

        Route::post('logout', [V1\AuthController::class, 'logout'])->name('logout');
        Route::get('me', [V1\AuthController::class, 'me'])->name('me');
        Route::put('me', [V1\AuthController::class, 'updateProfile'])->name('me.update');
        Route::put('me/password', [V1\AuthController::class, 'changePassword'])->name('me.password');
        Route::put('me/preferences', [V1\AuthController::class, 'updatePreferences'])->name('me.preferences');

        // Favorites CRUD
        Route::get('favorites', [V1\FavoriteController::class, 'index'])->name('favorites.index');
        Route::post('favorites/{song}', [V1\FavoriteController::class, 'toggle'])->name('favorites.toggle');

        // History Read + Delete
        Route::get('history', [V1\HistoryController::class, 'index'])->name('history.index');
        Route::delete('history', [V1\HistoryController::class, 'clear'])->name('history.clear');

        // Playlists full CRUD
        Route::apiResource('playlists', V1\PlaylistController::class);
        Route::post('playlists/{playlist}/songs/{song}', [V1\PlaylistController::class, 'addSong'])->name('playlists.songs.add');
        Route::delete('playlists/{playlist}/songs/{song}', [V1\PlaylistController::class, 'removeSong'])->name('playlists.songs.remove');
    });

    // ── Admin Role ONLY (Bearer Token + active check + EnsureAdmin) ──
    Route::middleware(['auth:sanctum', 'active.user', EnsureAdmin::class, 'throttle:api-admin'])
        ->prefix('admin')->name('admin.')->group(function () {

            Route::apiResource('songs', V1\Admin\SongController::class);
            Route::apiResource('artists', V1\Admin\ArtistController::class);
            Route::apiResource('albums', V1\Admin\AlbumController::class);
            Route::apiResource('genres', V1\Admin\GenreController::class);

            // Users CRUD + role/status/unlock
            Route::get('users', [V1\Admin\UserController::class, 'index'])->name('users.index');
            Route::get('users/{user}', [V1\Admin\UserController::class, 'show'])->name('users.show');
            Route::patch('users/{user}/role', [V1\Admin\UserController::class, 'toggleRole'])->name('users.role');
            Route::patch('users/{user}/status', [V1\Admin\UserController::class, 'toggleStatus'])->name('users.status');
            Route::patch('users/{user}/unlock', [V1\Admin\UserController::class, 'unlock'])->name('users.unlock');
            Route::delete('users/{user}', [V1\Admin\UserController::class, 'destroy'])->name('users.destroy');

            // Dashboard & Health
            Route::get('dashboard/stats', [V1\Admin\DashboardController::class, 'stats'])->name('dashboard.stats');
            Route::get('health', [V1\Admin\DashboardController::class, 'health'])->name('health');

            // Phase 9: Error tracking & logs alert (Admin only)
            Route::get('error-logs', [V1\Admin\ErrorLogController::class, 'index'])->name('error-logs.index');
            Route::get('error-logs/{errorLog}', [V1\Admin\ErrorLogController::class, 'show'])->name('error-logs.show');
            Route::patch('error-logs/{errorLog}/resolve', [V1\Admin\ErrorLogController::class, 'resolve'])->name('error-logs.resolve');
            Route::delete('error-logs/{errorLog}', [V1\Admin\ErrorLogController::class, 'destroy'])->name('error-logs.destroy');

            // Phase 4: Audit logs & LRS Local (Admin only)
            Route::get('audit-logs', [V1\Admin\AuditLogController::class, 'index'])->name('audit-logs.index');
            Route::get('lrs-statements', [V1\Admin\AuditLogController::class, 'lrs'])->name('lrs.index');

            // Phase 8: Backups
            Route::get('backups', [V1\Admin\BackupController::class, 'index'])->name('backups.index');
            Route::post('backups', [V1\Admin\BackupController::class, 'store'])->name('backups.store');

            // Phase 7: SEO metadata CRUD
            Route::apiResource('seo', V1\Admin\SeoController::class)->parameters(['seo' => 'seoMetadata']);
        });
});
