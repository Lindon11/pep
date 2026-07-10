<?php

namespace App\Plugins\PeptideVendorsBot\Models;

use Illuminate\Database\Eloquent\Model;

class Verification extends Model
{
    protected $table = 'peptide_vendors_verifications';

    protected $fillable = [
        'user_id',
        'user_name',
        'username',
        'agreed',
        'agreed_at',
    ];

    protected $casts = [
        'agreed' => 'boolean',
        'agreed_at' => 'datetime',
    ];
}
