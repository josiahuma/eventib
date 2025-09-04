<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventTicket;
use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckinsController extends Controller
{
    public function index(Event $event, Request $request)
    {
        // Owner or admin
        $u = Auth::user();
        abort_unless($u && ($event->user_id === $u->id || ($u->is_admin ?? false)), 403);

        $mode         = (($event->ticket_cost ?? 0) > 0) ? 'paid' : 'free';
        $filterStatus = $request->query('status', 'checked'); // ui filter: 'checked'|'all'
        $onlyChecked  = $filterStatus === 'checked';
        $term         = trim((string) $request->query('q', ''));
        $sid          = $request->query('session');           // event_session.id

        // For dropdown
        $sessions = $event->sessions()->orderBy('session_date')->get(['id','session_name','session_date']);

        if ($mode === 'paid') {
            $base = EventTicket::with([
                    'registration:id,event_id,name,email',
                    'registration.sessions:id,session_name,session_date',
                    'checker:id,name',
                ])
                ->where('event_id', $event->id);

            if ($onlyChecked) {
                $base->whereNotNull('checked_in_at');
            }

            if ($term !== '') {
                $base->where(function ($q) use ($term) {
                    $q->where('serial', 'like', "%{$term}%")
                      ->orWhereHas('registration', function ($qr) use ($term) {
                          $qr->where('name', 'like', "%{$term}%")
                             ->orWhere('email', 'like', "%{$term}%");
                      });
                });
            }

            if ($sid) {
                $base->whereHas('registration.sessions', fn($q) => $q->where('event_sessions.id', $sid));
            }

            $items   = $base->orderByDesc('checked_in_at')->orderBy('id')->paginate(20)->withQueryString();
            $total   = EventTicket::where('event_id', $event->id)->count();
            $checked = EventTicket::where('event_id', $event->id)->whereNotNull('checked_in_at')->count();
        } else {
            $base = EventRegistration::with([
                    'sessions:id,session_name,session_date',
                    'checker:id,name',
                ])
                ->where('event_id', $event->id);

            if ($onlyChecked) {
                $base->whereNotNull('checked_in_at');
            }

            if ($term !== '') {
                $base->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                      ->orWhere('email', 'like', "%{$term}%");
                });
            }

            if ($sid) {
                $base->whereHas('sessions', fn($q) => $q->where('event_sessions.id', $sid));
            }

            $items   = $base->orderByDesc('checked_in_at')->orderBy('id')->paginate(20)->withQueryString();
            $total   = EventRegistration::where('event_id', $event->id)->count();
            $checked = EventRegistration::where('event_id', $event->id)->whereNotNull('checked_in_at')->count();
        }

        $percent = $total > 0 ? round(($checked / $total) * 100) : 0;

        /**
         * Attendance checked-in (people):
         * - paid: count of checked-in tickets
         * - free: sum party size (1 + adults + children) of registrations that have a checked-in ticket
         *   (Apply the same search and session filters, but do NOT apply the UI “checked/all” toggle here,
         *    because this KPI explicitly means "checked-in".)
         */
        // Attendance checked-in (people)
        if ($mode === 'paid') {
            // Paid events: 1 ticket = 1 person
            $attQ = EventTicket::query()
                ->where('event_id', $event->id)
                ->whereNotNull('checked_in_at');

            if ($term !== '') {
                $attQ->where(function ($q) use ($term) {
                    $q->where('serial', 'like', "%{$term}%")
                    ->orWhereHas('registration', fn($qr) => $qr
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%"));
                });
            }
            if ($sid) {
                $attQ->whereHas('registration.sessions', fn($q) => $q->where('event_sessions.id', $sid));
            }

            $attendanceCheckedIn = $attQ->count();
        } else {
            // Free events: sum party sizes of registrations that are checked in
            $regsBase = EventRegistration::query()->where('event_id', $event->id);

            if ($term !== '') {
                $regsBase->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
                });
            }
            if ($sid) {
                $regsBase->whereHas('sessions', fn($q) => $q->where('event_sessions.id', $sid));
            }

            $attendanceCheckedIn = (clone $regsBase)
                ->whereNotNull('checked_in_at')            // <-- key change
                ->get(['party_adults', 'party_children'])
                ->sum(fn($r) => 1
                    + max(0, (int) $r->party_adults)
                    + max(0, (int) $r->party_children));
        }

        return view('tickets.checkins', [
            'event'               => $event,
            'mode'                => $mode,
            'items'               => $items,
            'sessions'            => $sessions,
            'status'              => $filterStatus, // ui toggle
            'term'                => $term,
            'sid'                 => $sid,
            'total'               => $total,
            'checked'             => $checked,
            'percent'             => $percent,
            'attendanceCheckedIn' => $attendanceCheckedIn,
        ]);
    }
}
