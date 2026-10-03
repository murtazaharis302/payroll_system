<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasCompanyScope;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    // protected $cascadeSoftDeletes = ['companies'];
    protected $fillable = [
        'name',
        'email',
        'google_id',
        'password',
        'role_id',
        'role',
        'company_id',
        'emp_id',
        'phone',
        'status',
        'confirmation_token',
        'language_id',
        'currency_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
        {
            return [
                'email_verified_at' => 'datetime',
                'password' => 'hashed',
            ];
        }
    public function owners()
    {
        return $this->hasMany(Employee::class, 'owner_user_id','id');
    }
    public function employees()
    {
        return $this->hasOne(Employee::class, 'e_user_id','id');
    }
    public function companies()
        {
            return $this->hasone(Company::class, 'user_id','id');
        }
    public function userIps()
        {
            return $this->hasMany(IP_Address::class, 'user_id','id');
        }
    public function permissions()
    {
        return $this->hasMany(Permission::class, 'user_id','id');
    }

    public function langugae()
    {
        return $this->belongsTo(Language::class, 'language_id', 'id');
    }
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id', 'id');
    }

    public function payrollMaster(): BelongsTo
    {
        return $this->belongsTo(EmployeeMaster::class, 'emp_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function getRoleAttribute()
    {
        $roleRelation = $this->relationLoaded('role') ? $this->getRelationValue('role') : $this->role()->first();
        $roleName = $roleRelation?->name;
        if (!$roleName && isset($this->attributes['role_id'])) {
            $roleMap = [
                1 => 'admin',
                2 => 'company',
                3 => 'hr',
                4 => 'employee',
            ];
            return $roleMap[$this->attributes['role_id']] ?? '';
        }
        if ($roleName === 'SuperAdmin') {
            return 'admin';
        }
        if ($roleName === 'CompanyAdmin') {
            return 'company';
        }
        if ($roleName === 'HR') {
            return 'hr';
        }
        if ($roleName === 'Employee') {
            return 'employee';
        }

        return strtolower($roleName ?? '');
    }

    public function setRoleAttribute($value): void
    {
        $roleMap = [
            'admin' => 1,
            'company' => 2,
            'hr' => 3,
            'employee' => 4,
        ];
        
        $roleId = $roleMap[strtolower($value)] ?? null;
        if ($roleId) {
            $this->attributes['role_id'] = $roleId;
        } else {
            // Find or create role by name
            $role = Role::firstOrCreate(['name' => ucfirst($value)]);
            $this->attributes['role_id'] = $role->id;
        }
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeMaster::class, 'emp_id');
    }
}
