<?php

namespace App\Http\Controllers;

use App\Mail\RegistrantBulkMail;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class RegistrantEmailController extends Controller
{
    public function create(Event $event)
    {
        abort_unless($event->user_id === Auth::id(), 403);

        $count = $event->registrations()->count();

        return view('registrants.email', [
            'event' => $event,
            'count' => $count,
        ]);
    }

    public function send(Request $request, Event $event)
    {
        abort_unless($event->user_id === Auth::id(), 403);

        $validated = $request->validate([
            'subject' => 'required|string|max:150',
            'message' => 'required|string|max:20000',
        ]);

        // Clean and normalize the Quill HTML
        $messageHtml = trim($validated['message']);
        $messageHtml = preg_replace('/\s+/', ' ', $messageHtml); // collapse whitespace
        $messageHtml = str_replace(['<p><br></p>', '<p></p>'], '', $messageHtml); // remove empty tags

        // Get unique, valid registrant emails
        $emails = $event->registrations()
            ->pluck('email')
            ->filter()        // remove null/empty
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            return back()->with('error', 'No registrants with email addresses found.');
        }

        // Send individually to keep recipients private
        foreach ($emails as $to) {
            Mail::to($to)->send(new RegistrantBulkMail(
                $event,
                $validated['subject'],
                $messageHtml // ✅ pure HTML from Quill, not escaped
            ));
        }

        return redirect()
            ->route('events.registrants', $event)
            ->with('success', 'Your message has been sent to all registrants.');
    }
}
