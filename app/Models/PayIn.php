<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayIn extends Model
{
    use HasCompanyScope;

    public $timestamps = false;

    protected $fillable = [
        'pay_id',
        'income_type_id',
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

    public function incomeType(): BelongsTo
    {
        return $this->belongsTo(IncomeType::class, 'income_type_id');
    }

    public function getAmountAttribute(): float
    {
        return (float) ($this->rate * $this->number_input);
    }
}
