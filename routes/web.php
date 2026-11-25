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
    PlaylistController,
    SeoController,
    SongController,
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
Route::post('/lagu/{song}/played', [SongController::class, 'played'])->middleware('throttle:60,1')->name('songs.played');

Route::get('/artis/{artist}', [ArtistController::class, 'show'])->name('artists.show');
Route::get('/album/{album}', [AlbumController::class, 'show'])->name('albums.show');
Route::get('/genre/{genre}', [GenreController::class, 'show'])->name('genres.show');
Route::get('/p/{playlist}', [PlaylistController::class, 'publicShow'])->name('playlists.public');

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

// ---- Pengguna Login (Role User & Admin — Fitur Terbuka) ----
Route::middleware('auth')->group(function () {
    Route::post('/keluar', [AuthController::class, 'logout'])->name('logout');

    // Akun, Keamanan, Preferensi Pro Player, & API Token
    Route::get('/akun', [AccountController::class, 'index'])->name('account.index');
    Route::put('/akun', [AccountController::class, 'updateProfile'])->name('account.update');
    Route::put('/akun/sandi', [AccountController::class, 'changePassword'])->name('account.password');
    Route::put('/akun/preferensi', [AccountController::class, 'updatePreferences'])->name('account.preferences');
    Route::post('/akun/tokens', [AccountController::class, 'createToken'])->name('account.tokens.create');
    Route::delete('/akun/tokens/{tokenId}', [AccountController::class, 'revokeToken'])->name('account.tokens.revoke');

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

    Route::get('genres', [Admin\GenreController::class, 'index'])->name('genres.index');
    Route::post('genres', [Admin\GenreController::class, 'store'])->name('genres.store');
    Route::delete('genres/{genre}', [Admin\GenreController::class, 'destroy'])->name('genres.destroy');

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
