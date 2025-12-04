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
            'code' => ['required', 'string'],
            'redirect_uri' => ['required', 'string'],
        ]);

        $client = new \GuzzleHttp\Client();

        // Exchange the "code" for tokens
        $tokenResponse = $client->post('https://oauth2.googleapis.com/token', [
            'form_params' => [
                'client_id' => config('services.google.android_client_id'),
                'client_secret' => config('services.google.client_secret'),
                'code' => $data['code'],
                'redirect_uri' => $data['redirect_uri'],
                'grant_type' => 'authorization_code',
            ],
        ]);

        $tokens = json_decode($tokenResponse->getBody(), true);

        $idToken = $tokens['id_token'] ?? null;
        if (!$idToken) {
            return response()->json(['message' => 'Google did not return id_token'], 422);
        }

        $payload = (new Google_Client())->verifyIdToken($idToken);
        if (!$payload) {
            return response()->json(['message' => 'Invalid Google token'], 422);
        }

        $email = $payload['email'] ?? null;

        if (!$email) {
            return response()->json(['message' => 'Email missing'], 422);
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $payload['name'] ?? $email,
                'password' => bcrypt(str()->random(32)),
            ]
        );

        $token = $user->createToken('eventib-mobile-google')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user,
        ]);
    }

    
}
