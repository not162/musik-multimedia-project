<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AiProviderSetting extends Model
{
    protected $fillable = [
        'provider', 'model', 'base_url', 'api_key_encrypted', 'key_hint',
        'auto_finetune_realtime', 'is_active', 'last_tested_at', 'last_status', 'updated_by',
    ];

    protected $hidden = ['api_key_encrypted'];

    protected $casts = [
        'auto_finetune_realtime' => 'boolean',
        'is_active'              => 'boolean',
        'last_tested_at'         => 'datetime',
    ];

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function setPlainApiKey(?string $plain): void
    {
        if (! $plain) return;
        $trimmed = trim($plain);
        $this->api_key_encrypted = Crypt::encryptString($trimmed);
        $this->key_hint = strlen($trimmed) >= 8
            ? substr($trimmed, 0, 4) . '••••' . substr($trimmed, -4)
            : '••••';
    }

    public function getDecryptedApiKey(): ?string
    {
        if ($this->api_key_encrypted) {
            try {
                return Crypt::decryptString($this->api_key_encrypted);
            } catch (\Throwable) {
                return null;
            }
        }
        return match ($this->provider) {
            'gemini' => env('GEMINI_API_KEY') ?: null,
            'openai' => env('OPENAI_API_KEY') ?: null,
            default  => env('AI_API_KEY') ?: null,
        };
    }

    public function hasApiKey(): bool
    {
        return ! empty($this->getDecryptedApiKey());
    }
}
