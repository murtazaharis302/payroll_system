<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IP_Address extends Model
{
    protected $table = 'ip_address';
    protected $fillable = [
        'user_id',
        'ip_address',
    ];
    public function users()
    {
        return $this->BelongsTo(User::class, 'user_id','id');
    }
}
