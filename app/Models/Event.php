<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','name','organizer','category','tags','location','description',
        'avatar_url','banner_url','ticket_cost','is_promoted','public_id',
        'ticket_currency','payout_method_id',
    ];

    protected $casts = [
        'tags'        => 'array',
        'is_promoted' => 'boolean',
        'ticket_cost' => 'decimal:2',
    ];

    public function getCurrencySymbolAttribute(): string
    {
        return match (strtoupper($this->ticket_currency ?? 'GBP')) {
            'GBP'=>'£','USD'=>'$','EUR'=>'€','NGN'=>'₦','KES'=>'KSh','GHS'=>'GH₵','ZAR'=>'R','CAD'=>'C$','AUD'=>'A$',
            default => strtoupper($this->ticket_currency ?? 'GBP').' ',
        };
    }

    public function getRouteKeyName(): string { return 'public_id'; }

    protected static function booted()
    {
        static::creating(function (Event $event) {
            if (empty($event->public_id)) {
                do { $event->public_id = (string) Str::ulid(); }
                while (static::where('public_id', $event->public_id)->exists());
            }
        });
    }

    // Relationships
    public function sessions()      { return $this->hasMany(EventSession::class); }
    public function registrations() { return $this->hasMany(EventRegistration::class); }
    public function unlocks()       { return $this->hasMany(EventUnlock::class); }
    public function payouts()       { return $this->hasMany(EventPayout::class); }
    public function user()          { return $this->belongsTo(User::class); }
    public function payoutMethod()  { return $this->belongsTo(UserPayoutMethod::class, 'payout_method_id')->withDefault(); }

    /** Gross paid (minor units) from registrations.amount where status is paid */
    public function grossPaidMinor(): int
    {
        return (int) $this->registrations()
            ->whereIn('status', ['paid','complete','completed','succeeded'])
            ->sum(DB::raw('ROUND(amount * 100)')); // amount is decimal major units
    }

    /** 9.99% fee on gross (minor units) */
    public function feeMinor(): int
    {
        return (int) round($this->grossPaidMinor() * 0.0999);
    }

    /** Net earned (minor units) */
    public function netPaidMinor(): int
    {
        return max(0, $this->grossPaidMinor() - $this->feeMinor());
    }

    /** Sum of payouts (minor units) that reduce availability */
    public function deductedPayoutMinor(): int
    {
        return (int) $this->payouts()
            ->whereNotIn('status', ['failed','cancelled'])
            ->sum('amount'); // your EventPayout.amount is already minor units
    }

    /** What the organiser can request right now (minor units, >= 0) */
    public function availablePayoutMinor(): int
    {
        return max(0, $this->netPaidMinor() - $this->deductedPayoutMinor());
    }
}
