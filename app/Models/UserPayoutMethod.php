<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPayoutMethod extends Model
{
    protected $fillable = [
        'user_id','type','country','paypal_email',
        'account_name','account_number','sort_code','iban','swift',
    ];

    protected $appends = ['display_name','last4'];

    public function getDisplayNameAttribute(): string
    {
        return $this->type === 'bank'
            ? ($this->account_name ?: 'Bank')
            : 'PayPal';
    }

    public function getLast4Attribute(): ?string
    {
        if ($this->type !== 'bank') return null;
        $digits = preg_replace('/\D+/', '', (string) $this->account_number);
        return $digits ? substr($digits, -4) : null;
    }
}
