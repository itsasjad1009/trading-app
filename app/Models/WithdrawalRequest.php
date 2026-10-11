<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WithdrawalRequest extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['destination'];

    protected function casts(): array
    {
        return ['destination' => 'encrypted'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
