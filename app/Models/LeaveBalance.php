<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'year',
        'month',
        'casual_leave',
        'sick_leave',
        'annual_leave',
        'used_casual',
        'used_sick',
        'used_annual',
    ];

    protected $casts = [
        'casual_leave' => 'decimal:1',
        'sick_leave' => 'decimal:1',
        'annual_leave' => 'decimal:1',
        'used_casual' => 'decimal:1',
        'used_sick' => 'decimal:1',
        'used_annual' => 'decimal:1',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getAvailableCasualAttribute(): float
    {
        return (float) $this->casual_leave - (float) $this->used_casual;
    }

    public function getAvailableSickAttribute(): float
    {
        return (float) $this->sick_leave - (float) $this->used_sick;
    }

    public function getAvailableAnnualAttribute(): float
    {
        return (float) $this->annual_leave - (float) $this->used_annual;
    }

    public function getTotalAvailableAttribute(): float
    {
        return $this->available_casual + $this->available_sick + $this->available_annual;
    }
}
