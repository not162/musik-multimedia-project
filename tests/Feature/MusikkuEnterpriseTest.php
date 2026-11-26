<?php

namespace Tests\Feature;

use App\Models\{ErrorLog, LrsStatement, Song, User};
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Hash;

class MusikkuEnterpriseTest extends TestCase
{
    public function test_public_light_landing_page_and_pwa_endpoints(): void
    {
        $res = $this->get('/');
        $res->assertStatus(200)
            ->assertSee('Akses Publik')
            ->assertSee('Fitur Akun Terdaftar')
            ->assertSee('manifest.webmanifest')
            ->assertDontSee('Mengenal Produk Musikku');

        $this->get('/sitemap.xml')->assertStatus(200);
        $this->get('/offline')->assertStatus(200);
        $this->getJson('/api/v1/health')->assertStatus(200)->assertJsonPath('status', 'ok');
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

        // User allowed on /api/v1/me/preferences, blocked on /api/v1/admin/error-logs
        $this->withToken($token)->putJson('/api/v1/me/preferences', [
            'eq_preset'    => 'adaptive',
            'eq_mode'      => 'adaptive',
            'eq_direction' => 'warm_bass',
            'eq_intensity' => 80,
            'eq_bands'     => [4, 3, 2, 1, 0, 1, 0, -1, -1, 0],
        ])->assertStatus(200)->assertJsonPath('preferences.eq_direction', 'warm_bass');

        $this->withToken($token)->getJson('/api/v1/admin/error-logs')->assertStatus(403);

        // Cleanup
        $user->tokens()->delete();
        $user->delete();
    }

    public function test_admin_can_access_error_logs_and_lrs(): void
    {
        $admin = User::where('email', 'admin@musikku.test')->firstOrFail();
        $token = $admin->createToken('test-admin', ['admin', 'user'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/admin/error-logs')->assertStatus(200);
        $this->withToken($token)->getJson('/api/v1/admin/lrs-statements')->assertStatus(200);
        $this->withToken($token)->getJson('/api/v1/admin/health')->assertStatus(200);

        $admin->tokens()->where('name', 'test-admin')->delete();
    }
}
