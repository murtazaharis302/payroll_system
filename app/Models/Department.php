<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\HasCompanyScope;

class Department extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(company::class);
    }

    public function subDepartments()
    {
        return $this->hasMany(SubDepartment::class);
    }

    public function employees()
    {
        return $this->hasMany(EmployeeMaster::class, 'dept_id');
    }
}
