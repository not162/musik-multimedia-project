<?php

namespace Database\Seeders;

use App\Models\{AiProviderSetting, Album, ApiEndpointRule, Artist, Genre, Playlist, RecordLabel, SeoMetadata, Song, User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{File, Storage};

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Default Admin & User Accounts
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

        // 3. Record Labels (Daftar Label Musik Baru)
        $labelKampus = RecordLabel::firstOrCreate(
            ['slug' => 'musikku-studio-records'],
            [
                'name'         => 'Musikku Studio Records',
                'country'      => 'Indonesia',
                'founded_year' => 2026,
                'description'  => 'Label produksi audio kampus yang merilis musik fokus belajar, lofi, dan eksperimen akustik digital.',
                'is_featured'  => true,
            ]
        );
        $labelIndie = RecordLabel::firstOrCreate(
            ['slug' => 'gelombang-nusantara'],
            [
                'name'         => 'Gelombang Nusantara',
                'country'      => 'Indonesia',
                'founded_year' => 2025,
                'description'  => 'Label musik independen untuk musisi elektronik, ambient, dan indie modern Asia Tenggara.',
                'is_featured'  => true,
            ]
        );
        $labelSenja = RecordLabel::firstOrCreate(
            ['slug' => 'senja-akustik-label'],
            [
                'name'         => 'Senja Akustik Label',
                'country'      => 'Indonesia',
                'founded_year' => 2026,
                'description'  => 'Label rekaman hangat untuk karya akustik, pop santai, dan komposisi instrumental.',
                'is_featured'  => true,
            ]
        );

        // 4. Genres
        $genres = collect(['Lofi', 'Ambient', 'Elektronik', 'Akustik', 'Pop', 'Indie'])
            ->mapWithKeys(fn ($name) => [$name => Genre::firstOrCreate(['name' => $name])]);

        // 5. Artists
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

        // 6. Albums
        $albBelajar = Album::firstOrCreate(
            ['artist_id' => $studio->id, 'title' => 'Sesi Fokus 2026'],
            ['release_year' => 2026, 'record_label_id' => $labelKampus->id]
        );
        $albMalam = Album::firstOrCreate(
            ['artist_id' => $senja->id, 'title' => 'Lampu Perpustakaan'],
            ['release_year' => 2026, 'record_label_id' => $labelSenja->id]
        );
        $albKode = Album::firstOrCreate(
            ['artist_id' => $synth->id, 'title' => 'Algoritma Malam'],
            ['release_year' => 2026, 'record_label_id' => $labelIndie->id]
        );

        // 7. Songs (with Record Labels & AI Fine-Tuning profiles)
        $catalog = [
            [
                'title' => 'Fokus Pagi', 'artist' => $studio, 'album' => $albBelajar, 'genre' => 'Lofi', 'label' => $labelKampus,
                'file' => 'songs/fokus-pagi.mp3', 'dur' => 52, 'plays' => 128,
                'lyrics' => "Pagi datang perlahan\nSecangkir kopi di meja kayu\nBaris kode mulai tertata\nTenang bersama irama lofi.",
                'ai' => [
                    'is_ai_finetuned' => true, 'ai_mood' => 'Fokus Tenang', 'ai_tempo_bpm' => 84, 'ai_direction' => 'focus_study',
                    'ai_eq_bands' => [-0.5, 0.5, 1.5, 1.0, 1.5, 1.0, -1.0, -2.0, -1.5, -1.0], 'ai_preamp_db' => -0.6,
                    'ai_mastering_notes' => 'Dihaluskan pada 2–8 kHz agar nyaman didengar lama saat belajar.',
                ],
            ],
            [
                'title' => 'Malam di Perpustakaan', 'artist' => $senja, 'album' => $albMalam, 'genre' => 'Ambient', 'label' => $labelSenja,
                'file' => 'songs/malam-di-perpustakaan.mp3', 'dur' => 51, 'plays' => 94,
                'lyrics' => "Lampu hangat di sudut ruang baca\nLembaran buku berbisik pelan\nHujan tipis di luar jendela.",
                'ai' => [
                    'is_ai_finetuned' => true, 'ai_mood' => 'Hangat & Dalam', 'ai_tempo_bpm' => 76, 'ai_direction' => 'warm_bass',
                    'ai_eq_bands' => [3.5, 3.0, 1.5, 0.5, 0.0, 0.5, -0.5, -1.0, -0.5, 0.5], 'ai_preamp_db' => -1.4,
                    'ai_mastering_notes' => 'Penguatan low-end hangat dengan kejernihan ruang akustik.',
                ],
            ],
            [
                'title' => 'Kode Mengalir', 'artist' => $synth, 'album' => $albKode, 'genre' => 'Elektronik', 'label' => $labelIndie,
                'file' => 'songs/kode-mengalir.mp3', 'dur' => 54, 'plays' => 152,
                'lyrics' => "Sintesis nada mengalir cepat\nSatu commit lagi sebelum tengah malam\nLogika menyatu dalam ritme.",
                'ai' => [
                    'is_ai_finetuned' => true, 'ai_mood' => 'Energik Dinamis', 'ai_tempo_bpm' => 124, 'ai_direction' => 'punchy',
                    'ai_eq_bands' => [5.0, 4.0, 1.5, -1.0, -1.5, 0.5, 1.5, 3.0, 3.5, 4.0], 'ai_preamp_db' => -2.0,
                    'ai_mastering_notes' => 'Sub-bass punchy & transient tinggi jernih untuk ritme elektronik.',
                ],
            ],
            [
                'title' => 'Jeda Sore', 'artist' => $senja, 'album' => $albMalam, 'genre' => 'Akustik', 'label' => $labelSenja,
                'file' => 'songs/jeda-sore.mp3', 'dur' => 46, 'plays' => 76,
                'lyrics' => "Berhenti sejenak dari layar\nMenatap langit jingga di kampus\nNapas lega menyambut senja.",
                'ai' => [],
            ],
            [
                'title' => 'Semangat Praktikum', 'artist' => $studio, 'album' => $albBelajar, 'genre' => 'Pop', 'label' => $labelKampus,
                'file' => 'songs/semangat-praktikum.mp3', 'dur' => 50, 'plays' => 110,
                'lyrics' => "Langkah pasti menuju laboratorium\nTim kompak siap berinovasi\nNada ceria penyemangat hari.",
                'ai' => [],
            ],
        ];

        $seededSongs = [];
        foreach ($catalog as $c) {
            $song = Song::firstOrCreate(
                ['title' => $c['title'], 'artist_id' => $c['artist']->id],
                array_merge([
                    'album_id'        => $c['album']->id,
                    'genre_id'        => $genres[$c['genre']]->id,
                    'record_label_id' => $c['label']->id,
                    'file_path'       => $c['file'],
                    'duration'        => $c['dur'],
                    'play_count'      => $c['plays'],
                    'lyrics'          => $c['lyrics'],
                ], $c['ai'])
            );
            if (! $song->record_label_id) {
                $song->update(array_merge(['record_label_id' => $c['label']->id], $c['ai']));
            }
            $seededSongs[] = $song;
        }

        // 8. Public & User Playlists
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

        // 9. AI Provider Default Settings & Connected API Management Rules
        AiProviderSetting::firstOrCreate([], [
            'provider'               => 'gemini',
            'model'                  => 'gemini-2.5-flash',
            'auto_finetune_realtime' => true,
            'is_active'              => true,
        ]);

        $rules = [
            ['group_name' => 'public', 'endpoint_key' => 'public.songs',   'uri_pattern' => '/api/v1/songs',              'method' => 'GET',       'rate_limit_per_min' => 60,  'description' => 'Katalog lagu publik, label & AI profile'],
            ['group_name' => 'public', 'endpoint_key' => 'public.labels',  'uri_pattern' => '/api/v1/labels',             'method' => 'GET',       'rate_limit_per_min' => 60,  'description' => 'Daftar label musik baru untuk halaman publik'],
            ['group_name' => 'user',   'endpoint_key' => 'user.favorites', 'uri_pattern' => '/api/v1/favorites',          'method' => 'GET/POST',  'rate_limit_per_min' => 60,  'description' => 'Koleksi favorit pengguna terdaftar'],
            ['group_name' => 'user',   'endpoint_key' => 'user.playlists', 'uri_pattern' => '/api/v1/playlists',          'method' => 'CRUD',      'rate_limit_per_min' => 60,  'description' => 'Playlist pribadi & publik pengguna'],
            ['group_name' => 'admin',  'endpoint_key' => 'admin.ai',       'uri_pattern' => '/api/v1/admin/ai/finetune',  'method' => 'POST',      'rate_limit_per_min' => 30,  'description' => 'AI Music Fine-Tuning & DSP broadcast ke user/publik'],
        ];
        foreach ($rules as $r) {
            ApiEndpointRule::firstOrCreate(['endpoint_key' => $r['endpoint_key']], $r);
        }

        // 10. Default SEO Metadata
        $seoDefaults = [
            ['route_name' => 'home',         'title' => 'Musikku — Pemutar Musik Interaktif & PWA', 'description' => 'Dengarkan musik favorit gratis, nikmati equalizer, playlist, label musik baru, dan live streaming real-time.'],
            ['route_name' => 'songs.index',  'title' => 'Jelajah Lagu — Musikku',                  'description' => 'Jelajahi koleksi lagu lengkap berdasarkan genre, label musik, dan popularitas di Musikku.'],
            ['route_name' => 'labels.index', 'title' => 'Daftar Label Musik Baru — Musikku',       'description' => 'Temukan daftar label musik terbaru dan rilisan rekaman resmi di Musikku.'],
            ['route_name' => 'charts',       'title' => 'Chart Lagu Populer — Musikku',            'description' => 'Tangga lagu terpopuler mingguan dan sepanjang masa di Musikku.'],
            ['route_name' => 'search',       'title' => 'Pencarian Musik — Musikku',               'description' => 'Cari lagu, artis, album, dan label musik secara instan di Musikku.'],
        ];
        foreach ($seoDefaults as $seo) {
            SeoMetadata::firstOrCreate(['route_name' => $seo['route_name']], $seo);
        }
    }
}
