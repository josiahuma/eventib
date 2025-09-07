<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageSlide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class HomepageSlideController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function ensureAdmin()
    {
        abort_unless(Auth::user() && (bool)(Auth::user()->is_admin ?? false), 403);
    }

    public function index()
    {
        $this->ensureAdmin();
        $slides = HomepageSlide::orderBy('sort')->orderBy('id')->get();
        return view('admin.slides.index', compact('slides'));
    }

    public function create()
    {
        $this->ensureAdmin();
        $slide = new HomepageSlide();
        return view('admin.slides.form', compact('slide'));
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'title'      => 'nullable|string|max:150',
            'image'      => 'required|image|mimes:jpg,jpeg,png,webp,avif|max:8192',
            'link_url'   => 'nullable|url|max:500',
            'is_active'  => 'nullable|boolean',
            'sort'       => 'nullable|integer',
            'starts_at'  => 'nullable|date',
            'ends_at'    => 'nullable|date|after_or_equal:starts_at',
        ]);

        $path = $request->file('image')->store('homepage_slides', 'public');

        HomepageSlide::create([
            'title'      => $validated['title'] ?? null,
            'image_path' => $path,
            'link_url'   => $validated['link_url'] ?? null,
            'is_active'  => (bool)($validated['is_active'] ?? false),
            'sort'       => (int)($validated['sort'] ?? 0),
            'starts_at'  => $validated['starts_at'] ?? null,
            'ends_at'    => $validated['ends_at'] ?? null,
        ]);

        return redirect()->route('admin.slides.index')->with('success', 'Slide created.');
    }

    public function edit(HomepageSlide $slide)
    {
        $this->ensureAdmin();
        return view('admin.slides.form', compact('slide'));
    }

    public function update(Request $request, HomepageSlide $slide)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'title'      => 'nullable|string|max:150',
            'image'      => 'nullable|image|mimes:jpg,jpeg,png,webp,avif|max:8192',
            'link_url'   => 'nullable|url|max:500',
            'is_active'  => 'nullable|boolean',
            'sort'       => 'nullable|integer',
            'starts_at'  => 'nullable|date',
            'ends_at'    => 'nullable|date|after_or_equal:starts_at',
        ]);

        if ($request->hasFile('image')) {
            if ($slide->image_path) Storage::disk('public')->delete($slide->image_path);
            $slide->image_path = $request->file('image')->store('homepage_slides', 'public');
        }

        $slide->title      = $validated['title'] ?? null;
        $slide->link_url   = $validated['link_url'] ?? null;
        $slide->is_active  = (bool)($validated['is_active'] ?? false);
        $slide->sort       = (int)($validated['sort'] ?? 0);
        $slide->starts_at  = $validated['starts_at'] ?? null;
        $slide->ends_at    = $validated['ends_at'] ?? null;
        $slide->save();

        return redirect()->route('admin.slides.index')->with('success', 'Slide updated.');
    }

    public function destroy(HomepageSlide $slide)
    {
        $this->ensureAdmin();
        if ($slide->image_path) Storage::disk('public')->delete($slide->image_path);
        $slide->delete();
        return redirect()->route('admin.slides.index')->with('success', 'Slide deleted.');
    }
}
