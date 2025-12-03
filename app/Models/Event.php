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
        'user_id',
        'name',
        'organizer_id',
        'category',
        'tags',
        'location',
        'description',
        'avatar_url',
        'banner_url',
        'ticket_cost',
        'is_promoted',
        'public_id',
        'ticket_currency',
        'payout_method_id',
        'is_disabled',
        'fee_mode',
        'fee_bps',
        'is_recurring',
        'recurrence_summary',
        'capacity',

        // 🔐 NEW
        'digital_pass_mode',      // off | optional | required
        'digital_pass_methods',   // voice | face | both
    ];

    protected $casts = [
        'tags'        => 'array',
        'is_promoted' => 'boolean',
        'ticket_cost' => 'decimal:2',
        'is_disabled' => 'boolean',
        'fee_bps'     => 'integer',
        'is_recurring'=> 'boolean',

        // not strictly required, but keeps IDE happy
        'digital_pass_mode'    => 'string',
        'digital_pass_methods' => 'string',
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

    public function feeMode(): string
    {
        return in_array($this->fee_mode, ['absorb','pass'], true) ? $this->fee_mode : 'absorb';
    }

    public function feeRate(): float
    {
        return max(0, (int)($this->fee_bps ?? 590)) / 10000; // e.g. 590 -> 0.059
    }

     /** Gross paid (minor units) from registrations.amount (organiser revenue only) */
    public function grossPaidMinor(): int
    {
        return (int) $this->registrations()
            ->whereIn('status', ['paid','complete','completed','succeeded'])
            ->sum(\DB::raw('ROUND(amount * 100)')); // amount is ticket subtotal in major units
    }

    /** Platform fee on organiser (minor units). If fee is passed to attendee, organiser is not charged. */
    public function feeMinor(): int
    {
        if ($this->feeMode() === 'pass') {
            return 0; // organiser not charged when fee is passed to attendee
        }
        return (int) round($this->grossPaidMinor() * $this->feeRate());
    }

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

    public function categories()
    {
        return $this->hasMany(\App\Models\EventTicketCategory::class)->orderBy('sort')->orderBy('id');
    }

    // Only completed attendees (free or paid)
    public function attendees()
    {
        return $this->hasMany(\App\Models\EventRegistration::class)
            ->whereIn('status', ['paid', 'free']);
    }

    /** Next upcoming session (or null) */
    public function nextUpcomingSession()
    {
        return $this->sessions()
            ->where('session_date', '>=', now())
            ->orderBy('session_date', 'asc')
            ->first();
    }

    /** All upcoming sessions ordered soonest → latest */
    public function upcomingSessions()
    {
        return $this->sessions()
            ->where('session_date', '>=', now())
            ->orderBy('session_date', 'asc');
    }

    /** Nice label for recurring badge */
    public function getRecurrenceLabelAttribute(): ?string
    {
        if (! $this->is_recurring) {
            return null;
        }

        // Prefer the custom summary if present
        if ($this->recurrence_summary) {
            return $this->recurrence_summary;
        }

        return 'Recurring event';
    }

    // Convenience: money actually received (paid only)
    public function paidAmount()
    {
        return (float) $this->attendees()->where('status', 'paid')->sum('amount');
    }

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }
}
