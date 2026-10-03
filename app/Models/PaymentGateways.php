<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGateways extends Model
{
    //
    protected $fillable = [
        'name',
        'c1',
        'c2',
        'c3',
        'c4',
        'c5',
        'dev',
        'dev_endpoint',
        'pro_endpoint',
        'status',
        'order',
    ];
}
