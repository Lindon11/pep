<?php

namespace App\Plugins\PeptideVendorsBot\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $table = 'peptide_vendors_reviews';

    protected $fillable = [
        'vendor',
        'rating',
        'feedback',
        'user_id',
        'user_name',
        'reviewer_name',
        'chat_id',
        'telegram_message_id',
    ];

    protected $casts = [
        'rating' => 'integer',
        'telegram_message_id' => 'integer',
    ];
}
