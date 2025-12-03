<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDigitalPass extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'voice_embedding',
        'voice_enrolled_at',
        'face_embedding',
        'face_enrolled_at',
        'is_active',
    ];

    protected $casts = [
        'voice_embedding' => 'array',
        'face_embedding'  => 'array',
        'voice_enrolled_at' => 'datetime',
        'face_enrolled_at'  => 'datetime',
        'is_active'         => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
