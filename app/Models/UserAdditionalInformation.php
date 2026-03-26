<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAdditionalInformation extends Model
{
    use HasFactory;

    protected $table = 'user_details';

    protected $fillable = [
        'user_id',
        'gender',
        'phone',
        'address',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
