<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function account()
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }
}
