<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralModule extends Model
{
    use HasFactory;

    protected $table = 'general_settings';

    protected $fillable = [
        'commissions',
        'tax',
        'user_id',
        'status',
    ];

    /**
     * Optional: Relationship with User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

