<?php

namespace App\Plugins\PeptideVendorsBot\Models;

use Illuminate\Database\Eloquent\Model;

class WelcomeMessage extends Model
{
    protected $table = 'peptide_vendors_welcome_messages';

    protected $fillable = [
        'chat_id',
        'user_id',
        'first_name',
        'username',
        'last_name',
        'is_premium',
        'welcome_message_sent',
        'error',
        'banned',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'banned' => 'boolean',
        'is_premium' => 'boolean',
    ];
}
