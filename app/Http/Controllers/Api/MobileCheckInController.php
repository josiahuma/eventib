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
            'payload'    => ['required', 'string'], // full QR payload
            'event_id'   => ['required', 'string'], // can be numeric or ULID
            'session_id' => ['nullable', 'integer'],
        ]);

        $u = $request->user();

        // ✅ Find event by public_id or numeric ID
        $event = Event::where('public_id', $data['event_id'])
            ->orWhere('id', $data['event_id'])
            ->firstOrFail();

        abort_unless(($event->user_id === $u->id) || ($u->is_admin ?? false), 403);

        \Log::info('📸 Mobile check-in started', [
            'event_id' => $event->id,
            'payload'  => $data['payload'],
        ]);

        // Determine if event is paid or free
        $isCatPaid    = $event->categories()->where('price', '>', 0)->exists();
        $isLegacyPaid = ($event->ticket_cost ?? 0) > 0;
        $mode = ($isCatPaid || $isLegacyPaid) ? 'paid' : 'free';

        $sessionId = $data['session_id'] ?? null;

        return $mode === 'paid'
            ? $this->checkInPaid($event->id, $data['payload'], $sessionId)
            : $this->checkInFree($event->id, $data['payload'], $sessionId);
    }

    public function checkedIn(Request $request, $eventId)
    {
        $u = $request->user();

        $event = Event::where('public_id', $eventId)
            ->orWhere('id', $eventId)
            ->firstOrFail();

        abort_unless(($event->user_id === $u->id) || ($u->is_admin ?? false), 403);

        $registrations = EventRegistration::where('event_id', $event->id)
            ->whereNotNull('checked_in_at')
            ->orderByDesc('checked_in_at')
            ->get(['id', 'name', 'email', 'mobile', 'checked_in_at']);

        return response()->json($registrations);
    }

    /** -----------------------------------------------
     *  Paid event check-in
     * ----------------------------------------------- */
    protected function checkInPaid(int $eventId, string $payload, ?int $sessionId)
    {
        $ticket = $this->resolveTicketFromPayload($eventId, $payload);

        if (!$ticket) {
            return response()->json(['status' => 'invalid', 'message' => 'Ticket not found'], 404);
        }

        if ($sessionId) {
            $ok = optional($ticket->registration)
                ->sessions()
                ->where('event_sessions.id', $sessionId)
                ->exists();

            if (!$ok) {
                return response()->json(['status' => 'invalid', 'message' => 'Ticket not for this session'], 422);
            }
        }

        if (!is_null($ticket->checked_in_at)) {
            return response()->json([
                'status'   => 'already',
                'message'  => 'Already checked in',
                'datetime' => optional($ticket->checked_in_at)->toDateTimeString(),
            ]);
        }

        DB::transaction(function () use ($ticket) {
            EventTicket::where('id', $ticket->id)
                ->whereNull('checked_in_at')
                ->update(['checked_in_at' => now()]);
        });

        return response()->json([
            'status'  => 'valid',
            'message' => 'Check-in successful',
            'data'    => [
                'name'  => optional($ticket->registration)->name,
                'email' => optional($ticket->registration)->email,
                'serial'=> $ticket->serial ?? null,
            ],
        ]);
    }

    /** -----------------------------------------------
     *  Free event check-in
     * ----------------------------------------------- */
    protected function checkInFree(int $eventId, string $payload, ?int $sessionId)
    {
        $registration = $this->resolveRegistrationFromPayload($eventId, $payload);

        if (!$registration) {
            return response()->json(['status' => 'invalid', 'message' => 'Registration not found'], 404);
        }

        if ($sessionId) {
            $ok = $registration->sessions()->where('event_sessions.id', $sessionId)->exists();
            if (!$ok) {
                return response()->json(['status' => 'invalid', 'message' => 'Registration not for this session'], 422);
            }
        }

        if (!is_null($registration->checked_in_at)) {
            return response()->json([
                'status'   => 'already',
                'message'  => 'Already checked in',
                'datetime' => optional($registration->checked_in_at)->toDateTimeString(),
            ]);
        }

        DB::transaction(function () use ($registration) {
            EventRegistration::where('id', $registration->id)
                ->whereNull('checked_in_at')
                ->update(['checked_in_at' => now()]);
        });

        return response()->json([
            'status'  => 'valid',
            'message' => 'Check-in successful',
            'data'    => [
                'name'  => $registration->name,
                'email' => $registration->email,
            ],
        ]);
    }

    /** -----------------------------------------------
     *  Helper: resolve ticket payload
     * ----------------------------------------------- */
    protected function resolveTicketFromPayload(int $eventId, string $payload): ?EventTicket
    {
        if (str_starts_with($payload, 'ET|')) {
            $parts = explode('|', $payload);
            $token = $parts[3] ?? null;
            if ($token) {
                return EventTicket::with('registration')
                    ->where('event_id', $eventId)
                    ->where('token', $token)
                    ->first();
            }
        }

        return null;
    }

    /** -----------------------------------------------
     *  Helper: resolve registration payload
     * ----------------------------------------------- */
    protected function resolveRegistrationFromPayload(int $eventId, string $payload): ?EventRegistration
    {
        if (str_starts_with($payload, 'FR|')) {
            $parts = explode('|', $payload);
            $regId = $parts[3] ?? null;
            $token = $parts[4] ?? null;

            if ($token) {
                $byToken = EventRegistration::where('event_id', $eventId)
                    ->where('qr_token', $token)
                    ->first();
                if ($byToken) return $byToken;
            }

            if ($regId) {
                return EventRegistration::where('event_id', $eventId)
                    ->where('id', $regId)
                    ->first();
            }
        }

        if (ctype_digit($payload)) {
            return EventRegistration::where('event_id', $eventId)->find((int) $payload);
        }

        return null;
    }
}
