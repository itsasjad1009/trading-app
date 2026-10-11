<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pair()
    {
        return $this->belongsTo(TradingPair::class, 'trading_pair_id');
    }

    public function settlement()
    {
        return $this->hasOne(TradeSettlement::class);
    }
}
