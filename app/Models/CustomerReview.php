<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerReview extends Model
{
    protected $fillable = [
        'user_id',
        'c_user_id',
        'booking_id',
        'rating',
        'comment'
    ];
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function reviewedUser()
    {
        return $this->belongsTo(User::class, 'c_user_id', 'id');
    }
    public function bookings()
    {
        return $this->belongsTo(Booking::class, 'booking_id','id');
    }
}
