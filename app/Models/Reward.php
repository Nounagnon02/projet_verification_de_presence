<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'cost',
        'image_url',
        'stock',
        'is_active',
    ];

    public function redemptions()
    {
        return $this->hasMany(Redemption::class);
    }
}
