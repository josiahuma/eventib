<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRegistrationItem extends Model
{
    protected $fillable = [
        'event_registration_id',
        'event_ticket_category_id',
        'snapshot_name',
        'unit_price',
        'quantity',
        'line_total',
    ];

    public function registration() {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    public function category() {
        return $this->belongsTo(EventTicketCategory::class, 'event_ticket_category_id');
    }
}
