<?php

namespace Database\Seeders;

use App\Models\{Album, Artist, Genre, Playlist, SeoMetadata, Song, User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{File, Storage};

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Default Admin & User Accounts (with cryptographic hashing + per-user salt)
        $adminCreds = User::hashPasswordWithSalt('Admin123!');
        $admin = User::firstOrCreate(
            ['email' => 'admin@musikku.test'],
            [
                'name'          => 'Administrator',
                'password'      => $adminCreds['password'],
                'password_salt' => $adminCreds['password_salt'],
                'role'          => 'admin',
                'is_active'     => true,
                'preferences'   => ['eq_preset' => 'normal', 'speed' => 1.0],
            ]
        );

        $userCreds = User::hashPasswordWithSalt('User1234!');
        $demoUser = User::firstOrCreate(
            ['email' => 'user@musikku.test'],
            [
                'name'          => 'Pengguna Demo',
                'password'      => $userCreds['password'],
                'password_salt' => $userCreds['password_salt'],
                'role'          => 'user',
                'is_active'     => true,
                'preferences'   => ['eq_preset' => 'bass', 'speed' => 1.0],
            ]
        );

        // 2. Copy bundled audio from public/music into storage/app/public/songs
        $disk = Storage::disk('public');
        $disk->makeDirectory('songs');
        foreach (['fokus-pagi.mp3', 'malam-di-perpustakaan.mp3', 'kode-mengalir.mp3', 'jeda-sore.mp3', 'semangat-praktikum.mp3'] as $mp3) {
            $src = public_path('music/' . $mp3);
            if (file_exists($src) && ! $disk->exists('songs/' . $mp3)) {
                $disk->put('songs/' . $mp3, File::get($src));
            }
        }

        // 3. Genres
        $genres = collect(['Lofi', 'Ambient', 'Elektronik', 'Akustik', 'Pop', 'Indie'])
            ->mapWithKeys(fn ($name) => [$name => Genre::firstOrCreate(['name' => $name])]);

        // 4. Artists
        $studio = Artist::firstOrCreate(
            ['name' => 'Studio Kampus'],
            ['bio' => 'Kolektif musik mahasiswa yang memproduksi lagu fokus belajar, lo-fi, dan akustik kampus.']
        );
        $senja = Artist::firstOrCreate(
            ['name' => 'Nada Senja'],
            ['bio' => 'Duo akustik & ambient indie untuk menemani sore hingga malam di perpustakaan.']
        );
        $synth = Artist::firstOrCreate(
            ['name' => 'Pulse Sintetik'],
            ['bio' => 'Produser elektronik bertempo ritmis untuk sesi coding dan praktikum.']
        );

        // 5. Albums
        $albBelajar = Album::firstOrCreate(
            ['artist_id' => $studio->id, 'title' => 'Sesi Fokus 2026'],
            ['release_year' => 2026]
        );
        $albMalam = Album::firstOrCreate(
            ['artist_id' => $senja->id, 'title' => 'Lampu Perpustakaan'],
            ['release_year' => 2026]
        );
        $albKode = Album::firstOrCreate(
            ['artist_id' => $synth->id, 'title' => 'Algoritma Malam'],
            ['release_year' => 2026]
        );

        // 6. Songs (all backed by real files in storage/app/public/songs)
        $catalog = [
            ['title' => 'Fokus Pagi',             'artist' => $studio, 'album' => $albBelajar, 'genre' => 'Lofi',       'file' => 'songs/fokus-pagi.mp3',             'dur' => 52, 'plays' => 128, 'lyrics' => "Pagi datang perlahan\nSecangkir kopi di meja kayu\nBaris kode mulai tertata\nTenang bersama irama lofi."],
            ['title' => 'Malam di Perpustakaan',  'artist' => $senja,  'album' => $albMalam,   'genre' => 'Ambient',    'file' => 'songs/malam-di-perpustakaan.mp3',  'dur' => 51, 'plays' => 94,  'lyrics' => "Lampu hangat di sudut ruang baca\nLembaran buku berbisik pelan\nHujan tipis di luar jendela."],
            ['title' => 'Kode Mengalir',          'artist' => $synth,  'album' => $albKode,    'genre' => 'Elektronik', 'file' => 'songs/kode-mengalir.mp3',          'dur' => 54, 'plays' => 152, 'lyrics' => "Sintesis nada mengalir cepat\nSatu commit lagi sebelum tengah malam\nLogika menyatu dalam ritme."],
            ['title' => 'Jeda Sore',              'artist' => $senja,  'album' => $albMalam,   'genre' => 'Akustik',    'file' => 'songs/jeda-sore.mp3',              'dur' => 46, 'plays' => 76,  'lyrics' => "Berhenti sejenak dari layar\nMenatap langit jingga di kampus\nNapas lega menyambut senja."],
            ['title' => 'Semangat Praktikum',     'artist' => $studio, 'album' => $albBelajar, 'genre' => 'Pop',        'file' => 'songs/semangat-praktikum.mp3',     'dur' => 50, 'plays' => 110, 'lyrics' => "Langkah pasti menuju laboratorium\nTim kompak siap berinovasi\nNada ceria penyemangat hari."],
        ];

        $seededSongs = [];
        foreach ($catalog as $c) {
            $seededSongs[] = Song::firstOrCreate(
                ['title' => $c['title'], 'artist_id' => $c['artist']->id],
                [
                    'album_id'   => $c['album']->id,
                    'genre_id'   => $genres[$c['genre']]->id,
                    'file_path'  => $c['file'],
                    'duration'   => $c['dur'],
                    'play_count' => $c['plays'],
                    'lyrics'     => $c['lyrics'],
                ]
            );
        }

        // 7. Public Featured Playlist & User Playlist
        $publicPl = Playlist::firstOrCreate(
            ['user_id' => $admin->id, 'name' => 'Teman Belajar Kampus'],
            ['is_public' => true]
        );
        if ($publicPl->songs()->count() === 0 && count($seededSongs) >= 3) {
            $publicPl->songs()->sync([
                $seededSongs[0]->id => ['position' => 1],
                $seededSongs[1]->id => ['position' => 2],
                $seededSongs[3]->id => ['position' => 3],
            ]);
        }

        $userPl = Playlist::firstOrCreate(
            ['user_id' => $demoUser->id, 'name' => 'Semangat Ngoding'],
            ['is_public' => true]
        );
        if ($userPl->songs()->count() === 0 && count($seededSongs) >= 5) {
            $userPl->songs()->sync([
                $seededSongs[2]->id => ['position' => 1],
                $seededSongs[4]->id => ['position' => 2],
            ]);
        }

        // 8. Phase 7: Default SEO Metadata
        $seoDefaults = [
            ['route_name' => 'home',        'title' => 'Musikku — Pemutar Musik Interaktif & PWA', 'description' => 'Dengarkan musik favorit gratis, nikmati equalizer, playlist, offline mode PWA, dan live streaming real-time.'],
            ['route_name' => 'songs.index', 'title' => 'Jelajah Lagu — Musikku',                  'description' => 'Jelajahi koleksi lagu lengkap berdasarkan genre dan popularitas di Musikku.'],
            ['route_name' => 'charts',      'title' => 'Chart Lagu Populer — Musikku',            'description' => 'Tangga lagu terpopuler mingguan dan sepanjang masa di Musikku.'],
            ['route_name' => 'search',      'title' => 'Pencarian Musik — Musikku',               'description' => 'Cari lagu, artis, dan album secara instan di Musikku.'],
        ];

        foreach ($seoDefaults as $seo) {
            SeoMetadata::firstOrCreate(['route_name' => $seo['route_name']], $seo);
        }
    }
}
