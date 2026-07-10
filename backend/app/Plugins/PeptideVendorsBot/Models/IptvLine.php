<?php

namespace App\Plugins\PeptideVendorsBot\Models;

use Illuminate\Database\Eloquent\Model;

class IptvLine extends Model
{
    protected $table = 'peptide_vendors_iptv_lines';

    protected $fillable = [
        'line_id',
        'old_username',
        'password',
        'expire_date',
        'notes',
        'speed',
        'con',
        'watching',
        'ip',
        'owner',
        'telegram_username',
        'telegram_user_id',
        'telegram_first_name',
        'telegram_last_name',
        'new_username',
        'new_password',
        'linked_at',
        'can_dm',
        'last_notified_at',
        'last_notified_message',
    ];
}
