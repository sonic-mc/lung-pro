<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionTwoPassReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'prediction_id',
        'reviewer_id',
        'baseline_label',
        'baseline_confidence',
        'baseline_time_seconds',
        'assisted_label',
        'assisted_confidence',
        'assisted_time_seconds',
        'overlooked_nodule_recovered',
        'notes',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'baseline_confidence' => 'float',
            'assisted_confidence' => 'float',
            'overlooked_nodule_recovered' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
