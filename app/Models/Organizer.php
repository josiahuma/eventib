<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Organizer extends Model
{
    //
    protected $fillable = ['user_id', 'name', 'slug', 'bio', 'avatar_url', 'website'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'followed_organizers');
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }


}
