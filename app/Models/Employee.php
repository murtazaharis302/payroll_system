<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $table = 'employees';
    protected $fillable =[
        'owner_user_id',
        'e_user_id',
        'designation',
        'id_number',
        'age',
        'address',
        'status',
    ];
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id','id');
    }
    public function employee()
    {
        return $this->belongsTo(User::class, 'e_user_id','id');
    }

    /**
     * Generate auto-incremented employee ID number (e.g. EMP-0001)
     */
    public static function generateIdNumber(): string
    {
        $lastEmployee = self::orderBy('id', 'desc')->first();
        $nextNumber = $lastEmployee ? ($lastEmployee->id + 1) : 1;
        return 'EMP-' . str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
    }
}

