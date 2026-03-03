<?php

namespace App\Models;

use App\Models\Scan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Prediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'scan_id',
        'predicted_label',
        'ground_truth_label',
        'ground_truth_source',
        'ground_truth_recorded_at',
        'probability',
        'heatmap_path',
        'finding_location',
        'severity_score',
        'confidence_band',
        'region_confidence_score',
        'cancer_stage',
        'confidence_reasoning',
        'ct_viewer',
        'explanation_maps',
        'nodule_diameter_mm',
        'nodule_area_px',
        'tumor_area_mm2',
        'tumor_volume_mm3',
        'growth_rate_percent',
        'nodule_burden_percent',
        'raw_response',
        'model_version',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_response' => 'array',
            'evaluated_at' => 'datetime',
            'ground_truth_recorded_at' => 'datetime',
            'probability' => 'float',
            'severity_score' => 'float',
            'region_confidence_score' => 'float',
            'ct_viewer' => 'array',
            'explanation_maps' => 'array',
            'nodule_diameter_mm' => 'float',
            'nodule_area_px' => 'float',
            'tumor_area_mm2' => 'float',
            'tumor_volume_mm3' => 'float',
            'growth_rate_percent' => 'float',
            'nodule_burden_percent' => 'float',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(PredictionFeedback::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PredictionComment::class)->latest();
    }

    public function twoPassReviews(): HasMany
    {
        return $this->hasMany(\App\Models\PredictionTwoPassReview::class)->latest('completed_at');
    }
}
