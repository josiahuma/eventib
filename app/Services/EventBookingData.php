<?php

namespace App\Services;

use App\Models\Event;

class EventBookingData
{
    public function forEvent(Event $event): array
    {
        $categories = $event->categories()->where('is_active', true)->orderBy('sort')->orderBy('id')->get();
        $sessions = $event->sessions()->where('session_date', '>', now())->orderBy('session_date')->get();
        return [
            'mode' => $categories->isNotEmpty() ? 'cats' : ((float)$event->ticket_cost > 0 ? 'single' : 'free'),
            'currency' => strtoupper($event->ticket_currency ?: 'GBP'),
            'symbol' => $event->currency_symbol,
            'unitMinor' => (int) round((float)$event->ticket_cost * 100),
            'feeBps' => $event->feeMode() === 'pass' ? max(0, (int)($event->fee_bps ?? 590)) : 0,
            'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'priceMinor' => (int) round((float)$c->price * 100)])->values()->all(),
            'sessions' => $sessions->map(fn ($s) => ['id' => $s->id, 'name' => $s->session_name, 'date' => \Illuminate\Support\Carbon::parse($s->session_date)->format('D, d M · g:ia')])->values()->all(),
        ];
    }
}
