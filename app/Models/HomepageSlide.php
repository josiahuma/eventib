<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class HomepageSlide extends Model
{
    use HasFactory;

    protected $fillable = [
        'title','image_path','link_url','is_active','sort','starts_at','ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function scopeActive($q)
    {
        $now = Carbon::now();
        return $q->where('is_active', true)
                 ->where(function($q) use ($now) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                 })
                 ->where(function($q) use ($now) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                 });
    }
}
