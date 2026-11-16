<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AliveIpHistory extends Model
{
    protected $table = 'v2_alive_ip_history';
    protected $guarded = ['id'];
    protected $dateFormat = 'U';
    protected $casts = [
        'last_seen_at' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];
}
