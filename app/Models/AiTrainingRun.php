<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiTrainingRun extends Model
{
    protected $fillable = [
        'model_name', 'architecture', 'train_count', 'val_count', 'epochs',
        'train_loss', 'val_loss', 'val_accuracy', 'clarity_gain_pct',
        'weights', 'metrics_detail', 'status', 'trained_by',
    ];

    protected $casts = [
        'weights'          => 'array',
        'metrics_detail'   => 'array',
        'train_loss'       => 'float',
        'val_loss'         => 'float',
        'val_accuracy'     => 'float',
        'clarity_gain_pct' => 'float',
    ];

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trained_by');
    }
}
