<?php

namespace App\Http\Controllers;

use App\Models\UserDigitalPass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DigitalPassController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $pass = $user->digitalPass;

        return view('digital-pass.setup', compact('user', 'pass'));
    }

    public function storeVoice(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'voice_sample1' => 'required|string',
            'voice_sample2' => 'required|string',
            'voice_sample3' => 'required|string',
        ]);

        $samples = [];

        foreach ([1, 2, 3] as $i) {
            $dataUrl = $request->input("voice_sample{$i}");

            // Expecting "data:audio/webm;base64,AAAA..."
            if (! str_starts_with($dataUrl, 'data:')) {
                return back()
                    ->with('error', "Sample {$i} is invalid.")
                    ->withInput();
            }

            [$meta, $base64] = explode(',', $dataUrl, 2);
            $audioBytes = base64_decode($base64, true);

            if ($audioBytes === false) {
                return back()
                    ->with('error', "Could not decode audio for sample {$i}.")
                    ->withInput();
            }

            // Send to FastAPI /embed
            $response = Http::attach(
                'audio',
                $audioBytes,
                "sample{$i}.webm"
            )->post(config('services.voice.url') . '/embed');

            if (! $response->ok()) {
                return back()
                    ->with('error', "Voice service failed for sample {$i}.")
                    ->withInput();
            }

            $embedding = $response->json('embedding');

            if (! is_array($embedding) || empty($embedding)) {
                return back()
                    ->with('error', "Invalid embedding returned for sample {$i}.")
                    ->withInput();
            }

            $samples[] = $embedding;
        }

        // Average the 3 vectors element-wise
        $dimension = count($samples[0]);
        $sum = array_fill(0, $dimension, 0.0);

        foreach ($samples as $vec) {
            // safety: ensure same length
            if (count($vec) !== $dimension) {
                return back()->with('error', 'Voice embeddings had mismatched dimensions.');
            }
            for ($i = 0; $i < $dimension; $i++) {
                $sum[$i] += (float) $vec[$i];
            }
        }

        $avg = [];
        foreach ($sum as $val) {
            $avg[] = $val / count($samples);
        }

        // Save into user_digital_passes
        $pass = $user->digitalPass ?: new UserDigitalPass(['user_id' => $user->id]);

        $pass->voice_embedding   = $avg;
        $pass->voice_enrolled_at = now();
        $pass->is_active         = true;
        $pass->save();

        return redirect()
            ->route('digital-pass.show')
            ->with('success', 'Voice pass saved.');
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        if ($user->digitalPass) {
            $user->digitalPass->delete();
        }

        return redirect()
            ->route('digital-pass.show')
            ->with('success', 'Your digital pass has been deleted.');
    }
}
