<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivity extends Model
{
 protected $fillable = [
    'ip_address',
    'browser',
    'url',
    'user_id',
    'method',
    'referrer',
    'action_type',
    'element_clicked',
];

}
