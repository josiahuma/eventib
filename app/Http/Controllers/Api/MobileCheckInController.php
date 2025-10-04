<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\EventRegistration;

class MobileCheckInController extends Controller
{
    public function checkIn(Request $request)
    {
        $data = $request->validate([
            'payload'    => ['required','string'], // the exact string scanned from the QR
            'event_id'   => ['required','string'], // ✅ allow ULID (public_id)
            'session_id' => ['nullable','integer'],
        ]);

        $u = $request->user();

        // ✅ Find by public_id OR fallback to numeric id
        $event = Event::where('public_id', $data['event_id'])
            ->orWhere('id', $data['event_id'])
            ->firstOrFail();

        abort_unless(($event->user_id === $u->id) || ($u->is_admin ?? false), 403);

        \Log::info('Check-in payload', [
            'event'   => $event->id,
            'payload' => $data['payload']
        ]);

        $isCatPaid    = $event->categories()->where('price', '>', 0)->exists();
        $isLegacyPaid = ($event->ticket_cost ?? 0) > 0;
        $mode = ($isCatPaid || $isLegacyPaid) ? 'paid' : 'free';

        $sessionId = $data['session_id'] ?? null;

        if ($mode === 'paid') {
            return $this->checkInPaid($event->id, $data['payload'], $sessionId);
        }

        return $this->checkInFree($event->id, $data['payload'], $sessionId);
    }

    public function checkedIn(Request $request, $eventId)
    {
        $u = $request->user();

        // ✅ Handle both numeric and ULID
        $event = Event::where('public_id', $eventId)
            ->orWhere('id', $eventId)
            ->firstOrFail();

        abort_unless(($event->user_id === $u->id) || ($u->is_admin ?? false), 403);

        $registrations = \App\Models\EventRegistration::where('event_id', $event->id)
            ->whereNotNull('checked_in_at')
            ->orderByDesc('checked_in_at')
            ->get([
                'id',
                'name',
                'email',
                'mobile',
                'checked_in_at',
            ]);

        return response()->json($registrations);
    }

    // ... keep all your existing helper methods (checkInPaid, checkInFree, etc.)
}
