{{-- resources/views/emails/tickets-issued.blade.php --}}
@php($cur = strtoupper($event->ticket_currency ?? 'GBP'))
@php($symbols = ['GBP'=>'£','USD'=>'$','EUR'=>'€','NGN'=>'₦','KES'=>'KSh','GHS'=>'₵','ZAR'=>'R','CAD'=>'$','AUD'=>'$'])
@php($sym = $symbols[$cur] ?? '')
@php($qty = max(1, (int)($registration->quantity ?? 1)))

<div style="font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;">
    <h2 style="margin:0 0 12px 0;">Your tickets — {{ $event->name }}</h2>
    @if($event->location)
        <div style="color:#6b7280; margin-bottom:8px;">{{ $event->location }}</div>
    @endif

    <p style="margin:12px 0;">
        You purchased <strong>{{ $qty }}</strong> ticket{{ $qty===1?'':'s' }}.
        You can open all tickets here:
        <a href="{{ $listUrl }}">Open tickets</a> or
        <a href="{{ $pdfUrl }}">download PDF</a>.
    </p>

    @foreach($tickets as $t)
        <div style="border:1px solid #e5e7eb; border-radius:12px; padding:12px; margin:12px 0;">
            <div style="font-weight:600;">Ticket #{{ $t->serial }}</div>
            <div style="margin-top:6px;">
                {{-- Inline SVG QR --}}
                {!! $qr[$t->id] !!}
            </div>
            <div style="color:#6b7280; font-size:12px; margin-top:6px;">
                Show this QR at the door.
            </div>
            <div style="margin-top:6px;">
                <a href="{{ route('tickets.show', [$event, $registration, $t]) }}">Open this ticket</a>
            </div>
        </div>
    @endforeach
</div>
