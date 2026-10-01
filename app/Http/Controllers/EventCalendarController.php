<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Support\Carbon;

class EventCalendarController extends Controller
{
    public function download(Event $event, EventSession $session)
    {
        abort_if($event->is_disabled || (int)$session->event_id !== (int)$event->id, 404);
        $start = Carbon::parse($session->session_date, config('app.timezone'))->utc()->format('Ymd\THis\Z');
        $escape = fn ($value) => str_replace(["\\", "\r\n", "\r", "\n", ';', ','], ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'], preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$value));
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Eventib//Event Calendar//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'BEGIN:VEVENT', 'UID:'.$event->public_id.'-'.$session->id.'@eventib', 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$start, 'SUMMARY:'.$escape($event->name.' — '.$session->session_name),
            'LOCATION:'.$escape($event->location),
            'DESCRIPTION:'.$escape(\Illuminate\Support\Str::limit(html_entity_decode(strip_tags($event->description ?? ''), ENT_QUOTES, 'UTF-8'), 2000)),
            'URL:'.route('events.show', $event), 'END:VEVENT', 'END:VCALENDAR'];
        $folded = [];
        foreach ($lines as $line) {
            $first = true;
            while (strlen($line) > ($first ? 75 : 74)) {
                $chunk = mb_strcut($line, 0, $first ? 75 : 74, 'UTF-8');
                $folded[] = ($first ? '' : ' ').$chunk;
                $line = substr($line, strlen($chunk));
                $first = false;
            }
            $folded[] = ($first ? '' : ' ').$line;
        }
        return response(implode("\r\n", $folded)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="eventib-calendar.ics"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
