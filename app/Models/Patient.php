<?php

namespace App\Models;

use App\Models\Scan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'medical_record_number',
        'full_name',
        'date_of_birth',
        'sex',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function scans(): HasMany
    {
        return $this->hasMany(Scan::class);
    }
}
