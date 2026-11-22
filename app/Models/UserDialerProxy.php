<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDialerProxy extends Model
{
    protected $table = 'v2_user_dialer_proxy';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
}
