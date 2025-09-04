<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;




class TicketController extends Controller
{
    /** Tickets home (legacy). Prefer /my-tickets, but we keep this for old links. */
    public function index(Request $request)
    {
        $u = Auth::user();

        $registrations = EventRegistration::with([
                'event:id,public_id,name,ticket_currency,banner_url,avatar_url,ticket_cost',
                'tickets:id,registration_id,index,serial,token,status,checked_in_at',
                'sessions:id,session_name,session_date',
            ])
            ->where(function ($q) use ($u) {
                $q->where('user_id', $u->id)
                  ->orWhere(fn ($qq) => $qq->whereNull('user_id')->where('email', $u->email));
            })
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('tickets.index', compact('registrations'));
    }

    /** Jump straight to the FIRST ticket QR (creates tickets if missing). */
    public function first(Event $event, EventRegistration $registration)
    {
        abort_unless($registration->event_id === $event->id, 404);
        $this->authorizeRegistration($registration);

        $tickets = $this->ensureTickets($event, $registration);
        $first   = $tickets->first();
        abort_unless($first, 404);

        return redirect()->route('tickets.show', [
            'event'        => $event,
            'registration' => $registration,
            'ticket'       => $first,
        ]);
    }

    /** FREE event “pass” QR (whole party on one code). */
    public function pass(Event $event, EventRegistration $registration)
    {
        abort_unless($registration->event_id === $event->id, 404);
        $this->authorizeRegistration($registration);

        $payload = $registration->freePassPayload(); // your helper on the model
        $party   = 1
            + (int)($registration->party_adults ?? 0)
            + (int)($registration->party_children ?? 0);

        $qrSvg = null;
        if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(240)
                ->generate($payload);
        }

        return view('tickets.free-pass', [
            'event'        => $event,
            'registration' => $registration->load('sessions'),
            'payload'      => $payload,
            'party'        => $party,
            'qrSvg'        => $qrSvg,
        ]);
    }

    /** View ONE paid ticket QR (with prev/next switcher). */
    public function show(Event $event, EventRegistration $registration, EventTicket $ticket, Request $request)
    {
        abort_unless(
            $registration->event_id === $event->id &&
            $ticket->event_id === $event->id &&
            $ticket->registration_id === $registration->id,
            404
        );
        $this->authorizeRegistration($registration);

        // Idempotently make sure the whole set exists
        $this->ensureTickets($event, $registration);
        $siblings = $registration->tickets()->orderBy('index')->get(['id','serial','index']);

        // QR payload (what the scanner expects)
        $qrPayload = "ET|v1|{$event->public_id}|{$ticket->token}";

        $qrSvg = null;
        if (class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class)) {
            $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
                ->size(240)
                ->generate($qrPayload);
        }

        return view('tickets.show', [
            'event'        => $event,
            'registration' => $registration->load('sessions'),
            'ticket'       => $ticket,
            'siblings'     => $siblings,
            'qrPayload'    => $qrPayload,
            'qrSvg'        => $qrSvg,
        ]);
    }

    /** Prefer PNG (GD/Imagick) for DomPDF; return data URI or null */
    // Return raw PNG bytes using BaconQrCode (GD or Imagick). Null on failure.
    /** Try to produce a PNG (binary) using Imagick backend. Returns raw bytes or null. */
    private function qrPngBinary(string $payload, int $size = 220): ?string
    {
        try {
            if (class_exists(\Imagick::class)) {
                $renderer  = new ImageRenderer(new RendererStyle($size), new ImagickImageBackEnd('png'));
                $writer    = new Writer($renderer);
                return $writer->writeString($payload); // PNG bytes
            }
        } catch (\Throwable $e) {
            \Log::error('QR PNG error: '.$e->getMessage());
        }
        return null;
    }

    /** SVG string (works without any PHP extensions). */
    private function qrSvgString(string $payload, int $size = 220): ?string
    {
        try {
            $renderer = new ImageRenderer(new RendererStyle($size), new SvgImageBackEnd());
            $writer   = new Writer($renderer);
            return $writer->writeString($payload); // raw <svg>...</svg>
        } catch (\Throwable $e) {
            \Log::error('QR SVG error: '.$e->getMessage());
            return null;
        }
    }

    /** Helper: base64 data URI from SVG string. */
    private function svgToDataUri(?string $svg): ?string
    {
        if (!$svg) return null;
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    // --- ONE ticket -> PDF (writes PNG to a temp file & deletes it after) ---
    public function pdfTicket(Event $event, EventRegistration $registration, EventTicket $ticket)
    {
        abort_unless(
            $registration->event_id === $event->id &&
            $ticket->event_id === $event->id &&
            $ticket->registration_id === $registration->id,
            404
        );
        $this->authorizeRegistration($registration);

        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return back()->with('error', 'PDF generator not installed.');
        }

        $payload = "ET|v1|{$event->public_id}|{$ticket->token}";

        // Prefer PNG via Imagick (save under /public to satisfy DomPDF chroot)
        $qrPath = null;
        if ($png = $this->qrPngBinary($payload, 260)) {
            $dir = public_path('qr-cache');
            if (!is_dir($dir)) @mkdir($dir, 0777, true);
            $qrPath = str_replace('\\', '/', $dir.'/qr-'.Str::random(12).'.png');
            file_put_contents($qrPath, $png);
        }

        // Fallback to SVG-as-image (data URI)
        $qrSvgDataUri = $qrPath ? null : $this->svgToDataUri($this->qrSvgString($payload, 220));

        $pdf = Pdf::setOption('chroot', public_path())
            ->loadView('tickets.pdf.single', [
                'event'        => $event,
                'ticket'       => $ticket,
                'qrPath'       => $qrPath,       // absolute file path under /public
                'qrSvgDataUri' => $qrSvgDataUri, // data:image/svg+xml;base64,...
            ])
            ->setPaper('a4');

        $resp = $pdf->download("ticket-{$ticket->serial}.pdf");
        if ($qrPath) @unlink($qrPath); // cleanup
        return $resp;
    }


    // --- ALL tickets in a registration -> PDF (writes multiple PNGs) ---
    public function pdfRegistration(Event $event, EventRegistration $registration)
    {
        abort_unless($registration->event_id === $event->id, 404);
        $this->authorizeRegistration($registration);

        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return back()->with('error', 'PDF generator not installed.');
        }

        $tickets = $this->ensureTickets($event, $registration)->values();

        $dir = public_path('qr-cache');
        if (!is_dir($dir)) @mkdir($dir, 0777, true);

        $qrPaths      = []; // [id => absolute path under /public]
        $qrSvgDataUri = []; // [id => data:image/svg+xml;base64,...]

        foreach ($tickets as $t) {
            $payload = "ET|v1|{$event->public_id}|{$t->token}";
            if ($png = $this->qrPngBinary($payload, 220)) {
                $path = str_replace('\\', '/', $dir.'/qr-'.Str::random(12).'.png');
                file_put_contents($path, $png);
                $qrPaths[$t->id] = $path;
                $qrSvgDataUri[$t->id] = null;
            } else {
                $qrPaths[$t->id] = null;
                $qrSvgDataUri[$t->id] = $this->svgToDataUri($this->qrSvgString($payload, 200));
            }
        }

        $pdf = Pdf::setOption('chroot', public_path())
            ->loadView('tickets.pdf.registration', [
                'event'        => $event,
                'tickets'      => $tickets,
                'qrPaths'      => $qrPaths,
                'qrSvgDataUri' => $qrSvgDataUri,
            ])
            ->setPaper('a4');

        $resp = $pdf->download("tickets-{$registration->id}.pdf");

        // cleanup
        foreach ($qrPaths as $p) if ($p) @unlink($p);

        return $resp;
    }




    /** Organizer: camera/scan page. */
    public function scanPage(Event $event)
    {
        $u = Auth::user();
        abort_unless($u && ($event->user_id === $u->id || ($u->is_admin ?? false)), 403);
        return view('tickets.scan', compact('event'));
    }

    /** Organizer: validate QR + mark checked-in (works for paid + free). */
    public function scanValidate(Request $request, Event $event)
    {
        $u = Auth::user();
        abort_unless($u && ($event->user_id === $u->id || ($u->is_admin ?? false)), 403);

        $payload = (string) $request->input('payload', '');
        $parts = explode('|', $payload);
        if (count($parts) < 4) {
            return response()->json(['ok' => false, 'reason' => 'Invalid QR format'], 422);
        }

        [$prefix, $ver] = [$parts[0], $parts[1]];
        if ($ver !== 'v1') return response()->json(['ok'=>false,'reason'=>'Unsupported QR version'], 422);

        // PAID
        if ($prefix === 'ET') {
            if (count($parts) !== 4) return response()->json(['ok'=>false,'reason'=>'Invalid QR format'], 422);
            [, , $eventPid, $token] = $parts;

            if ($eventPid !== $event->public_id) return response()->json(['ok'=>false,'reason'=>'Wrong event'], 422);

            $ticket = EventTicket::where('event_id', $event->id)->where('token', $token)->first();
            if (!$ticket) return response()->json(['ok'=>false,'reason'=>'Ticket not found'], 404);
            if ($ticket->status !== 'valid') return response()->json(['ok'=>false,'reason'=>'Ticket revoked'], 422);

            $already = (bool) $ticket->checked_in_at;
            if (!$already) {
                $ticket->forceFill(['checked_in_at' => now(), 'checked_in_by' => $u->id])->save();
            }

            return response()->json([
                'ok' => true, 'type' => 'paid', 'already' => $already,
                'serial' => $ticket->serial,
                'checked_in_at' => optional($ticket->checked_in_at)->toIso8601String(),
            ]);
        }

        // FREE
        if ($prefix === 'FR') {
            if (count($parts) !== 5) return response()->json(['ok'=>false,'reason'=>'Invalid QR format'], 422);
            [, , $eventPid, $regId, $token] = $parts;
            if ($eventPid !== $event->public_id) return response()->json(['ok'=>false,'reason'=>'Wrong event'], 422);

            $reg = EventRegistration::where('id', $regId)->where('event_id', $event->id)->first();
            if (!$reg) return response()->json(['ok'=>false,'reason'=>'Registration not found'], 404);

            if (!hash_equals($reg->expectedFreePassToken(), $token)) {
                return response()->json(['ok'=>false,'reason'=>'Invalid pass token'], 422);
            }

            $already = (bool) $reg->checked_in_at;
            if (!$already) {
                $reg->forceFill(['checked_in_at' => now(), 'checked_in_by' => $u->id])->save();
            }

            $party = 1 + (int)($reg->party_adults ?? 0) + (int)($reg->party_children ?? 0);

            return response()->json([
                'ok' => true, 'type' => 'free', 'already' => $already, 'party' => $party,
                'checked_in_at' => optional($reg->checked_in_at)->toIso8601String(),
            ]);
        }

        return response()->json(['ok' => false, 'reason' => 'Unknown QR type'], 422);
    }

    /** Idempotently create tickets for a paid registration. */
    private function ensureTickets(Event $event, EventRegistration $registration)
    {
        $qty = max(1, (int)($registration->quantity ?? 1));
        $existing = $registration->tickets()->count();

        for ($i = $existing; $i < $qty; $i++) {
            $token  = EventTicket::makeToken($event->public_id, $registration->id, $i);
            $serial = EventTicket::makeSerial($registration->id, $i);
            if (!EventTicket::where('token', $token)->exists()) {
                $registration->tickets()->create([
                    'event_id' => $event->id,
                    'index'    => $i,
                    'serial'   => $serial,
                    'token'    => $token,
                ]);
            }
        }

        return $registration->tickets()->orderBy('index')->get();
    }

    /** Registration ownership check (by user_id OR guest email). */
    private function authorizeRegistration(EventRegistration $registration): void
    {
        $u = Auth::user();
        $ownsById = !empty($registration->user_id) && $registration->user_id === $u->id;
        $ownsByEmail = $registration->user_id === null
            && strcasecmp((string)$registration->email, (string)$u->email) === 0;

        abort_unless($ownsById || $ownsByEmail, 403);
    }
}
