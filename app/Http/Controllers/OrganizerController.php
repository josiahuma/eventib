<?php

namespace App\Http\Controllers;

use App\Models\Organizer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContactOrganizerMail;

class OrganizerController extends Controller
{
    public function show($slug)
    {
        $organizer = Organizer::where('slug', $slug)->with('events')->firstOrFail();
        return view('organizers.show', compact('organizer'));
    }

    public function create()
    {
        if (!auth()->user()->is_admin && auth()->user()->organizer) {
            return redirect()->route('organizers.edit', auth()->user()->organizer->slug)
                ->with('info', 'You already have an organizer profile.');
        }

        return view('organizers.create');
    }

    public function store(Request $request)
    {
        // 🔧 Preprocess website before validation
        if ($request->filled('website')) {
            $website = $request->input('website');
            if (!Str::startsWith($website, ['http://', 'https://'])) {
                $request->merge([
                    'website' => 'https://' . $website,
                ]);
            }
        }

        $validated = $request->validate([
            'name'   => 'required|string|max:255',
            'bio'    => 'nullable|string',
            'avatar' => 'nullable|image|max:2048',
            'website'=> 'nullable|url',
        ]);

        $avatarPath = $request->file('avatar')?->store('organizers', 'public');

        Organizer::create([
            'user_id'    => auth()->id(),
            'name'       => $validated['name'],
            'slug'       => Str::slug($validated['name']) . '-' . Str::random(6),
            'bio'        => $validated['bio'] ?? null,
            'avatar_url' => $avatarPath,
            'website'    => $website ?? null,
        ]);

        return redirect()->route('events.create')->with('success', 'Organizer created. You can now create your event.');

    }

    public function follow(Organizer $organizer)
    {
        $user = auth()->user();
        $user->followedOrganizers()->syncWithoutDetaching([$organizer->id]);

        return back()->with('success', 'You are now following this organizer.');
    }

    public function unfollow(Organizer $organizer)
    {
        $user = auth()->user();
        $user->followedOrganizers()->detach($organizer->id);

        return back()->with('success', 'You have unfollowed this organizer.');
    }

    public function edit(Organizer $organizer)
    {
        $this->authorize('update', $organizer); // Optional: Policy
        return view('organizers.edit', compact('organizer'));
    }

    public function update(Request $request, Organizer $organizer)
    {
        $this->authorize('update', $organizer); // Optional: Policy

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'avatar' => 'nullable|image|max:2048',
            'website' => 'nullable|url|max:255',
        ]);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('organizers', 'public');
            $validated['avatar_url'] = $path;
        }

        $organizer->update($validated);

        return redirect()->route('organizers.show', $organizer)->with('success', 'Organizer updated.');

    }

    public function contact(Request $request, Organizer $organizer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'message' => 'required|string|max:2000',
        ]);

        $recipient = $organizer->user->email;
        try {
                Mail::to($recipient)->send(new ContactOrganizerMail(
                    $validated['name'],
                    $validated['email'],
                    $validated['message']
                ));
                \Log::info("Contact email successfully sent.");
            } catch (\Exception $e) {
                \Log::error("Failed to send contact email: " . $e->getMessage());
            }


        return back()->with('success', 'Message sent to the organizer.');
    }

}

