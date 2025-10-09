<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $event->name }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; background-color: #f9fafb; margin: 0; padding: 0;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px;">
    <tr>
        <td style="padding: 20px;">
            <p style="font-size: 14px; color: #374151;">
                Message from <strong>{{ $organizerName }}</strong>
                regarding <strong>{{ $event->name }}</strong>:
            </p>

            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 12px 0;">

            <div style="font-size: 15px; line-height: 1.6; color: #111827;">
                {!! $messageHtml !!}
            </div>

            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">

            <p style="font-size: 12px; color: #6b7280;">
                You received this because you registered for <strong>{{ $event->name }}</strong>.
            </p>

            @if($organizerEmail)
                <p style="font-size: 12px; color: #6b7280;">
                    To unsubscribe from future emails about this event, please contact the event organizer at
                    <a href="mailto:{{ $organizerEmail }}" style="color:#2563eb; text-decoration:none;">
                        {{ $organizerEmail }}
                    </a>.
                </p>
            @else
                <p style="font-size: 12px; color: #6b7280;">
                    To unsubscribe from future emails about this event, please contact the event organizer.
                </p>
            @endif
        </td>
    </tr>
</table>
</body>
</html>
