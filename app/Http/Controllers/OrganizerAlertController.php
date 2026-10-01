<?php

namespace App\Http\Controllers;

use App\Models\Organizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizerAlertController extends Controller
{
    public function index(Request $request)
    {
        $subscriptions = DB::table('organizer_alert_subscriptions as s')->join('organizers as o', 'o.id', '=', 's.organizer_id')
            ->where('s.user_id', $request->user()->id)->select('s.*', 'o.name', 'o.slug')->orderBy('o.name')->get();
        return view('organizers.alerts', compact('subscriptions'));
    }
    public function update(Request $request, Organizer $organizer)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $organizer, $data) {
            $key = ['user_id' => $request->user()->id, 'organizer_id' => $organizer->id];
            DB::table('organizer_alert_subscriptions')->insertOrIgnore($key + ['enabled' => false, 'created_at' => now(), 'updated_at' => now()]);
            $current = DB::table('organizer_alert_subscriptions')->where($key)->lockForUpdate()->first();
            DB::table('organizer_alert_subscriptions')->where($key)->update(['enabled' => (bool)$data['enabled'], 'enabled_at' => $data['enabled'] ? ($current->enabled ? $current->enabled_at : now()) : null, 'updated_at' => now()]);
            if ($data['enabled']) $request->user()->followedOrganizers()->syncWithoutDetaching([$organizer->id]);
            if (!$data['enabled']) {
                $id = DB::table('organizer_alert_subscriptions')->where($key)->value('id');
                DB::table('organizer_event_alerts')->where('subscription_id', $id)->where('status', 'pending')->update(['status' => 'skipped', 'updated_at' => now()]);
            }
        });
        return back()->with('success', $data['enabled'] ? 'New-event emails enabled for this organiser.' : 'New-event emails turned off.');
    }
    public function unsubscribe(Request $request, int $subscription)
    {
        $row = DB::table('organizer_alert_subscriptions')->find($subscription);
        abort_unless($row, 404);
        if ($request->isMethod('post')) {
            DB::transaction(function () use ($subscription) {
                DB::table('organizer_alert_subscriptions')->where('id', $subscription)->update(['enabled' => false, 'enabled_at' => null, 'updated_at' => now()]);
                DB::table('organizer_event_alerts')->where('subscription_id', $subscription)->where('status', 'pending')->update(['status' => 'skipped', 'updated_at' => now()]);
            });
        }
        return view('organizers.unsubscribe', ['disabled' => !$row->enabled || $request->isMethod('post')]);
    }
}
