<?php

namespace App\Http\Controllers;

use App\Models\EventRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class VoiceCheckController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'registration_id' => 'required|integer|exists:event_registrations,id',
            'audio'           => 'required|file|mimetypes:audio/webm,audio/wav,audio/mpeg,video/webm',
            'threshold'       => 'nullable|numeric|min:0|max:1',
        ]);

        $registration = EventRegistration::findOrFail($data['registration_id']);

        if (! $registration->voice_enabled || empty($registration->voice_embedding)) {
            return response()->json([
                'ok'    => false,
                'error' => 'No voice profile stored for this registration.',
            ], 400);
        }

        // voice_embedding is cast to array → encode back to JSON string for the Python API
        $embeddingJson = json_encode($registration->voice_embedding);

        $threshold = $data['threshold'] ?? 0.80;

        $file = $request->file('audio');
        $voiceServiceUrl = rtrim(config('services.voice.url'), '/') . '/compare';

        try {
            $response = Http::timeout(30)
                ->attach('audio', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                ->asMultipart()
                ->post($voiceServiceUrl, [
                    'reference_embedding' => $embeddingJson,
                    'threshold'           => $threshold,
                ]);

            if (! $response->ok()) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'Voice service error',
                    'meta'  => [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ],
                ], 500);
            }

            $payload = $response->json();

            // Optional: if match is true, mark as checked-in
            if (!empty($payload['match']) && $payload['match'] === true) {
                $registration->checked_in_at = now();
                // You might also set checked_in_by here when we hook up the scanner user
                $registration->save();
            }

            return response()->json([
                'ok'          => true,
                'match'       => (bool) ($payload['match'] ?? false),
                'similarity'  => $payload['similarity'] ?? null,
                'threshold'   => $payload['threshold'] ?? $threshold,
                'checked_in'  => !empty($registration->checked_in_at),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Voice check failed', [
                'registration_id' => $registration->id,
                'error'           => $e->getMessage(),
            ]);

            return response()->json([
                'ok'    => false,
                'error' => 'Could not contact voice service',
            ], 500);
        }
    }
}
