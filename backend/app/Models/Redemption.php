<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Redemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'reward_id',
        'points_spent',
        'purchased_at',
    ];

    protected $dates = [
        'purchased_at',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function reward()
    {
        return $this->belongsTo(Reward::class);
    }
}
