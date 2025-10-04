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
            'event_id'   => ['required','integer'],
            'session_id' => ['nullable','integer'],
        ]);

        $u = $request->user();
        $event = Event::findOrFail($data['event_id']);
        abort_unless(($event->user_id === $u->id) || ($u->is_admin ?? false), 403);

        // 👇 Add this to log each scan attempt
        \Log::info('Check-in payload', [
            'event'   => $data['event_id'],
            'payload' => $data['payload']
        ]);

        // Determine mode: paid vs free (same logic as CheckinsController)
        $isCatPaid    = $event->categories()->where('price', '>', 0)->exists();
        $isLegacyPaid = ($event->ticket_cost ?? 0) > 0;
        $mode = ($isCatPaid || $isLegacyPaid) ? 'paid' : 'free';

        $sessionId = $data['session_id'] ?? null;

        if ($mode === 'paid') {
            return $this->checkInPaid($event->id, $data['payload'], $sessionId);
        }

        return $this->checkInFree($event->id, $data['payload'], $sessionId);
    }

    /**
     * Paid events: resolve to an EventTicket then set checked_in_at.
     */
    protected function checkInPaid(int $eventId, string $payload, ?int $sessionId)
    {
        $ticket = $this->resolveTicketFromPayload($eventId, $payload);

        if (!$ticket) {
            return response()->json(['status' => 'invalid', 'message' => 'Ticket not found'], 404);
        }

        // If session is required, ensure the ticket's registration includes that session
        if ($sessionId) {
            $ok = optional($ticket->registration)
                ->sessions()
                ->where('event_sessions.id', $sessionId)
                ->exists();

            if (!$ok) {
                return response()->json(['status' => 'invalid', 'message' => 'Ticket is not for this session'], 422);
            }
        }

        if (!is_null($ticket->checked_in_at)) {
            return response()->json([
                'status'   => 'already',
                'message'  => 'Already checked in',
                'datetime' => optional($ticket->checked_in_at)->toDateTimeString(),
            ]);
        }

        // Idempotent update
        $updated = 0;
        DB::transaction(function () use ($ticket, &$updated) {
            $updated = EventTicket::where('id', $ticket->id)
                ->whereNull('checked_in_at')
                ->update(['checked_in_at' => now()]);
        });

        if ($updated === 0) {
            return response()->json(['status' => 'already', 'message' => 'Already checked in']);
        }

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

    /**
     * Free events: resolve to an EventRegistration then set checked_in_at.
     */
    protected function checkInFree(int $eventId, string $payload, ?int $sessionId)
    {
        $registration = $this->resolveRegistrationFromPayload($eventId, $payload);

        if (!$registration) {
            return response()->json(['status' => 'invalid', 'message' => 'Registration not found'], 404);
        }

        if ($sessionId) {
            $ok = $registration->sessions()
                ->where('event_sessions.id', $sessionId)
                ->exists();

            if (!$ok) {
                return response()->json(['status' => 'invalid', 'message' => 'Registration is not for this session'], 422);
            }
        }

        if (!is_null($registration->checked_in_at)) {
            return response()->json([
                'status'   => 'already',
                'message'  => 'Already checked in',
                'datetime' => optional($registration->checked_in_at)->toDateTimeString(),
            ]);
        }

        $updated = 0;
        DB::transaction(function () use ($registration, &$updated) {
            $updated = EventRegistration::where('id', $registration->id)
                ->whereNull('checked_in_at')
                ->update(['checked_in_at' => now()]);
        });

        if ($updated === 0) {
            return response()->json(['status' => 'already', 'message' => 'Already checked in']);
        }

        return response()->json([
            'status'  => 'valid',
            'message' => 'Check-in successful',
            'data'    => [
                'name'  => $registration->name,
                'email' => $registration->email,
            ],
        ]);
    }

    public function checkedIn(Request $request, $eventId)
    {
        $u = $request->user();
        $event = Event::findOrFail($eventId);

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




    /**
     * Heuristics to resolve tickets from QR payloads:
     * - Matches full URLs like .../tickets/{ticketId}
     * - Matches ticket serial if your QR encodes the serial directly
     * - As a fallback, numeric payload = ticket id
     */
    protected function resolveTicketFromPayload(int $eventId, string $payload): ?EventTicket
    {
        \Log::info('resolveTicketFromPayload called', [
            'event'   => $eventId,
            'payload' => $payload
        ]);

        // Case 1: "ET|v1|{someId}|{token}"
        if (str_starts_with($payload, 'ET|')) {
            $parts = explode('|', $payload);
            $token = $parts[3] ?? null; // 4th element = token
            if ($token) {
                return EventTicket::with('registration')
                    ->where('event_id', $eventId)
                    ->where('token', $token)   // 👈 match against token
                    ->first();
            }
        }

        return null;
    }
    /**
     * Heuristics to resolve registrations from QR payloads:
     * - Matches full URLs like .../registrations/{id}/pass
     * - Matches new style "FR|v1|{token}"
     * - As a fallback, numeric payload = registration id
     */


    protected function resolveRegistrationFromPayload(int $eventId, string $payload): ?EventRegistration
    {
        if (str_starts_with($payload, 'FR|')) {
            $parts = explode('|', $payload);
            $regId = $parts[3] ?? null;
            $token = $parts[4] ?? null;

            // Prefer qr_token if present
            if ($token) {
                $byToken = EventRegistration::where('event_id', $eventId)
                    ->where('qr_token', $token)
                    ->first();
                if ($byToken) return $byToken;
            }

            // Fallback to ID (legacy QR codes still work)
            if ($regId) {
                return EventRegistration::where('event_id', $eventId)
                    ->where('id', $regId)
                    ->first();
            }
        }

        // Old numeric payload = ID
        if (ctype_digit($payload)) {
            return EventRegistration::where('event_id', $eventId)->find((int)$payload);
        }

        return null;
    }




}
