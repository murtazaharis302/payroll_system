<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'symbol',
        'code',
        'rate',
        'decimals',
        'symbol_placement',
        'primary_order',
        'is_default',
        'is_active',
    ];
    public function currencies()
    {
        return $this->hasMany(Currency::class, 'currency_id','id');
    }
}
