<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = [
        'name',
        'code',
        'flag_code',
        'is_active',
    ];
    public function users()
    {
        return $this->hasMany(User::class, 'language_id','id');
    }

}
