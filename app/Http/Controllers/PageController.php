<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactMessageMail;


class PageController extends Controller
{
    public function how()
    {
        return view('static.how');
    }

    public function pricing()
    {
        return view('static.pricing');
    }

      public function about()
    {
        return view('static.about');
    }

    public function contact()
    {
        return view('static.contact');
    }

    public function terms()
    {
        return view('static.terms');
    }
    public function privacy()
    {
        return view('static.privacy');
    }

    public function cookies()
    {
        return view('static.cookies');
    }

    public function organizerapp()
    {
        return view('static.organizer-app');
    }

    public function contactSubmit(Request $request)
    {
        // simple honeypot
        if ($request->filled('website')) {
            return back()->with('success', 'Thanks!'); // silently ignore bots
        }

        $data = $request->validate([
            'name'    => 'required|string|max:120',
            'email'   => 'required|email',
            'subject' => 'required|string|max:160',
            'message' => 'required|string|max:5000',
        ]);

        Mail::to('info@eventib.com')->send(new ContactMessageMail($data));

        return back()->with('success', 'Thank you! Your message has been sent. We’ll get back to you shortly.');
    }
}
