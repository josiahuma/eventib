<?php

// app/Http/Controllers/Controller.php
namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function ensureActiveUser()
    {
        if (Auth::check() && Auth::user()->is_disabled) {
            Auth::logout();
            abort(403, 'Your account has been disabled.');
        }
    }
}
