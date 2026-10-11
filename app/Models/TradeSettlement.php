<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradeSettlement extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function trade()
    {
        return $this->belongsTo(Trade::class);
    }
}
