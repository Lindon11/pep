<?php

namespace App\Plugins\PeptideVendorsBot\Models;

use Illuminate\Database\Eloquent\Model;

class TopicPermission extends Model
{
    protected $table = 'peptide_vendors_topic_permissions';

    protected $fillable = [
        'topic_id',
        'allowed_user_id',
        'allowed_user_name',
        'topic_name',
        'added_by',
    ];

    protected $casts = [
        'topic_id' => 'integer',
    ];
}
