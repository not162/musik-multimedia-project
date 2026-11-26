<?php

namespace Tests\Feature;

use App\Models\{AiProviderSetting, ApiEndpointRule, RecordLabel, Song, User};
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Hash;

class MusikkuEnterpriseTest extends TestCase
{
    public function test_public_light_landing_page_labels_and_pwa_endpoints(): void
    {
        $res = $this->get('/');
        $res->assertStatus(200)
            ->assertSee('Akses Publik')
            ->assertSee('Fitur Akun Terdaftar')
            ->assertSee('Daftar Label Musik Baru')
            ->assertSee('manifest.webmanifest')
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
            ->assertDontSee('Personal API Token')
            ->assertDontSee('SALT ID:')
            ->assertDontSee('HASH: BCRYPT');

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

        $this->actingAs($admin)->get('/admin/ai-studio')->assertStatus(200)->assertSee('Konfigurasi API AI Key');
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

        // 1. Save encrypted AI key without exposing raw key in response
        $this->withToken($token)->postJson('/api/v1/admin/ai/provider', [
            'provider'               => 'gemini',
            'model'                  => 'gemini-2.5-flash',
            'api_key'                => 'test_secret_ai_key_987654321',
            'auto_finetune_realtime' => true,
            'is_active'              => false, // keep false during offline test so heuristic runs cleanly
        ])->assertStatus(200)->assertJson(['has_key' => true])->assertDontSee('test_secret_ai_key_987654321');

        // Clean test AI key back out
        AiProviderSetting::query()->update(['api_key_encrypted' => null, 'key_hint' => null, 'is_active' => true]);

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

        $admin->tokens()->where('name', 'test-admin')->delete();
    }
}
