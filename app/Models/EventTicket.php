<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventTicket extends Model
{
    protected $fillable = [
        'event_id','registration_id','index','serial','token','status','checked_in_at','checked_in_by'
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function event()        { return $this->belongsTo(Event::class); }
    public function registration() { return $this->belongsTo(EventRegistration::class, 'registration_id'); }
    public function checker()      { return $this->belongsTo(User::class, 'checked_in_by'); }

    public static function makeToken(string $eventPublicId, int $registrationId, int $idx): string
    {
        $payload = "e:$eventPublicId|r:$registrationId|i:$idx";
        $key = config('app.key');
        $raw = hash_hmac('sha256', $payload, $key, true);
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '='); // base64url
    }

    public static function makeSerial(int $registrationId, int $idx): string
    {
        return strtoupper(base_convert($registrationId, 10, 36)).'-'.str_pad((string)($idx+1), 3, '0', STR_PAD_LEFT);
    }
}
