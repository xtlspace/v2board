<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubLog extends Model
{
    protected $table = 'v2_sub_log';
    protected $guarded = [];
    protected $dateFormat = 'U';
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];
}
