<?php

namespace App\Plugins\PeptideVendorsBot\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookUpdate extends Model
{
    protected $table = 'peptide_vendors_webhook_updates';

    protected $fillable = [
        'update_id',
        'type',
        'chat_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
