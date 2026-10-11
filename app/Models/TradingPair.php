<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingPair extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function trades()
    {
        return $this->hasMany(Trade::class);
    }
}
