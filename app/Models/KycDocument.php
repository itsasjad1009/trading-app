<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KycDocument extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['path'];

    public function application()
    {
        return $this->belongsTo(KycApplication::class, 'kyc_application_id');
    }
}
