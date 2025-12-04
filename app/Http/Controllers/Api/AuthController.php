<?php

namespace App\Http\Controllers\Api;
use Google_Client;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required','email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($data)) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        $user  = $request->user();

        // Optional: only allow organisers/admins to use the scanner app
        // if (!($user->is_admin ?? false) && !$user->can('organize-events')) {
        //     return response()->json(['message' => 'Not authorized'], 403);
        // }

        $token = $user->createToken('eventib-mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateMe(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'   => ['required', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:50'],
        ]);

        $user->fill($data);
        $user->save();

        return response()->json($user);
    }

    public function loginWithGoogle(Request $request)
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        $client = new Google_Client(['android_client_id' => config('services.google.android_client_id')]);
        $payload = $client->verifyIdToken($data['id_token']);

        if (!$payload) {
            return response()->json(['message' => 'Invalid Google token'], 422);
        }

        $googleEmail = $payload['email'] ?? null;
        $googleName  = $payload['name']  ?? null;

        if (!$googleEmail) {
            return response()->json(['message' => 'Google account has no email'], 422);
        }

        // Find or create user
        $user = User::where('email', $googleEmail)->first();

        if (!$user) {
            $user = User::create([
                'name'     => $googleName ?: $googleEmail,
                'email'    => $googleEmail,
                'password' => bcrypt(str()->random(32)), // random password, they won’t use it
            ]);
        }

        $token = $user->createToken('eventib-mobile-google')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
    
}
