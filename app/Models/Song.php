<?php
namespace App\Models;

use App\Models\Concerns\HasArt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Song extends Model
{
    use HasArt;

    protected $fillable = [
        'artist_id', 'album_id', 'genre_id', 'record_label_id', 'uploaded_by',
        'title', 'file_path', 'media_format', 'voice_bitrate_kbps', 'cover', 'lyrics', 'duration', 'play_count',
        'is_ai_finetuned', 'ai_mood', 'ai_tempo_bpm', 'ai_direction',
        'ai_eq_bands', 'ai_preamp_db', 'ai_pitch_rate', 'ai_mastering_notes',
        'ai_finetuned_at', 'audio_meta',
        'training_split', 'voice_preset', 'voice_clarity_score',
        'voice_smoothness_score', 'noise_reduction_db', 'target_sample_rate_khz',
        'hires_file_path', 'is_voice_smoothed', 'labeled_by', 'labeled_at',
    ];

    protected $casts = [
        'uploaded_by'            => 'integer',
        'voice_bitrate_kbps'     => 'integer',
        'is_ai_finetuned'        => 'boolean',
        'is_voice_smoothed'      => 'boolean',
        'ai_eq_bands'            => 'array',
        'ai_preamp_db'           => 'float',
        'ai_pitch_rate'          => 'float',
        'noise_reduction_db'     => 'float',
        'target_sample_rate_khz' => 'integer',
        'ai_finetuned_at'        => 'datetime',
        'labeled_at'             => 'datetime',
        'audio_meta'             => 'array',
    ];

    public function artist()          { return $this->belongsTo(Artist::class); }
    public function album()           { return $this->belongsTo(Album::class); }
    public function genre()           { return $this->belongsTo(Genre::class); }
    public function recordLabel()     { return $this->belongsTo(RecordLabel::class); }
    public function uploader()        { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function histories()       { return $this->hasMany(PlayHistory::class); }
    public function favoritedBy()     { return $this->belongsToMany(User::class, 'favorites')->withTimestamps(); }
    public function trainingSamples() { return $this->hasMany(AudioTrainingSample::class); }
    public function studioRequests()  { return $this->hasMany(StudioRequest::class); }

    public function getArtUrlAttribute(): ?string
    {
        $path = $this->cover ?: $this->album?->cover;
        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function getDurationLabelAttribute(): string
    {
        return intdiv($this->duration, 60) . ':' . str_pad($this->duration % 60, 2, '0', STR_PAD_LEFT);
    }

    public function scopeSearch($q, ?string $term)
    {
        return $term ? $q->where(function ($w) use ($term) {
            $w->where('title', 'like', "%{$term}%")
              ->orWhereHas('artist', fn ($a) => $a->where('name', 'like', "%{$term}%"))
              ->orWhereHas('album',  fn ($a) => $a->where('title', 'like', "%{$term}%"))
              ->orWhereHas('recordLabel', fn ($l) => $l->where('name', 'like', "%{$term}%"));
        }) : $q;
    }
}
