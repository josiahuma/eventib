<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistration extends Model
{
    //
    protected $fillable = [
        'event_id','user_id','name','email','mobile','status','stripe_session_id','amount','quantity', 'party_adults', 'party_children', 'currency',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'checked_in_at' => 'datetime',
    ];


    public function event() {
        return $this->belongsTo(Event::class);
    }

    public function sessions() {
        return $this->belongsToMany(EventSession::class, 'event_registration_session');
    }

    public function tickets()
    {
        return $this->hasMany(\App\Models\EventTicket::class, 'registration_id');
    }

    public function expectedFreePassToken(): string
    {
        $secret = config('app.key'); // app key as HMAC secret
        $seed = "reg:{$this->id}|created:".optional($this->created_at)->timestamp;
        // base64url of raw HMAC bytes
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $seed, $secret, true)), '+/', '-_'), '=');
    }

    public function freePassPayload(): string
    {
        $event = $this->event; // ensure relation exists
        return "FR|v1|{$event->public_id}|{$this->id}|".$this->expectedFreePassToken();
    }

    public function checker()      { return $this->belongsTo(User::class, 'checked_in_by'); }

}
