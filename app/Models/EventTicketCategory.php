<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventTicketCategory extends Model
{
    protected $fillable = [
        'event_id','name','price','capacity','is_active','sort',
    ];

    public function event() {
        return $this->belongsTo(Event::class);
    }
}
