<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class StatutoryRule extends Model
{
    protected $table = 'statutory_rules';

    protected $fillable = [
        'statutory_type',
        'version_code',
        'source_document',
        'effective_from',
        'effective_to',
        'salary_floor',
        'salary_ceiling',
        'ee_share_rate',
        'er_share_rate',
        'brackets',
        'rounding_decimals',
        'rounding_mode',
        'is_active',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'salary_floor' => 'decimal:2',
        'salary_ceiling' => 'decimal:2',
        'ee_share_rate' => 'decimal:4',
        'er_share_rate' => 'decimal:4',
        'brackets' => 'array',
        'rounding_decimals' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Scope query to active rule version matching statutory type and pay date.
     *
     * @param Builder $query
     * @param string $type
     * @param string $payDate
     * @return Builder
     */
    public function scopeForTypeAndDate(Builder $query, string $type, string $payDate): Builder
    {
        return $query->where('statutory_type', strtolower($type))
            ->where('is_active', true)
            ->where('effective_from', '<=', $payDate)
            ->where(function ($q) use ($payDate) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $payDate);
            })
            ->orderBy('effective_from', 'desc');
    }
}
