<?php

use App\Http\Controllers\{
    AccountController,
    AlbumController,
    ArtistController,
    AuthController,
    FavoriteController,
    GenreController,
    HistoryController,
    HomeController,
    LabelController,
    PlaylistController,
    SeoController,
    SongController,
    StudioController,
};
use App\Http\Controllers\Admin;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

// ---- Publik (Guest & Semua Pengunjung) ----
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/cari', [HomeController::class, 'search'])->name('search');
Route::get('/chart', [HomeController::class, 'charts'])->name('charts');

Route::get('/lagu', [SongController::class, 'index'])->name('songs.index');
Route::get('/lagu/{song}', [SongController::class, 'show'])->name('songs.show');
Route::get('/lagu/{song}/stream', [SongController::class, 'stream'])->name('songs.stream');
Route::get('/lagu/{song}/hires', [SongController::class, 'downloadHires'])->name('songs.hires');
Route::post('/lagu/{song}/played', [SongController::class, 'played'])->middleware('throttle:60,1')->name('songs.played');

Route::get('/artis/{artist}', [ArtistController::class, 'show'])->name('artists.show');
Route::get('/album/{album}', [AlbumController::class, 'show'])->name('albums.show');
Route::get('/genre/{genre}', [GenreController::class, 'show'])->name('genres.show');
Route::get('/label', [LabelController::class, 'index'])->name('labels.index');
Route::get('/label/{label:slug}', [LabelController::class, 'show'])->name('labels.show');
Route::get('/p/{playlist}', [PlaylistController::class, 'publicShow'])->name('playlists.public');
Route::get('/profil-logo/{user}', [AccountController::class, 'avatar'])->name('account.avatar');

// ---- SEO & PWA ----
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/offline', [SeoController::class, 'offline'])->name('offline');
Route::get('/health', [SeoController::class, 'health'])->name('health');

// ---- Tamu ----
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login'])->middleware('throttle:8,1');
    Route::get('/daftar', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/daftar', [AuthController::class, 'register'])->middleware('throttle:8,1');
});

// ---- Pengguna Login (Role User & Admin) ----
Route::middleware('auth')->group(function () {
    Route::post('/keluar', [AuthController::class, 'logout'])->name('logout');

    // Profil & Preferensi (Token API khusus Admin)
    Route::get('/akun', [AccountController::class, 'index'])->name('account.index');
    Route::put('/akun', [AccountController::class, 'updateProfile'])->name('account.update');
    Route::put('/akun/sandi', [AccountController::class, 'changePassword'])->name('account.password');
    Route::put('/akun/preferensi', [AccountController::class, 'updatePreferences'])->name('account.preferences');
    Route::post('/akun/tokens', [AccountController::class, 'createToken'])->middleware(EnsureAdmin::class)->name('account.tokens.create');
    Route::delete('/akun/tokens/{tokenId}', [AccountController::class, 'revokeToken'])->middleware(EnsureAdmin::class)->name('account.tokens.revoke');

    // Fitur User Role: Studio Musik (terpisah dari API & AI Admin, tersinkron real-time)
    Route::get('/studio-musik', [StudioController::class, 'index'])->name('studio.index');
    Route::post('/studio-musik/preferensi', [StudioController::class, 'savePreferences'])->name('studio.preferences');
    Route::post('/studio-musik/requests', [StudioController::class, 'storeRequest'])->name('studio.requests.store');

    Route::get('/favorit', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favorit/{song}', [FavoriteController::class, 'toggle'])->name('favorites.toggle');

    Route::get('/riwayat', [HistoryController::class, 'index'])->name('history.index');
    Route::delete('/riwayat', [HistoryController::class, 'clear'])->name('history.clear');

    Route::get('/playlists', [PlaylistController::class, 'index'])->name('playlists.index');
    Route::post('/playlists', [PlaylistController::class, 'store'])->name('playlists.store');
    Route::get('/playlists/{playlist}', [PlaylistController::class, 'show'])->name('playlists.show');
    Route::put('/playlists/{playlist}', [PlaylistController::class, 'update'])->name('playlists.update');
    Route::delete('/playlists/{playlist}', [PlaylistController::class, 'destroy'])->name('playlists.destroy');
    Route::post('/playlists/{playlist}/songs/{song}', [PlaylistController::class, 'addSong'])->name('playlists.songs.add');
    Route::delete('/playlists/{playlist}/songs/{song}', [PlaylistController::class, 'removeSong'])->name('playlists.songs.remove');
});

// ---- Admin Role ONLY ----
Route::middleware(['auth', EnsureAdmin::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::resource('songs', Admin\SongController::class)->except('show');
    Route::resource('artists', Admin\ArtistController::class)->except('show');
    Route::resource('albums', Admin\AlbumController::class)->except('show');
    Route::resource('labels', Admin\LabelController::class)->except('show');

    Route::get('genres', [Admin\GenreController::class, 'index'])->name('genres.index');
    Route::post('genres', [Admin\GenreController::class, 'store'])->name('genres.store');
    Route::delete('genres/{genre}', [Admin\GenreController::class, 'destroy'])->name('genres.destroy');

    // AI Audio Fine-Tuning, Alat Bantu Latih & API Management Studio (Admin Only)
    Route::get('ai-studio', [Admin\AiStudioController::class, 'index'])->name('ai.index');
    Route::post('ai-studio/provider', [Admin\AiStudioController::class, 'saveProvider'])->name('ai.provider');
    Route::post('ai-studio/provider/test', [Admin\AiStudioController::class, 'testProvider'])->name('ai.test');
    Route::post('ai-studio/labelling/auto', [Admin\AiStudioController::class, 'autoLabelAll'])->name('ai.autoLabel');
    Route::post('ai-studio/songs/{song}/label', [Admin\AiStudioController::class, 'labelSong'])->name('ai.label');
    Route::post('ai-studio/train', [Admin\AiStudioController::class, 'trainModel'])->name('ai.train');
    Route::post('ai-studio/validate', [Admin\AiStudioController::class, 'validateModel'])->name('ai.validate');
    Route::post('ai-studio/songs/{song}/polish', [Admin\AiStudioController::class, 'polishSong'])->name('ai.polish');
    Route::post('ai-studio/polish-all', [Admin\AiStudioController::class, 'polishAll'])->name('ai.polishAll');
    Route::post('ai-studio/songs/{song}/finetune', [Admin\AiStudioController::class, 'finetuneSong'])->name('ai.finetune');
    Route::put('ai-studio/songs/{song}/manual', [Admin\AiStudioController::class, 'manualFinetune'])->name('ai.manual');
    Route::post('ai-studio/requests/{studioRequest}/process', [Admin\AiStudioController::class, 'processStudioRequest'])->name('ai.requestProcess');
    Route::patch('api-management/rules/{rule}', [Admin\AiStudioController::class, 'updateRule'])->name('api.ruleUpdate');

    Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/role', [Admin\UserController::class, 'toggleRole'])->name('users.role');
    Route::patch('users/{user}/status', [Admin\UserController::class, 'toggleStatus'])->name('users.status');
    Route::patch('users/{user}/unlock', [Admin\UserController::class, 'unlock'])->name('users.unlock');
    Route::delete('users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');

    // Phase 9: Error Tracking & Logs Alert (Admin Only)
    Route::get('logs', [Admin\OperationsController::class, 'logs'])->name('logs.index');
    Route::patch('logs/{errorLog}/resolve', [Admin\OperationsController::class, 'resolveLog'])->name('logs.resolve');
    Route::post('logs/resolve-all', [Admin\OperationsController::class, 'resolveAllLogs'])->name('logs.resolveAll');
    Route::delete('logs/{errorLog}', [Admin\OperationsController::class, 'deleteLog'])->name('logs.destroy');
    Route::post('logs/test-alert', [Admin\OperationsController::class, 'testAlert'])->name('logs.testAlert');

    // Phase 4: Security, Audit Trail & LRS Local
    Route::get('security', [Admin\OperationsController::class, 'security'])->name('security.index');
    Route::get('security/lrs-export', [Admin\OperationsController::class, 'exportLrs'])->name('security.lrsExport');

    // Phase 2, 6, 7, 8: Database, Storage, Cache, Load Balancing, SEO & Recovery
    Route::get('system', [Admin\OperationsController::class, 'system'])->name('system.index');
    Route::post('system/backup', [Admin\OperationsController::class, 'createBackup'])->name('system.backup');
    Route::get('system/backup/{backupLog}/download', [Admin\OperationsController::class, 'downloadBackup'])->name('system.backupDownload');
    Route::post('system/cache-clear', [Admin\OperationsController::class, 'clearCache'])->name('system.cacheClear');
    Route::post('system/seo', [Admin\OperationsController::class, 'saveSeo'])->name('system.seoSave');
    Route::delete('system/seo/{seoMetadata}', [Admin\OperationsController::class, 'deleteSeo'])->name('system.seoDelete');
});
