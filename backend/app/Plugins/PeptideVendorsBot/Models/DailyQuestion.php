<?php

namespace App\Plugins\PeptideVendorsBot\Models;

use Illuminate\Database\Eloquent\Model;

class DailyQuestion extends Model
{
    protected $table = 'peptide_vendors_daily_questions';

    protected $fillable = [
        'question',
        'enabled',
        'last_posted_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_posted_at' => 'datetime',
    ];
}
