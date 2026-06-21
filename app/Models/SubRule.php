<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubRule extends Model
{
    protected $table = 'v2_sub_rule';
    protected $guarded = [];
    protected $dateFormat = 'U';
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];
}
