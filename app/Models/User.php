<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'password_salt', 'role', 'avatar', 'phone',
        'is_active', 'preferences', 'last_login_at', 'last_login_ip',
        'failed_login_attempts', 'locked_until',
    ];

    protected $hidden = ['password', 'password_salt', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
        'preferences'       => 'array',
        'last_login_at'     => 'datetime',
        'locked_until'      => 'datetime',
    ];

    protected ?array $favIdsCache = null;

    public function isAdmin(): bool { return $this->role === 'admin'; }

    public function favoriteSongs() { return $this->belongsToMany(Song::class, 'favorites')->withTimestamps(); }
    public function playlists()     { return $this->hasMany(Playlist::class); }
    public function playHistories() { return $this->hasMany(PlayHistory::class); }
    public function lrsStatements()  { return $this->hasMany(LrsStatement::class); }
    public function auditLogs()      { return $this->hasMany(AuditLog::class); }
    public function studioRequests() { return $this->hasMany(StudioRequest::class); }

    /** ID lagu favorit (di-cache per request supaya daftar lagu tidak N+1). */
    public function favoriteIds(): array
    {
        return $this->favIdsCache ??= $this->favoriteSongs()->pluck('songs.id')->all();
    }

    /** Phase 3: Check if account is currently locked. */
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Phase 3: Explicit hashing + cryptographic per-user salt metadata.
     * Note: bcrypt/argon2id embeds its own random salt inside the hash string;
     * we also persist a CSPRNG 128-bit salt identifier for auditability & token derivation.
     */
    public static function hashPasswordWithSalt(string $plain): array
    {
        return [
            'password'      => Hash::make($plain),
            'password_salt' => bin2hex(random_bytes(16)),
        ];
    }

    /** Role-based feature gating. */
    public function unlockedFeatures(): array
    {
        $userFeatures = [
            'favorites', 'playlists', 'history', 'recommendations',
            'equalizer', 'playback_speed', 'sleep_timer',
            'offline_pwa_audio', 'realtime_presence',
        ];

        if ($this->isAdmin()) {
            return array_merge($userFeatures, [
                'api_tokens', 'api_management', 'ai_music_finetuning',
                'record_labels_crud', 'admin_catalog_crud', 'admin_users',
                'admin_error_alerts', 'admin_lrs_audit', 'admin_backups',
                'admin_seo', 'admin_health',
            ]);
        }

        return $userFeatures;
    }
}
