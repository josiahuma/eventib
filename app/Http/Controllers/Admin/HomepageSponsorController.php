<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageSponsor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HomepageSponsorController extends Controller
{
    public function index()
    {
        $sponsors = HomepageSponsor::orderBy('priority')->orderByDesc('id')->paginate(20);

        return view('admin.homepage_sponsors.index', compact('sponsors'));
    }

    public function create()
    {
        $sponsor = new HomepageSponsor();

        return view('admin.homepage_sponsors.form', [
            'sponsor' => $sponsor,
            'mode'    => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('sponsors/logos', 'public');
        }

        if ($request->hasFile('background')) {
            $data['background_path'] = $request->file('background')->store('sponsors/backgrounds', 'public');
        }

        HomepageSponsor::create($data);

        return redirect()
            ->route('admin.homepage-sponsors.index')
            ->with('status', 'Sponsor created.');
    }

    public function edit(HomepageSponsor $homepageSponsor)
    {
        return view('admin.homepage_sponsors.form', [
            'sponsor' => $homepageSponsor,
            'mode'    => 'edit',
        ]);
    }

    public function update(Request $request, HomepageSponsor $homepageSponsor)
    {
        $data = $this->validateData($request);

        if ($request->hasFile('logo')) {
            if ($homepageSponsor->logo_path) {
                Storage::disk('public')->delete($homepageSponsor->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('sponsors/logos', 'public');
        }

        if ($request->hasFile('background')) {
            if ($homepageSponsor->background_path) {
                Storage::disk('public')->delete($homepageSponsor->background_path);
            }
            $data['background_path'] = $request->file('background')->store('sponsors/backgrounds', 'public');
        }

        $homepageSponsor->update($data);

        return redirect()
            ->route('admin.homepage-sponsors.index')
            ->with('status', 'Sponsor updated.');
    }

    public function destroy(HomepageSponsor $homepageSponsor)
    {
        if ($homepageSponsor->logo_path) {
            Storage::disk('public')->delete($homepageSponsor->logo_path);
        }
        if ($homepageSponsor->background_path) {
            Storage::disk('public')->delete($homepageSponsor->background_path);
        }

        $homepageSponsor->delete();

        return redirect()
            ->route('admin.homepage-sponsors.index')
            ->with('status', 'Sponsor deleted.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'website_url'  => ['nullable', 'url', 'max:255'],
            'priority'     => ['nullable', 'integer', 'min:1', 'max:9999'],
            'is_active'    => ['sometimes', 'boolean'],
            'starts_on'    => ['nullable', 'date'],
            'ends_on'      => ['nullable', 'date', 'after_or_equal:starts_on'],

            'logo'         => ['nullable', 'image', 'max:2048'],
            'background'   => ['nullable', 'image', 'max:4096'],
        ]) + [
            'priority'  => $request->input('priority', 10),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
