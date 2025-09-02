<?php
// app/Http/Controllers/PayoutMethodController.php

namespace App\Http\Controllers;

use App\Models\UserPayoutMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayoutMethodController extends Controller
{
    /** Countries we currently support for bank payouts */
    private array $countries = [
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'NG' => 'Nigeria',
        'IN' => 'India',
        'EU' => 'Eurozone',
    ];

    public function index(Request $request)
    {
        $methods = Auth::user()->payoutMethods()->orderBy('type')->orderBy('country')->get();

        $takenCountries = $methods->where('type','bank')->pluck('country')->all();
        $availableCountries = array_diff_key($this->countries, array_flip($takenCountries));

        // prefill comes from ?country=XX (only if still available), else first available, else GB
        $qCountry = strtoupper($request->query('country',''));
        $prefill  = array_key_exists($qCountry, $availableCountries)
                  ? $qCountry
                  : (array_key_first($availableCountries) ?? 'GB');

        $hasPaypal = $methods->contains(fn($m) => $m->type === 'paypal');

        return view('profile.payouts', [
            'methods'            => $methods,
            'prefill'            => $prefill,
            'availableCountries' => $availableCountries,
            'hasPaypal'          => $hasPaypal,
        ]);
    }

    public function store(Request $request)
    {
        $type = $request->input('type');
        $country = $type === 'paypal' ? 'ZZ' : strtoupper($request->input('country',''));

        $rules = ['type' => 'required|in:bank,paypal'];

        if ($type === 'paypal') {
            $rules['paypal_email'] = 'required|email';
        } else {
            $rules['country']        = 'required|string|size:2';
            $rules['account_name']   = 'required|string|max:100';
            $rules['account_number'] = 'required|string|max:32';
            $rules['sort_code']      = 'required|string|max:32';
            $rules['iban']           = 'nullable|string|max:34';
            $rules['swift']          = 'nullable|string|max:11';
        }

        $data = $request->validate($rules);

        // prevent duplicates
        $exists = UserPayoutMethod::where('user_id', Auth::id())
            ->where('type', $type)
            ->when($type === 'paypal',
                fn ($q) => $q,                 // only one PayPal total
                fn ($q) => $q                  // only one bank total (no country filter)
            )
            ->exists();

        if ($exists) {
            return back()->withErrors([
                $type === 'paypal' ? 'paypal_email' : 'country' => 'You already have a '.$type.' payout saved. Edit the existing one instead.',
            ]);
        }

        UserPayoutMethod::create([
            'user_id'        => Auth::id(),
            'type'           => $type,
            'country'        => $country,
            'paypal_email'   => $data['paypal_email'] ?? null,
            'account_name'   => $data['account_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'sort_code'      => $data['sort_code'] ?? null,
            'iban'           => $data['iban'] ?? null,
            'swift'          => $data['swift'] ?? null,
        ]);

        return redirect()->route('profile.payouts')->with('status','payout-method-added');
    }

    public function edit(UserPayoutMethod $method)
    {
        abort_unless($method->user_id === Auth::id(), 403);

        // you can’t change the country here (1 per country); only edit fields
        return view('profile.payouts-edit', [
            'method'    => $method,
            'countryName' => $method->type === 'bank'
                ? ($this->countries[$method->country] ?? $method->country)
                : 'PayPal',
        ]);
    }

    public function update(Request $request, UserPayoutMethod $method)
    {
        abort_unless($method->user_id === Auth::id(), 403);

        if ($method->type === 'paypal') {
            $data = $request->validate([
                'paypal_email' => 'required|email',
            ]);
            $method->paypal_email = $data['paypal_email'];
        } else {
            $data = $request->validate([
                'account_name'   => 'required|string|max:100',
                'account_number' => 'required|string|max:32',
                'sort_code'      => 'required|string|max:32',
                'iban'           => 'nullable|string|max:34',
                'swift'          => 'nullable|string|max:11',
            ]);
            $method->fill($data);
        }

        $method->save();

        return redirect()->route('profile.payouts')->with('status', 'payout-method-updated');
    }

    public function destroy(UserPayoutMethod $method)
    {
        abort_unless($method->user_id === Auth::id(), 403);
        $method->delete();

        return redirect()->route('profile.payouts')->with('status','payout-method-deleted');
    }
}
