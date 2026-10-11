<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KycApplication extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['national_id', 'tax_id', 'full_name'];

    protected function casts(): array
    {
        return ['national_id' => 'encrypted', 'tax_id' => 'encrypted', 'full_name' => 'encrypted'];
    }

    public function documents()
    {
        return $this->hasMany(KycDocument::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
