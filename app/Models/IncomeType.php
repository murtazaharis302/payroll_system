<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncomeType extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'is_taxable',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_taxable' => 'boolean',
        'is_active'  => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function ($model) {
            if (!empty($model->system_key)) {
                $query = static::withoutGlobalScopes()
                    ->where('system_key', $model->system_key);

                if ($model->company_id === null) {
                    $query->whereNull('company_id');
                } else {
                    $query->where('company_id', $model->company_id);
                }

                if ($model->exists) {
                    $query->where('id', '!=', $model->id);
                }

                if ($query->exists()) {
                    $scope = $model->company_id ? "company [{$model->company_id}]" : 'global scope';
                    throw new \InvalidArgumentException("Duplicate system_key '{$model->system_key}' is not allowed for {$scope}.");
                }
            }
        });
    }

    public function scopeForCompany(Builder $query, ?int $companyId = null): Builder
    {
        if ($companyId === null) {
            $companyId = getActiveCompanyId();
        }

        if ($companyId === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($companyId) {
            $q->where('company_id', $companyId)
              ->orWhereNull('company_id');
        })->orderByRaw('CASE WHEN company_id IS NOT NULL THEN 0 ELSE 1 END');
    }

    public static function findBySystemKey(string $key, ?int $companyId = null): ?self
    {
        $query = static::withoutGlobalScopes()->where('system_key', $key);

        if ($companyId !== null) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)
                  ->orWhereNull('company_id');
            })->orderByRaw('CASE WHEN company_id IS NOT NULL THEN 0 ELSE 1 END');
        } else {
            $query->whereNull('company_id');
        }

        return $query->first();
    }

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function empIncomes(): HasMany
    {
        return $this->hasMany(EmpIncome::class, 'income_type_id');
    }

    public function scopeSystemKey(Builder $query, string $key): Builder
    {
        return $query->where('system_key', $key);
    }

    public function isBasic(): bool
    {
        return $this->system_key === 'basic';
    }

    public function isOvertime(): bool
    {
        return $this->system_key === 'overtime';
    }

    public static function generateNextCode(?string $name = null): string
    {
        if (!empty($name)) {
            $slugCode = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', trim($name)));
            $slugCode = trim($slugCode, '_');
            if (strlen($slugCode) > 0 && !static::where('code', $slugCode)->exists()) {
                return substr($slugCode, 0, 50);
            }
        }

        $maxId = (int) static::max('id');
        $next = $maxId + 1;
        $code = 'INC-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);

        while (static::where('code', $code)->exists()) {
            $next++;
            $code = 'INC-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        }

        return $code;
    }
}
