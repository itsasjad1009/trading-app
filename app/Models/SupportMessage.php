<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportMessage extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['attachment'];

    public function conversation()
    {
        return $this->belongsTo(SupportConversation::class, 'support_conversation_id');
    }
}
