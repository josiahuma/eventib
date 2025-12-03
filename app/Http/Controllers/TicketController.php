<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventTicket;
use App\Services\DigitalPassService;
use Illuminate\Support\Facades\Http;
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

        // Ensure qr_token exists
        if (empty($registration->qr_token)) {
            $registration->qr_token = \Illuminate\Support\Str::random(40);
            $registration->save();
        }

        $payload = "FR|v1|{$event->public_id}|{$registration->id}|{$registration->qr_token}";
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

        // Create only missing tickets, trim extras; return VALID only
        $validTickets = $this->ensureTickets($event, $registration);

        // Ensure the chosen ticket is valid; otherwise jump to first valid
        if ($ticket->status !== 'valid') {
            $first = $validTickets->first();
            abort_unless($first, 404);
            return redirect()->route('tickets.show', [
                'event'        => $event,
                'registration' => $registration,
                'ticket'       => $first,
            ]);
        }

        $siblings = $validTickets->map(fn($t) => (object)[
            'id'     => $t->id,
            'serial' => $t->serial,
            'index'  => $t->index,
        ]);

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
    private function qrPngBinary(string $payload, int $size = 220): ?string
    {
        try {
            if (class_exists(\Imagick::class)) {
                $renderer  = new ImageRenderer(new RendererStyle($size), new ImagickImageBackEnd('png'));
                $writer    = new Writer($renderer);
                return $writer->writeString($payload);
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
            return $writer->writeString($payload);
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

    // --- ONE ticket -> PDF ---
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

        $qrPath = null;
        if ($png = $this->qrPngBinary($payload, 260)) {
            $dir = public_path('qr-cache');
            if (!is_dir($dir)) @mkdir($dir, 0777, true);
            $qrPath = str_replace('\\', '/', $dir.'/qr-'.Str::random(12).'.png');
            file_put_contents($qrPath, $png);
        }

        $qrSvgDataUri = $qrPath ? null : $this->svgToDataUri($this->qrSvgString($payload, 220));

        $pdf = Pdf::setOption('chroot', public_path())
            ->loadView('tickets.pdf.single', [
                'event'        => $event,
                'ticket'       => $ticket,
                'qrPath'       => $qrPath,
                'qrSvgDataUri' => $qrSvgDataUri,
            ])
            ->setPaper('a4');

        $resp = $pdf->download("ticket-{$ticket->serial}.pdf");
        if ($qrPath) @unlink($qrPath);
        return $resp;
    }

    // --- ALL tickets in a registration -> PDF ---
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

        $qrPaths      = [];
        $qrSvgDataUri = [];

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
        foreach ($qrPaths as $p) if ($p) @unlink($p);
        return $resp;
    }

     /** Organizer: central check-in hub (QR + Digital Pass). */
    public function checkinHub(Event $event)
    {
        $u = Auth::user();
        abort_unless($u && ($event->user_id === $u->id || ($u->is_admin ?? false)), 403);

        $mode = $event->digital_pass_mode ?? 'off'; // off|optional|required

        return view('tickets.checkin-hub', [
            'event' => $event,
            'mode'  => $mode,
        ]);
    }

    /** Organizer: Face ID check-in (placeholder for now). */
    public function faceCheckinPage(Event $event)
    {
        $u = Auth::user();
        abort_unless($u && ($event->user_id === $u->id || ($u->is_admin ?? false)), 403);

        // Event-level digital-pass config (same as voice)
        $mode = $event->digital_pass_mode ?? 'off'; // off|optional|required

        return view('tickets.face-checkin', [
            'event' => $event,
            'mode'  => $mode,
        ]);
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

        // PAID ticket
        if ($prefix === 'ET') {
            if (count($parts) !== 4) return response()->json(['ok'=>false,'reason'=>'Invalid QR format'], 422);
            [, , $eventPid, $token] = $parts;

            if ($eventPid !== $event->public_id) return response()->json(['ok'=>false,'reason'=>'Wrong event'], 422);

            $ticket = EventTicket::where('event_id', $event->id)->where('token', $token)->first();
            if (!$ticket) return response()->json(['ok'=>false,'reason'=>'Ticket not found'], 404);
            if ($ticket->status !== 'valid') return response()->json(['ok'=>false,'reason'=>'Ticket revoked'], 422);

            // Ensure we have the parent registration loaded
            $reg = $ticket->registration; // assumes relation exists

            $already = (bool) $ticket->checked_in_at;
            if (!$already) {
                $now = now();

                // 1) mark the ticket
                $ticket->forceFill([
                    'checked_in_at' => $now,
                    'checked_in_by' => $u->id,
                ])->save();

                // 2) also stamp the parent registration (first time only)
                \App\Models\EventRegistration::where('id', $ticket->registration_id)
                    ->whereNull('checked_in_at')
                    ->update([
                        'checked_in_at' => $now,
                        'checked_in_by' => $u->id,
                    ]);
            }

            // Digital pass flags snapshot
            $usesDp = (bool) ($reg?->uses_digital_pass ?? false);
            $method = $usesDp ? ($reg->digital_pass_method ?: 'any') : null;

            return response()->json([
                'ok'                => true,
                'type'              => 'paid',
                'already'           => $already,
                'serial'            => $ticket->serial,
                'checked_in_at'     => optional($ticket->checked_in_at)->toIso8601String(),
                'uses_digital_pass' => $usesDp,
                'digital_pass_method' => $method,
            ]);

        }

        // FREE pass
        if ($prefix === 'FR') {
            if (count($parts) !== 5) {
                return response()->json(['ok' => false, 'reason' => 'Invalid QR format'], 422);
            }

            [, , $eventPid, $regId, $token] = $parts;

            if ($eventPid !== $event->public_id) {
                return response()->json(['ok' => false, 'reason' => 'Wrong event'], 422);
            }

            // ✅ Match either qr_token or expectedFreePassToken() for backward compatibility
            $reg = EventRegistration::where('event_id', $event->id)
                ->where(function ($q) use ($regId, $token) {
                    $q->where('id', $regId)
                    ->where(function ($qq) use ($token) {
                        $qq->where('qr_token', $token)
                            ->orWhereRaw('BINARY `qr_token` = ?', [$token]);
                    });
                })
                ->first();

            // Fallback for old tokens
            if (! $reg && $regId) {
                $fallback = EventRegistration::find($regId);
                if ($fallback && hash_equals($fallback->expectedFreePassToken(), $token)) {
                    $reg = $fallback;
                }
            }

            if (! $reg) {
                return response()->json(['ok' => false, 'reason' => 'Invalid pass token'], 422);
            }

            $already = (bool) $reg->checked_in_at;
            if (! $already) {
                $reg->forceFill([
                    'checked_in_at' => now(),
                    'checked_in_by' => $u->id,
                ])->save();
            }

            $party = 1
                + (int) ($reg->party_adults ?? 0)
                + (int) ($reg->party_children ?? 0);

            $usesDp = (bool) ($reg->uses_digital_pass ?? false);
            $method = $usesDp ? ($reg->digital_pass_method ?: 'any') : null;

            return response()->json([
                'ok'                 => true,
                'type'               => 'free',
                'already'            => $already,
                'party'              => $party,
                'checked_in_at'      => optional($reg->checked_in_at)->toIso8601String(),
                'uses_digital_pass'  => $usesDp,
                'digital_pass_method'=> $method,
            ]);

        }
    }

    /** Organizer: voice-based Digital Pass check-in page. */
    /** Organizer: voice-based Digital Pass check-in page. */
    public function digitalCheckinPage(Event $event)
    {
        $u = Auth::user();
        abort_unless($u && ($event->user_id === $u->id || ($u->is_admin ?? false)), 403);

        // Event-level digital-pass config
        $mode = $event->digital_pass_mode ?? 'off'; // off|optional|required

        // ✅ See if this event actually has any digital-pass attendees
        $hasDigitalPassAttendees = EventRegistration::where('event_id', $event->id)
            ->where('uses_digital_pass', true)
            ->whereNotIn('status', ['canceled', 'cancelled', 'failed'])
            ->exists();

        return view('tickets.digital-checkin', [
            'event'                   => $event,
            'mode'                    => $mode,
            'hasDigitalPassAttendees' => $hasDigitalPassAttendees,
        ]);
    }


    /** Organizer: accept audio sample and match against digital-pass attendees. */
    public function digitalCheckinVoice(Request $request, Event $event)
    {
        $u = Auth::user();
        abort_unless($u && ($event->user_id === $u->id || ($u->is_admin ?? false)), 403);

        $data = $request->validate([
            'audio' => 'required|string', // data:audio/webm;base64,....
        ]);

        $dataUrl = $data['audio'];

        if (! str_starts_with($dataUrl, 'data:')) {
            return response()->json([
                'ok'    => false,
                'error' => 'Invalid audio payload.',
            ], 422);
        }

        // Split "data:audio/webm;base64,AAAA..."
        [, $base64] = explode(',', $dataUrl, 2);
        $audioBytes = base64_decode($base64, true);

        if ($audioBytes === false) {
            return response()->json([
                'ok'    => false,
                'error' => 'Could not decode audio.',
            ], 422);
        }

        // 1) Get embedding for the *probe* audio
        $response = Http::attach(
            'audio',
            $audioBytes,
            'probe.webm'
        )->post(config('services.voice.url') . '/embed');

        if (! $response->ok()) {
            return response()->json([
                'ok'    => false,
                'error' => 'Voice service failed. Please try again.',
            ], 500);
        }

        $probeEmbedding = $response->json('embedding');
        if (! is_array($probeEmbedding) || empty($probeEmbedding)) {
            return response()->json([
                'ok'    => false,
                'error' => 'Invalid embedding from voice service.',
            ], 500);
        }

        // 2) Load all registrations for this event that opted in and have a snapshot
        $regs = EventRegistration::query()
            ->where('event_id', $event->id)
            ->where('uses_digital_pass', true)
            ->whereNotIn('status', ['canceled', 'cancelled', 'failed'])
            ->with(['sessions', 'user.digitalPass'])
            ->get();

        if ($regs->isEmpty()) {
            return response()->json([
                'ok'    => false,
                'error' => 'No digital-pass attendees found for this event.',
            ], 404);
        }

        // 3) Compare embeddings & find best match
        // NOTE: we'll start SUPER relaxed so we can see scores.
        $threshold = 0.65;   // <-- for debugging: literally accept ANY score
        $scores    = [];
        $anySnapshots = false;

        foreach ($regs as $reg) {
            // Prefer frozen snapshot at registration time…
            $snapshot = $reg->voice_embedding_snapshot;

            // …but if it’s missing for some reason, fall back to the user’s current pass
            if (! $snapshot && $reg->user && $reg->user->digitalPass) {
                $snapshot = $reg->user->digitalPass->voice_embedding;
            }

            // If stored as JSON string, decode
            if (is_string($snapshot)) {
                $decoded = json_decode($snapshot, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $snapshot = $decoded;
                }
            }

            if (! is_array($snapshot) || empty($snapshot)) {
                continue;
            }

            $anySnapshots = true;

            $sim = $this->cosineSimilarity($probeEmbedding, $snapshot);
            if ($sim === null) {
                continue;
            }

            $scores[] = [
                'reg'   => $reg,
                'score' => $sim,
            ];
        }

        if (!$anySnapshots) {
            return response()->json([
                'ok'    => false,
                'error' => 'No usable voice snapshots found for any attendee.',
            ], 404);
        }

        if (empty($scores)) {
            return response()->json([
                'ok'    => false,
                'error' => 'No comparable embeddings (dimension mismatch).',
            ], 404);
        }

        usort($scores, fn ($a, $b) => $b['score'] <=> $a['score']);
        $best = $scores[0];

        $reg   = $best['reg'];
        $score = $best['score'];

        // ❗ Enforce the threshold: ONLY accept if score is high enough
        if ($score < $threshold) {
            return response()->json([
                'ok'    => false,
                'error' => 'No confident match. Please try again or use QR.',
                'best'  => round($score, 3),
                'name'  => $reg->name,
                'email' => $reg->email,
            ]);
        }

        // 4) Mark as checked-in (idempotent)
        $already = (bool) $reg->checked_in_at;
        if (! $already) {
            $reg->forceFill([
                'checked_in_at' => now(),
                'checked_in_by' => $u->id,
            ])->save();
        }

        $sessions = $reg->sessions?->pluck('session_name')->join(', ') ?: 'All sessions';

        return response()->json([
            'ok'            => true,
            'already'       => $already,
            'name'          => $reg->name,
            'email'         => $reg->email,
            'party'         => 1 + (int) $reg->party_adults + (int) $reg->party_children,
            'sessions'      => $sessions,
            'score'         => round($score, 3),
            'raw_score'     => $score,
            'checked_in_at' => optional($reg->checked_in_at)->toIso8601String(),
        ]);

    }





    /**
     * Idempotently create/trim VALID tickets for a paid registration and return them (ordered).
     * - Only runs when registration status is paid/complete.
     * - Creates missing tickets with status=valid.
     * - Revokes extras (status=revoked) so they never show/scan.
     */
    private function ensureTickets(Event $event, EventRegistration $registration)
    {
        $status = strtolower((string) ($registration->status ?? ''));
        $paidStates = ['paid','complete','completed','succeeded'];

        // For unpaid/cancelled/failed – never create; just show existing VALID ones.
        if (!in_array($status, $paidStates, true)) {
            return $registration->tickets()
                ->where('status', 'valid')
                ->orderBy('index')
                ->get();
        }

        // Items (category mode) and expected counts
        $items         = $registration->items()->orderBy('id')->get(['event_ticket_category_id','quantity']);
        $useCategories = $items->count() > 0;

        $expectedFromReg   = max(0, (int) ($registration->quantity ?? 0));
        $expectedFromItems = (int) $items->sum('quantity');

        // Choose a sane expected total:
        // - categories: prefer the smaller of (reg.qty, items sum). If reg.qty is 0, fall back to items sum.
        // - legacy: use reg.qty (>=1).
        if ($useCategories) {
            $expectedTotal = $expectedFromReg > 0
                ? min($expectedFromReg, $expectedFromItems)
                : $expectedFromItems;
            $expectedTotal = max(1, $expectedTotal);
        } else {
            $expectedTotal = max(1, $expectedFromReg);
        }

        // Current VALID set
        $valid = $registration->tickets()->where('status', 'valid')->orderBy('index')->get();

        // Global next index (monotonic across the registration)
        $maxIndex  = (int) ($registration->tickets()->max('index') ?? -1);
        $nextIndex = function () use (&$maxIndex) { $maxIndex++; return $maxIndex; };

        if ($useCategories) {
            // Expected per category
            $expectedByCat = $items->groupBy('event_ticket_category_id')
                ->map(fn($g) => (int) $g->sum('quantity'));

            // Existing VALID per category
            $validByCat = $valid->groupBy('event_ticket_category_id')->map->count();

            // Create missing, but never exceed expectedTotal globally
            foreach ($expectedByCat as $catId => $need) {
                $have       = (int) ($validByCat[$catId] ?? 0);
                $remaining  = $expectedTotal - $valid->count();
                if ($remaining <= 0) break;

                $toMake = min(max(0, $need - $have), $remaining);
                for ($j = 0; $j < $toMake; $j++) {
                    $i      = $nextIndex();
                    $token  = \App\Models\EventTicket::makeToken($event->public_id, $registration->id, $i);
                    $serial = \App\Models\EventTicket::makeSerial($registration->id, $i);

                    if (!\App\Models\EventTicket::where('token', $token)->exists()) {
                        $registration->tickets()->create([
                            'event_id'                 => $event->id,
                            'event_ticket_category_id' => $catId,
                            'index'                    => $i,
                            'serial'                   => $serial,
                            'token'                    => $token,
                            'status'                   => 'valid',
                        ]);
                    }
                }

                // refresh valid + per-cat counts for next loop
                $valid     = $registration->tickets()->where('status', 'valid')->orderBy('index')->get();
                $validByCat = $valid->groupBy('event_ticket_category_id')->map->count();
            }
        } else {
            // Legacy single price: create up to expectedTotal
            $have   = $valid->count();
            $toMake = max(0, $expectedTotal - $have);

            for ($k = 0; $k < $toMake; $k++) {
                $i      = $nextIndex();
                $token  = \App\Models\EventTicket::makeToken($event->public_id, $registration->id, $i);
                $serial = \App\Models\EventTicket::makeSerial($registration->id, $i);

                if (!\App\Models\EventTicket::where('token', $token)->exists()) {
                    $registration->tickets()->create([
                        'event_id' => $event->id,
                        'index'    => $i,
                        'serial'   => $serial,
                        'token'    => $token,
                        'status'   => 'valid',
                    ]);
                }
            }

            $valid = $registration->tickets()->where('status', 'valid')->orderBy('index')->get();
        }

        // Final guard: trim extras globally to match expectedTotal exactly
        if ($valid->count() > $expectedTotal) {
            $extras = $valid->sortByDesc('index')->slice($expectedTotal); // drop newest first
            foreach ($extras as $t) {
                $t->update(['status' => 'revoked']);
            }
            $valid = $registration->tickets()->where('status','valid')->orderBy('index')->get();
        }

        return $valid;
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

    private function cosineSimilarity(array $a, array $b): ?float
    {
        $lenA = count($a);
        $lenB = count($b);

        if ($lenA === 0 || $lenB === 0 || $lenA !== $lenB) {
            return null;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $lenA; $i++) {
            $va = (float) $a[$i];
            $vb = (float) $b[$i];

            $dot   += $va * $vb;
            $normA += $va * $va;
            $normB += $vb * $vb;
        }

        if ($normA <= 0 || $normB <= 0) {
            return null;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

}
