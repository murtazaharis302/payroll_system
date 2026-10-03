<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanType extends Model
{
    use HasCompanyScope;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::saving(function (LoanType $loanType) {
            if (empty($loanType->deduction_type_id)) {
                throw new \InvalidArgumentException("A loan configuration cannot exist without an explicitly selected Deduction Type (deduction_type_id is required).");
            }
        });
    }

    protected $fillable = [
        'company_id',
        'deduction_type_id',
        'name',
        'default_amortization_per_cutoff',
        'default_installments_count',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'default_amortization_per_cutoff' => 'decimal:2',
        'default_installments_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class, 'deduction_type_id');
    }

    public function empLoans(): HasMany
    {
        return $this->hasMany(EmpLoan::class, 'loan_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
