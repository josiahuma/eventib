{{-- resources/views/tickets/pdf/single.blade.php --}}
@php($font = "font-family: DejaVu Sans, sans-serif;")
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ticket #{{ $ticket->serial }}</title>
    <style>
        * { {{ $font }} }
        .wrap { border:1px solid #ddd; border-radius:10px; padding:14px; }
        .small { color:#555; font-size:12px; }
        .qr { text-align:center; margin-top:10px; }
        img.qrimg { width:220px; height:220px; }
    </style>
</head>
<body>
<div class="wrap">
    <h3>Ticket #{{ $ticket->serial }}</h3>
    <div><strong>{{ $event->name }}</strong></div>
    @if($event->location)<div class="small">{{ $event->location }}</div>@endif

    <div class="qr">
        @if(!empty($qrPath))
            <img class="qrimg" src="{{ $qrPath }}" alt="QR code">
        @elseif(!empty($qrSvgDataUri))
            <img class="qrimg" src="{{ $qrSvgDataUri }}" alt="QR code">
        @else
            <div class="small">QR unavailable</div>
        @endif
    </div>

    <div class="small" style="text-align:center; margin-top:6px;">Show this QR at the door.</div>
</div>
</body>
</html>
