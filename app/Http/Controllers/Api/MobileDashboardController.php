<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventPayout;

class MobileDashboardController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->organizer) {
            return response()->json([
                'message' => 'You need an organiser profile to view dashboard analytics.',
            ], 422);
        }

        $organizer   = $user->organizer;
        $currentYear = now()->year;
        $year        = (int) $request->query('year', $currentYear);

        // 1) Events posted this year
        $eventsCount = Event::where('organizer_id', $organizer->id)
            ->whereYear('created_at', $year)
            ->count();

        // 2) Total earnings from payouts (minor units / pence)
        $totalEarnings = (int) EventPayout::whereHas('event', function ($q) use ($organizer) {
                $q->where('organizer_id', $organizer->id);
            })
            ->where('status', 'paid')
            ->whereYear('created_at', $year)
            ->sum('amount');

        // 3) Total registered attendees (registrant + party)
        $registrations = EventRegistration::whereHas('event', function ($q) use ($organizer) {
                $q->where('organizer_id', $organizer->id);
            })
            ->whereYear('created_at', $year)
            ->get();

        $totalAttendees = $registrations->sum(function ($r) {
            $adults   = max(0, (int) $r->party_adults);
            $children = max(0, (int) $r->party_children);
            return 1 + $adults + $children;
        });

        // 4) Total check-ins (registrations with checked_in_at set)
        $totalCheckins = EventRegistration::whereHas('event', function ($q) use ($organizer) {
                $q->where('organizer_id', $organizer->id);
            })
            ->whereNotNull('checked_in_at')
            ->whereYear('checked_in_at', $year)
            ->count();

        return response()->json([
            'year'            => $year,
            'events_count'    => $eventsCount,
            'total_earnings'  => $totalEarnings,   // minor units (pence)
            'total_attendees' => $totalAttendees,
            'total_checkins'  => $totalCheckins,
        ]);
    }
}
