<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'path',
        'type',
        'split',
        'prediction',
        'confidence'
    ];

    protected $casts = [
        'confidence' => 'float'
    ];

    public function scopeWithPredictions($query)
    {
        return $query->whereNotNull('prediction');
    }
}