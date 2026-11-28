<?php
/**
 * Phase 1: RESTful API routes — versioned under /api/v1
 * Connected across Guest (Public), User, and Admin roles with dynamic API Management rules.
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

    // Live sync state for Guest Public & User auto-refresh
    Route::get('live-sync', [V1\SongController::class, 'liveSync'])
        ->middleware('throttle:api-public')
        ->name('live-sync');

    // ── Auth (Guest) ─────────────────────────────────────────────────
    Route::post('register', [V1\AuthController::class, 'register'])
        ->middleware('throttle:api-auth')
        ->name('register');

    Route::post('login', [V1\AuthController::class, 'login'])
        ->middleware('throttle:api-auth')
        ->name('login');

    // ── Public Catalog + Labels + AI DSP Profile (connected to Admin API Management) ─
    Route::middleware(['throttle:api-public', 'cache.response'])->group(function () {
        Route::get('songs', [V1\SongController::class, 'index'])
            ->middleware('api.rule:public.songs')
            ->name('songs.index');
        Route::get('songs/{song}', [V1\SongController::class, 'show'])->name('songs.show');
        Route::get('songs/{song}/ai-profile', [V1\SongController::class, 'aiProfile'])->name('songs.ai-profile');
        Route::get('labels', [V1\LabelController::class, 'index'])
            ->middleware('api.rule:public.labels')
            ->name('labels.index');
        Route::get('labels/{label}', [V1\LabelController::class, 'show'])->name('labels.show');
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
        Route::get('favorites', [V1\FavoriteController::class, 'index'])
            ->middleware('api.rule:user.favorites')
            ->name('favorites.index');
        Route::post('favorites/{song}', [V1\FavoriteController::class, 'toggle'])
            ->middleware('api.rule:user.favorites')
            ->name('favorites.toggle');

        // History Read + Delete
        Route::get('history', [V1\HistoryController::class, 'index'])->name('history.index');
        Route::delete('history', [V1\HistoryController::class, 'clear'])->name('history.clear');

        // Playlists full CRUD
        Route::apiResource('playlists', V1\PlaylistController::class)
            ->middleware('api.rule:user.playlists');
        Route::post('playlists/{playlist}/songs/{song}', [V1\PlaylistController::class, 'addSong'])->name('playlists.songs.add');
        Route::delete('playlists/{playlist}/songs/{song}', [V1\PlaylistController::class, 'removeSong'])->name('playlists.songs.remove');

        // Endpoint 2 (User Role): Studio Musik — terpisah dari Admin AI/API tapi tersambung real-time
        Route::middleware('api.rule:user.studio')->prefix('studio')->name('studio.')->group(function () {
            Route::get('/', [V1\StudioController::class, 'index'])->name('index');
            Route::put('preferences', [V1\StudioController::class, 'updatePreferences'])->name('preferences');
            Route::post('requests', [V1\StudioController::class, 'storeRequest'])->name('requests.store');
            Route::post('import', [V1\StudioController::class, 'importSong'])->name('import');
            Route::delete('songs/{song}', [V1\StudioController::class, 'destroySong'])->name('songs.destroy');
        });
    });

    // ── Admin Role ONLY (Bearer Token + active check + EnsureAdmin) ──
    Route::middleware(['auth:sanctum', 'active.user', EnsureAdmin::class, 'throttle:api-admin'])
        ->prefix('admin')->name('admin.')->group(function () {

            Route::apiResource('songs', V1\Admin\SongController::class);
            Route::apiResource('artists', V1\Admin\ArtistController::class);
            Route::apiResource('albums', V1\Admin\AlbumController::class);
            Route::apiResource('genres', V1\Admin\GenreController::class);
            Route::apiResource('labels', V1\Admin\LabelController::class);

            // AI Music Fine-Tuning, Alat Bantu Latih & API Management
            Route::get('ai/status', [V1\Admin\AiStudioController::class, 'status'])->name('ai.status');
            Route::post('ai/provider', [V1\Admin\AiStudioController::class, 'saveProvider'])->name('ai.provider');
            Route::post('ai/labelling/auto', [V1\Admin\AiStudioController::class, 'autoLabel'])->name('ai.autoLabel');
            Route::post('ai/songs/{song}/label', [V1\Admin\AiStudioController::class, 'labelSong'])->name('ai.label');
            Route::post('ai/train', [V1\Admin\AiStudioController::class, 'trainModel'])->name('ai.train');
            Route::post('ai/validate', [V1\Admin\AiStudioController::class, 'validateModel'])->name('ai.validate');
            Route::post('ai/songs/{song}/polish', [V1\Admin\AiStudioController::class, 'polishSong'])
                ->middleware('api.rule:admin.ai')
                ->name('ai.polish');
            Route::post('ai/songs/{song}/finetune', [V1\Admin\AiStudioController::class, 'finetuneSong'])
                ->middleware('api.rule:admin.ai')
                ->name('ai.finetune');
            Route::get('ai/studio-requests', [V1\Admin\AiStudioController::class, 'studioRequests'])
                ->name('ai.studioRequests');
            Route::post('ai/studio-requests/{studioRequest}/process', [V1\Admin\AiStudioController::class, 'processStudioRequest'])
                ->middleware('api.rule:admin.ai')
                ->name('ai.studioRequests.process');
            Route::patch('api-management/rules/{rule}', [V1\Admin\AiStudioController::class, 'updateRule'])->name('api-management.rules.update');

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
