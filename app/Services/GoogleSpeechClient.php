<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin wrapper over Google Cloud Speech-to-Text's synchronous `speech:recognize`
 * REST endpoint (no SDK, for shared-hosting simplicity). Suited to short voice
 * notes (well under a minute) such as call-log memos — long recordings need the
 * async `longrunningrecognize` endpoint, which this class deliberately does not
 * implement.
 *
 * Every call is wrapped in try/catch and returns null on any failure — a
 * transcription outage must never break the Call Log workflow. Audio bytes and
 * transcript text are never logged (may contain customer data).
 */
class GoogleSpeechClient
{
    private const ENDPOINT = 'https://speech.googleapis.com/v1/speech:recognize';

    public function __construct(private readonly ?string $apiKey) {}

    /**
     * Transcribe a short audio clip. $encoding is a Google STT encoding name
     * (e.g. "WEBM_OPUS", "OGG_OPUS"); $sampleRateHertz should match the
     * recording (48000 for typical browser MediaRecorder output).
     *
     * Recognizes against Hindi as the primary language with Marathi and
     * English as alternatives, since call-log voice notes are expected to be
     * in one of those three. Returns null if no key, network error, non-2xx,
     * or no speech was recognized.
     */
    public function transcribe(string $audioContentBase64, string $encoding, int $sampleRateHertz = 48000): ?string
    {
        if (blank($this->apiKey)) {
            return null;
        }

        try {
            $response = Http::timeout(30)
                ->retry(1, 250, throw: false)
                ->post(self::ENDPOINT.'?key='.$this->apiKey, [
                    'config' => [
                        'encoding' => $encoding,
                        'sampleRateHertz' => $sampleRateHertz,
                        'languageCode' => 'hi-IN',
                        'alternativeLanguageCodes' => ['mr-IN', 'en-IN'],
                    ],
                    'audio' => [
                        'content' => $audioContentBase64,
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('Google Speech-to-Text call failed.', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $transcript = collect($response->json('results', []))
                ->map(fn (array $result) => data_get($result, 'alternatives.0.transcript'))
                ->filter()
                ->implode(' ');

            return filled($transcript) ? $transcript : null;
        } catch (Throwable $e) {
            Log::warning('Google Speech-to-Text call threw an exception.', [
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
