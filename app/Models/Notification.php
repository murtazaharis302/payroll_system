<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    //
    protected $fillable = [
       
        'type',
        'from_user_id',
        'to_user_id',
        'user_id',
        'text',
        'status'
    ];

    public function from_user()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }
    public function to_user()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
