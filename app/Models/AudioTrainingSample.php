<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AudioTrainingSample extends Model
{
    protected $fillable = [
        'song_id', 'label_name', 'dataset_split', 'target_clarity',
        'target_smoothness', 'noise_reduction_db', 'extracted_features',
        'target_eq_bands', 'notes', 'labeled_by',
    ];

    protected $casts = [
        'extracted_features' => 'array',
        'target_eq_bands'    => 'array',
        'noise_reduction_db' => 'float',
    ];

    public function song()
    {
        return $this->belongsTo(Song::class);
    }

    public function labeler()
    {
        return $this->belongsTo(User::class, 'labeled_by');
    }
}
