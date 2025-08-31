<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'accuracy',
        'precision',
        'recall',
        'f1_score',
        'auc_roc',
        'confusion_matrix',
        'split_ratio'
    ];

    protected $casts = [
        'confusion_matrix' => 'array'
    ];
}