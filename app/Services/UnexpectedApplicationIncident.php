<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class UnexpectedApplicationIncident
{
    public function __construct(private readonly OperationalIncidentRecorder $incidents) {}

    public function record(Throwable $exception, Request $request): string
    {
        $reference = (string) Str::uuid();
        $user = $request->user();

        Log::error('An unexpected application request failed.', [
            'reference' => $reference,
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'path' => $request->path(),
            'exception' => $exception,
        ]);

        try {
            return $this->incidents->record('application.unexpected_failure', $exception, [
                'reference' => $reference,
                'severity' => 'error',
                'name' => $user?->name,
                'email' => $user?->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'exception_message' => 'An unexpected application error occurred on '.$request->method().' /'.$request->path().'. See the Laravel log using this reference for private diagnostics.',
            ])->reference;
        } catch (Throwable $recordingFailure) {
            Log::critical('The unexpected application incident could not be persisted.', [
                'reference' => $reference,
                'recording_exception' => $recordingFailure,
            ]);

            return $reference;
        }
    }
}
