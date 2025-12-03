<?php

namespace App\Models;

use App\Models\User;

use Illuminate\Database\Eloquent\Model;

class EventRegistration extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'name',
        'email',
        'mobile',
        'status',
        'stripe_session_id',
        'amount',
        'quantity',
        'party_adults',
        'party_children',
        'currency',

        // voice legacy fields
        'voice_sample_path',
        'voice_enabled',
        'voice_recorded_at',
        'voice_embedding',

        // 💳 fees / tokens
        'platform_fee',
        'qr_token',

        // 🔐 digital pass flags & snapshots
        'uses_digital_pass',
        'digital_pass_method',
        'voice_embedding_snapshot',
        'face_embedding_snapshot',

        // check-in
        'checked_in_at',
        'checked_in_by',
    ];

    protected $casts = [
        'amount'                   => 'decimal:2',
        'platform_fee'             => 'decimal:2',
        'created_at'               => 'datetime',
        'updated_at'               => 'datetime',
        'checked_in_at'            => 'datetime',
        'uses_digital_pass'        => 'boolean',
        'voice_embedding_snapshot' => 'array',
        'face_embedding_snapshot'  => 'array',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function sessions()
    {
        return $this->belongsToMany(EventSession::class, 'event_registration_session');
    }

    public function tickets()
    {
        return $this->hasMany(\App\Models\EventTicket::class, 'registration_id');
    }

    public function checker()
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function items()
    {
        return $this->hasMany(\App\Models\EventRegistrationItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    // ... your expectedFreePassToken() and freePassPayload() stay as-is
}
