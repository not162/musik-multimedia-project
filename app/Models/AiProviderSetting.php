<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AiProviderSetting extends Model
{
    protected $fillable = [
        'provider', 'model', 'base_url', 'compute_name', 'compute_key_encrypted', 'compute_key_hint',
        'api_key_encrypted', 'key_hint', 'auto_finetune_realtime', 'is_active',
        'last_tested_at', 'last_status', 'updated_by',
    ];

    protected $hidden = ['api_key_encrypted', 'compute_key_encrypted'];

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
        $this->key_hint = $this->maskSecret($trimmed);
    }

    public function setPlainComputeKey(?string $plain): void
    {
        if (! $plain) return;
        $trimmed = trim($plain);
        $this->compute_key_encrypted = Crypt::encryptString($trimmed);
        $this->compute_key_hint = $this->maskSecret($trimmed);
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
            'gemini' => config('services.ai.gemini_key') ?: null,
            'openai' => config('services.ai.openai_key') ?: null,
            'nvidia' => config('services.ai.nvidia_key') ?: null,
            default  => config('services.ai.compute_api_key') ?: null,
        };
    }

    public function getDecryptedComputeKey(): ?string
    {
        if ($this->compute_key_encrypted) {
            try {
                return Crypt::decryptString($this->compute_key_encrypted);
            } catch (\Throwable) {
                return null;
            }
        }
        return config('services.ai.compute_api_key') ?: null;
    }

    public function hasApiKey(): bool
    {
        return ! empty($this->getDecryptedApiKey());
    }

    public function hasComputeKey(): bool
    {
        return ! empty($this->getDecryptedComputeKey());
    }

    private function maskSecret(string $trimmed): string
    {
        return strlen($trimmed) >= 8
            ? substr($trimmed, 0, 5) . '••••' . substr($trimmed, -4)
            : '••••';
    }
}
