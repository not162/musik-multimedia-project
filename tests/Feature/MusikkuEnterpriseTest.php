<?php

namespace Tests\Feature;

use App\Models\{AiProviderSetting, ApiEndpointRule, Artist, RecordLabel, Song, StudioRequest, User};
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Hash, Storage};

class MusikkuEnterpriseTest extends TestCase
{
    public function test_public_light_landing_page_labels_and_pwa_endpoints(): void
    {
        $res = $this->get('/');
        $res->assertStatus(200)
            ->assertSee('Akses Publik')
            ->assertSee('Fitur Akun Terdaftar')
            ->assertSee('Daftar Label Musik Baru')
            ->assertSee('Dengarkan Musik Lebih Nyaman')
            ->assertSee('manifest.webmanifest')
            ->assertDontSee('musikkuSplashLoader')
            ->assertDontSee('loader.js')
            ->assertDontSee('Personal API Token')
            ->assertDontSee('Mengenal Produk Musikku');

        $this->get('/label')->assertStatus(200)->assertSee('Daftar Label Musik');
        $label = RecordLabel::firstOrFail();
        $this->get('/label/' . $label->slug)->assertStatus(200)->assertSee($label->name);

        $this->get('/sitemap.xml')->assertStatus(200);
        $this->get('/offline')->assertStatus(200);
        $this->getJson('/api/v1/health')->assertStatus(200)->assertJsonPath('status', 'ok');
        $this->getJson('/api/v1/labels')->assertStatus(200);

        $song = Song::firstOrFail();
        $this->getJson("/api/v1/songs/{$song->id}/ai-profile")->assertStatus(200)->assertJsonStructure(['song_id', 'eq_bands', 'is_ai_finetuned']);
    }

    public function test_akun_hides_api_hash_salt_from_regular_user_and_shows_only_to_admin(): void
    {
        $demoUser = User::where('email', 'user@musikku.test')->firstOrFail();
        $this->actingAs($demoUser)
            ->get('/akun')
            ->assertStatus(200)
            ->assertSee('Profil Saya')
            ->assertSee('placeholder="Masukkan nama akun Anda"', false)
            ->assertSee('Nama akun:')
            ->assertSee('notifBellBtn')
            ->assertSee('notify.js')
            ->assertDontSee('Personal API Token')
            ->assertDontSee('SALT ID:')
            ->assertDontSee('HASH: BCRYPT');

        // Upload & display logo gambar akun user + notification flash banner
        $origName   = $demoUser->name;
        $origAvatar = $demoUser->avatar;
        $logoFile   = UploadedFile::fake()->image('user-logo.png', 120, 120);

        $this->actingAs($demoUser)
            ->put('/akun', [
                'name'   => 'Pengguna Demo Updated',
                'phone'  => '08123456789',
                'avatar' => $logoFile,
            ])
            ->assertRedirect()
            ->assertSessionHas('ok');

        $demoUser->refresh();
        $this->assertNotEmpty($demoUser->avatar);
        $this->assertTrue(Storage::disk('public')->exists($demoUser->avatar));
        $this->get('/profil-logo/' . $demoUser->id)->assertStatus(200);
        $this->actingAs($demoUser)->get('/akun')->assertSee('/profil-logo/' . $demoUser->id);

        // Restore demoUser name & avatar cleanly
        Storage::disk('public')->delete($demoUser->avatar);
        $demoUser->update(['name' => $origName, 'avatar' => $origAvatar]);

        // User role blocked from creating API tokens or accessing AI Studio
        $this->actingAs($demoUser)->post('/akun/tokens', ['token_name' => 'Hack'])->assertStatus(403);
        $this->actingAs($demoUser)->get('/admin/ai-studio')->assertStatus(403);
        $this->actingAs($demoUser)->get('/admin/labels')->assertStatus(403);

        // Admin role sees full Akun & API, AI Studio, and Label management
        $admin = User::where('email', 'admin@musikku.test')->firstOrFail();
        $this->actingAs($admin)
            ->get('/akun')
            ->assertStatus(200)
            ->assertSee('Personal API Token')
            ->assertSee('HASH:');

        $this->actingAs($admin)->get('/admin/ai-studio')
            ->assertStatus(200)
            ->assertSee('Konfigurasi API AI Key')
            ->assertSee('Labelling')
            ->assertSee('Train')
            ->assertSee('Validation')
            ->assertSee('Latih Suara Halus & Jernih');
        $this->actingAs($admin)->get('/admin/labels')->assertStatus(200)->assertSee('Daftar Label Musik');
    }

    public function test_role_isolation_and_adaptive_eq_preferences_api(): void
    {
        $email = 'phpunit_' . uniqid() . '@musikku.test';

        $reg = $this->postJson('/api/v1/register', [
            'name'                  => 'PHPUnit User',
            'email'                 => $email,
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);
        $reg->assertStatus(201);
        $token = $reg->json('token');

        $user = User::where('email', $email)->firstOrFail();
        $this->assertEquals('user', $user->role);
        $this->assertTrue(Hash::check('Secret123!', $user->password));
        $this->assertNotEmpty($user->password_salt);

        $this->withToken($token)->putJson('/api/v1/me/preferences', [
            'eq_preset'    => 'adaptive',
            'eq_mode'      => 'adaptive',
            'eq_direction' => 'warm_bass',
            'eq_intensity' => 80,
            'eq_bands'     => [4, 3, 2, 1, 0, 1, 0, -1, -1, 0],
        ])->assertStatus(200)->assertJsonPath('preferences.eq_direction', 'warm_bass');

        $this->withToken($token)->getJson('/api/v1/admin/error-logs')->assertStatus(403);
        $this->withToken($token)->getJson('/api/v1/admin/ai/status')->assertStatus(403);

        $user->tokens()->delete();
        $user->delete();
    }

    public function test_admin_ai_finetuning_encrypted_key_labels_and_api_management(): void
    {
        $admin = User::where('email', 'admin@musikku.test')->firstOrFail();
        $token = $admin->createToken('test-admin', ['admin', 'user'])->plainTextToken;

        // 1. Save encrypted AI & Compute key without exposing raw keys in response (preserving existing config)
        $origSetting = AiProviderSetting::first()?->only([
            'provider', 'model', 'base_url', 'compute_name', 'compute_key_encrypted',
            'compute_key_hint', 'api_key_encrypted', 'key_hint', 'auto_finetune_realtime', 'is_active',
        ]);

        $this->withToken($token)->postJson('/api/v1/admin/ai/provider', [
            'provider'               => 'nvidia',
            'model'                  => 'nvidia/riva-translate-4b-instruct-v2',
            'base_url'               => 'https://integrate.api.nvidia.com/v1',
            'compute_name'           => 'constant-aquamarine-meerkat',
            'compute_key'            => 'test_compute_secret_123456',
            'api_key'                => 'test_secret_ai_key_987654321',
            'auto_finetune_realtime' => true,
            'is_active'              => false,
        ])->assertStatus(200)
            ->assertJson(['has_key' => true])
            ->assertDontSee('test_secret_ai_key_987654321')
            ->assertDontSee('test_compute_secret_123456');

        // Keep original AI configuration intact
        if ($origSetting) {
            AiProviderSetting::query()->update($origSetting);
        }

        // 2. Run AI Fine-Tuning on first song -> verify public AI profile updates
        $song = Song::firstOrFail();
        $this->withToken($token)->postJson("/api/v1/admin/ai/songs/{$song->id}/finetune", [
            'direction_hint' => 'punchy',
            'target_prompt'  => 'Naikkan sub-bass dan kejernihan vokal',
        ])->assertStatus(200)->assertJsonPath('song.is_ai_finetuned', true);

        $this->getJson("/api/v1/songs/{$song->id}/ai-profile")
            ->assertStatus(200)
            ->assertJsonPath('is_ai_finetuned', true);

        // 3. Create & delete a Record Label via Admin API and verify public visibility
        $lblRes = $this->withToken($token)->postJson('/api/v1/admin/labels', [
            'name'        => 'Label Uji Realtime ' . time(),
            'country'     => 'Indonesia',
            'is_featured' => true,
        ])->assertStatus(201);
        $lblId = $lblRes->json('label.id');

        $this->getJson("/api/v1/labels/{$lblId}")->assertStatus(200);
        $this->withToken($token)->deleteJson("/api/v1/admin/labels/{$lblId}")->assertStatus(200);

        // 4. Verify Admin API Management rules endpoint, Error Logs & LRS
        $rule = ApiEndpointRule::firstOrFail();
        $this->withToken($token)->patchJson("/api/v1/admin/api-management/rules/{$rule->id}", [
            'rate_limit_per_min' => 60,
            'is_enabled'         => true,
        ])->assertStatus(200);

        $this->withToken($token)->getJson('/api/v1/admin/error-logs')->assertStatus(200);
        $this->withToken($token)->getJson('/api/v1/admin/lrs-statements')->assertStatus(200);
        $this->withToken($token)->getJson('/api/v1/admin/health')->assertStatus(200);

        // 5. Verify AI Training Pipeline: Labelling -> Train -> Validation -> Latih Suara Halus & Jernih
        $this->withToken($token)->postJson('/api/v1/admin/ai/labelling/auto')
            ->assertStatus(200)
            ->assertJsonStructure(['message', 'count']);

        $this->withToken($token)->postJson("/api/v1/admin/ai/songs/{$song->id}/label", [
            'preset_key'        => 'halus_jernih',
            'dataset_split'     => 'train',
            'target_clarity'    => 95,
            'target_smoothness' => 96,
        ])->assertStatus(200)->assertJsonPath('sample.label_name', 'Halus & Jernih');

        $this->withToken($token)->postJson('/api/v1/admin/ai/train', [
            'epochs'     => 60,
            'model_name' => 'Model Uji Penjernih Suara',
        ])->assertStatus(200)->assertJsonPath('run.status', 'trained');

        $this->withToken($token)->postJson('/api/v1/admin/ai/validate')
            ->assertStatus(200)
            ->assertJsonPath('run.status', 'validated')
            ->assertJsonStructure(['run' => ['val_accuracy', 'val_loss', 'clarity_gain_pct']]);

        $this->withToken($token)->postJson("/api/v1/admin/ai/songs/{$song->id}/polish", [
            'preset_key'     => 'halus_jernih',
            'noise_strength' => 'medium',
            'render_audio'   => true,
        ])->assertStatus(200)
            ->assertJsonPath('song.is_voice_smoothed', true)
            ->assertJsonPath('song.voice_preset', 'halus_jernih');

        $this->getJson("/api/v1/songs/{$song->id}/ai-profile")
            ->assertStatus(200)
            ->assertJsonPath('is_voice_smoothed', true);

        // 6. Verify MP4 upload + 100-1100 kHz master creation + Live Sync for Guest Public & User views
        $syncBefore = $this->getJson('/api/v1/live-sync')->assertStatus(200)->json('catalog_version');
        $tmpMp4 = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpunit_test_song.mp4';
        $ffmpeg = 'C:\\ProgramData\\chocolatey\\bin\\ffmpeg.exe';
        if (file_exists($ffmpeg)) {
            @exec(sprintf('"%s" -y -f lavfi -i "sine=frequency=440:duration=1" -c:a aac -b:a 128k %s 2>&1', $ffmpeg, escapeshellarg($tmpMp4)));
        }
        if (file_exists($tmpMp4)) {
            $artist = Artist::firstOrFail();
            $mp4Upload = new UploadedFile($tmpMp4, 'test-musik-baru.mp4', 'video/mp4', null, true);

            $createRes = $this->withToken($token)->post('/api/v1/admin/songs', [
                'title'                  => 'Lagu MP4 Uji 500kHz',
                'artist_id'              => $artist->id,
                'target_sample_rate_khz' => 500,
                'audio'                  => $mp4Upload,
            ], ['Accept' => 'application/json']);

            $createRes->assertStatus(201)
                ->assertJsonPath('song.media_format', 'mp4')
                ->assertJsonPath('song.target_sample_rate_khz', 500);

            $newId = $createRes->json('song.id');
            $createdSong = Song::findOrFail($newId);
            $this->assertNotEmpty($createdSong->hires_file_path);
            $this->assertTrue(Storage::disk('public')->exists($createdSong->hires_file_path));

            // Guest Public & User views auto-sync + MP4/Hi-Res routes
            $syncAfter = $this->getJson('/api/v1/live-sync')->assertStatus(200);
            $this->assertGreaterThan($syncBefore, $syncAfter->json('catalog_version'));
            $this->get('/')->assertStatus(200)->assertSee('Lagu MP4 Uji 500kHz');
            $this->get("/lagu/{$newId}")->assertStatus(200)->assertSee('500 kHz')->assertSee('video');
            $this->get("/lagu/{$newId}/stream")->assertStatus(200);
            $this->get("/lagu/{$newId}/hires")->assertStatus(200);

            $demoUser = User::where('email', 'user@musikku.test')->firstOrFail();
            $this->actingAs($demoUser)->get('/lagu')->assertStatus(200)->assertSee('Lagu MP4 Uji 500kHz');
            $this->app['auth']->forgetGuards();

            // Clean up temporary MP4 test song using admin Bearer token
            $this->withToken($token)->deleteJson("/api/v1/admin/songs/{$newId}")->assertStatus(200);
            @unlink($tmpMp4);
        }

        $admin->tokens()->where('name', 'test-admin')->delete();
    }

    public function test_two_connected_roles_user_studio_and_admin_ai_realtime(): void
    {
        $user  = User::where('email', 'user@musikku.test')->firstOrFail();
        $admin = User::where('email', 'admin@musikku.test')->firstOrFail();
        $song  = Song::firstOrFail();

        // 1. User role sees Studio Musik (web & endpoint) and NEVER sees Admin API AI config
        $this->actingAs($user)->get('/studio-musik')
            ->assertStatus(200)
            ->assertSee('Studio Musik Saya')
            ->assertDontSee('musikkuSplashLoader')
            ->assertDontSee('loader.js')
            ->assertDontSee('API AI Key')
            ->assertDontSee('NVIDIA AI')
            ->assertDontSee('Personal API Token');

        $this->actingAs($user)->get('/akun')
            ->assertStatus(200)
            ->assertSee('Studio Musik')
            ->assertDontSee('API AI')
            ->assertDontSee('Personal API Token');

        $this->actingAs($user)->get('/admin/ai-studio')->assertStatus(403);

        $userToken = $user->createToken('test-user-studio', ['user'])->plainTextToken;
        $adminToken = $admin->createToken('test-admin-studio', ['admin', 'user'])->plainTextToken;

        // 2. Endpoint User (/api/v1/studio) isolated from Admin AI endpoint
        $this->withToken($userToken)->getJson('/api/v1/admin/ai/status')->assertStatus(403);
        $this->withToken($userToken)->getJson('/api/v1/admin/ai/studio-requests')->assertStatus(403);

        $studioGet = $this->withToken($userToken)->getJson('/api/v1/studio');
        $studioGet->assertStatus(200)
            ->assertJsonPath('feature', 'studio_musik_user')
            ->assertDontSee('api_key_encrypted')
            ->assertDontSee('key_hint');

        $verBefore = $studioGet->json('catalog_version');

        // 3. User updates Studio Musik preferences & sends real-time request through Endpoint 2
        $this->withToken($userToken)->putJson('/api/v1/studio/preferences', [
            'eq_mode'      => 'auto_genre',
            'eq_direction' => 'bright_clear',
            'eq_intensity' => 85,
        ])->assertStatus(200);

        $reqRes = $this->withToken($userToken)->postJson('/api/v1/studio/requests', [
            'song_id'       => $song->id,
            'desired_style' => 'halus_jernih',
            'note'          => 'Uji permintaan Studio Musik ke Admin',
        ]);
        $reqRes->assertStatus(201)->assertJsonPath('request.status', 'pending');
        $reqId = $reqRes->json('request.id');

        // 4. Admin sees the request via Endpoint 1 (/api/v1/admin/ai/studio-requests) & processes it with AI
        $this->app['auth']->forgetGuards();
        $this->withToken($adminToken)->getJson('/api/v1/admin/ai/studio-requests')
            ->assertStatus(200)
            ->assertSee('Uji permintaan Studio Musik ke Admin');

        $this->withToken($adminToken)->postJson("/api/v1/admin/ai/studio-requests/{$reqId}/process", [
            'admin_reply' => 'Selesai dirapikan dari AI Admin untuk Studio Musik User.',
        ])->assertStatus(200)->assertJsonPath('studio_request.status', 'completed');

        // 5. User Studio endpoint immediately sees completion & incremented real-time catalog version
        $this->app['auth']->forgetGuards();
        $userAfter = $this->withToken($userToken)->getJson('/api/v1/studio')->assertStatus(200);
        $this->assertGreaterThan($verBefore, $userAfter->json('catalog_version'));
        $userAfter->assertSee('Selesai dirapikan dari AI Admin untuk Studio Musik User.');

        // 6. Clean up temporary tokens & test request row
        StudioRequest::where('id', $reqId)->delete();
        $user->tokens()->where('name', 'test-user-studio')->delete();
        $admin->tokens()->where('name', 'test-admin-studio')->delete();
    }

    public function test_user_can_import_custom_song_with_vocal_bitrate_up_to_378_kbps(): void
    {
        $user  = User::where('email', 'user@musikku.test')->firstOrFail();
        $token = $user->createToken('test-user-import', ['user'])->plainTextToken;

        $this->actingAs($user)->get('/studio-musik')
            ->assertStatus(200)
            ->assertSee('Impor Lagu Custom')
            ->assertSee('350')
            ->assertSee('378 kbps')
            ->assertDontSee('NVIDIA AI');
        $this->app['auth']->forgetGuards();

        $tmpMp3 = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpunit_custom_user_song.mp3';
        $ffmpeg = 'C:\\ProgramData\\chocolatey\\bin\\ffmpeg.exe';
        if (file_exists($ffmpeg)) {
            @exec(sprintf('"%s" -y -f lavfi -i "sine=frequency=440:duration=1" -ac 2 -c:a libmp3lame -b:a 350k %s 2>&1', $ffmpeg, escapeshellarg($tmpMp3)));
        }

        $this->assertTrue(file_exists($tmpMp3), 'FFmpeg synthetic MP3 test file was created.');

        // 1. Validation rejects bitrate below 128 kbps or above 378 kbps
        $badLow = new UploadedFile($tmpMp3, 'bad-low.mp3', 'audio/mpeg', null, true);
        $this->withToken($token)->post('/api/v1/studio/import', [
            'title'              => 'Bitrate Terlalu Rendah',
            'voice_bitrate_kbps' => 96,
            'audio'              => $badLow,
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors(['voice_bitrate_kbps']);

        $badHigh = new UploadedFile($tmpMp3, 'bad-high.mp3', 'audio/mpeg', null, true);
        $this->withToken($token)->post('/api/v1/studio/import', [
            'title'              => 'Bitrate Melebihi 378',
            'voice_bitrate_kbps' => 420,
            'audio'              => $badHigh,
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors(['voice_bitrate_kbps']);

        // 2. Valid API custom song import at 350 kbps
        $validMp3 = new UploadedFile($tmpMp3, 'custom-user-350.mp3', 'audio/mpeg', null, true);
        $res = $this->withToken($token)->post('/api/v1/studio/import', [
            'title'              => 'Lagu Custom User 350kbps',
            'artist_name'        => 'Vokal Uji Studio',
            'voice_bitrate_kbps' => 350,
            'voice_style'        => 'vokal_lembut',
            'audio'              => $validMp3,
        ], ['Accept' => 'application/json']);

        $res->assertStatus(201)
            ->assertJsonPath('bitrate_kbps', 350)
            ->assertJsonPath('song.voice_bitrate_kbps', 350)
            ->assertJsonPath('song.is_custom', true)
            ->assertJsonPath('song.is_smoothed', true)
            ->assertJsonPath('song.uploaded_by', $user->id);

        $songId = (int) $res->json('song.id');
        $song   = Song::findOrFail($songId);
        $this->assertSame($user->id, (int) $song->uploaded_by);
        $this->assertSame(350, (int) $song->voice_bitrate_kbps);
        $this->assertTrue(Storage::disk('public')->exists($song->file_path));

        // 3. Another user cannot upgrade or delete someone else's custom song
        $other = User::create([
            'name'     => 'Pengguna Lain Uji',
            'email'    => 'other-import-test-' . time() . '@musikku.test',
            'password' => Hash::make('secret123'),
            'role'     => 'user',
        ]);
        $otherToken = $other->createToken('test-other', ['user'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->postJson("/api/v1/studio/songs/{$songId}/upgrade-bitrate", ['voice_bitrate_kbps' => 378])->assertStatus(403);
        $this->withToken($otherToken)->deleteJson("/api/v1/studio/songs/{$songId}")->assertStatus(403);
        $other->tokens()->delete();
        $other->delete();

        // 4. Owner upgrades custom song from 350 kbps to 378 kbps via API & plays it cleanly
        $this->app['auth']->forgetGuards();
        $upRes = $this->withToken($token)->postJson("/api/v1/studio/songs/{$songId}/upgrade-bitrate", [
            'voice_bitrate_kbps' => 378,
        ]);
        $upRes->assertStatus(200)
            ->assertJsonPath('from_kbps', 350)
            ->assertJsonPath('bitrate_kbps', 378)
            ->assertJsonPath('song.voice_bitrate_kbps', 378);

        $upgradedSong = Song::findOrFail($songId);
        $this->assertSame(378, (int) $upgradedSong->voice_bitrate_kbps);
        $this->assertTrue(Storage::disk('public')->exists($upgradedSong->file_path));
        $this->get("/lagu/{$songId}/stream")->assertStatus(200);

        $this->app['auth']->forgetGuards();
        $this->actingAs($user)->get('/studio-musik')
            ->assertStatus(200)
            ->assertSee('Lagu Custom User 350kbps')
            ->assertSee('378 kbps');
        $this->app['auth']->forgetGuards();

        // 5. Web upload at 350 kbps then one-click upgrade to 378 kbps
        $webMp3 = new UploadedFile($tmpMp3, 'custom-user-web-350.mp3', 'audio/mpeg', null, true);
        $this->actingAs($user)->post('/studio-musik/import', [
            'title'              => 'Lagu Custom Web 350kbps',
            'voice_bitrate_kbps' => 350,
            'voice_style'        => 'halus_jernih',
            'audio'              => $webMp3,
        ])->assertRedirect()->assertSessionHas('ok');

        $webSong = Song::where('title', 'Lagu Custom Web 350kbps')->where('uploaded_by', $user->id)->firstOrFail();
        $this->assertSame(350, (int) $webSong->voice_bitrate_kbps);

        $this->actingAs($user)->post("/studio-musik/songs/{$webSong->id}/upgrade-bitrate", [
            'voice_bitrate_kbps' => 378,
        ])->assertRedirect()->assertSessionHas('ok');
        $this->assertSame(378, (int) $webSong->fresh()->voice_bitrate_kbps);

        // 6. Clean up test songs, tokens, temporary artist & file
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->deleteJson("/api/v1/studio/songs/{$songId}")->assertStatus(200);
        $this->app['auth']->forgetGuards();
        $this->actingAs($user)->delete("/studio-musik/songs/{$webSong->id}")->assertRedirect()->assertSessionHas('ok');

        Artist::where('name', 'Vokal Uji Studio')->doesntHave('songs')->delete();
        $user->tokens()->where('name', 'test-user-import')->delete();
        @unlink($tmpMp3);
    }
}
