<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayOut extends Model
{
    use HasCompanyScope;

    public $timestamps = false;

    protected $fillable = [
        'pay_id',
        'deduction_type_id',
        'loan_id',
        'number_input',
        'rate',
    ];

    protected $casts = [
        'number_input' => 'decimal:2',
        'rate' => 'decimal:2',
    ];

    public function payHeader(): BelongsTo
    {
        return $this->belongsTo(PayHeader::class, 'pay_id');
    }

    public function deductionType(): BelongsTo
    {
        return $this->belongsTo(DeductionType::class, 'deduction_type_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmpLoan::class, 'loan_id');
    }

    public function getAmountAttribute(): float
    {
        return (float) ($this->rate * $this->number_input);
    }
}
