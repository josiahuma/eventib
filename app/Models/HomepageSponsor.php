<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class HomepageSponsor extends Model
{
    protected $fillable = [
        'name',
        'website_url',
        'logo_path',
        'background_path',
        'priority',
        'is_active',
        'starts_on',
        'ends_on',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_on' => 'date',
        'ends_on'   => 'date',
    ];

    /**
     * Scope sponsors that are active *and* valid for a given date.
     */
    public function scopeActiveForDate($query, ?Carbon $date = null)
    {
        $date = $date ?: now()->startOfDay();

        return $query
            ->where('is_active', true)
            ->where(function ($q) use ($date) {
                $q->whereNull('starts_on')
                  ->orWhere('starts_on', '<=', $date->toDateString());
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('ends_on')
                  ->orWhere('ends_on', '>=', $date->toDateString());
            });
    }
}
