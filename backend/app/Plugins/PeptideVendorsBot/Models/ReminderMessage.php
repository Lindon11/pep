<?php

namespace App\Plugins\PeptideVendorsBot\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderMessage extends Model
{
    protected $table = 'peptide_vendors_reminder_messages';

    protected $fillable = [
        'chat_id',
        'message_id',
        'user_id',
        'sent_at',
    ];

    protected $casts = [
        'message_id' => 'integer',
        'user_id' => 'integer',
        'sent_at' => 'datetime',
    ];
}
