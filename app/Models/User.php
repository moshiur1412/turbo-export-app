<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'employee_id',
        'name',
        'email',
        'password',
        'designation_id',
        'department_id',
        'salary_id',
        'attendance_id',
        'join_date',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'join_date' => 'date',
    ];

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function salary()
    {
        return $this->belongsTo(Salary::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }

    public function additionalInformation()
    {
        return $this->hasOne(UserAdditionalInformation::class);
    }
}
