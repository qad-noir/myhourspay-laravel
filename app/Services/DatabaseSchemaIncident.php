<?php

namespace App\Services;

use App\Models\OperationalIncident;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DatabaseSchemaIncident
{
    public const EVENT_TYPE = 'deployment.database_schema_mismatch';

    public const EXPECTED_MIGRATION = '2026_08_23_090000_add_scalable_hours_projections';

    public function __construct(private readonly OperationalIncidentRecorder $incidents) {}

    public function matches(QueryException $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());
        $missingColumn = in_array((string) $exception->getCode(), ['42S22', '42703'], true)
            || str_contains($message, 'sqlstate[42s22]')
            || str_contains($message, 'unknown column');
        $requiredProjection = str_contains($message, 'net_minutes') || str_contains($message, 'week_start');

        return $missingColumn && $requiredProjection;
    }

    public function record(QueryException $exception, Request $request): string
    {
        $reference = (string) Str::uuid();
        $user = $request->user();
        $context = [
            'reference' => $reference,
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'path' => $request->path(),
            'sql_state' => (string) $exception->getCode(),
            'expected_migration' => self::EXPECTED_MIGRATION,
            'exception' => $exception,
        ];

        Log::critical('The deployed application code is ahead of the database schema.', $context);

        try {
            $existing = OperationalIncident::query()
                ->where('event_type', self::EVENT_TYPE)
                ->whereNull('resolved_at')
                ->where('occurred_at', '>=', now()->subMinutes(10))
                ->latest('occurred_at')
                ->first();
            if ($existing) {
                return $existing->reference;
            }

            return $this->incidents->record(self::EVENT_TYPE, $exception, [
                'reference' => $reference,
                'severity' => 'critical',
                'name' => $user?->name,
                'email' => $user?->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'exception_message' => 'Required hours projection columns are missing. Run migration '.self::EXPECTED_MIGRATION.'.',
            ])->reference;
        } catch (Throwable $incidentFailure) {
            Log::critical('The database schema incident could not be persisted.', [
                'reference' => $reference,
                'recording_exception' => $incidentFailure,
            ]);

            return $reference;
        }
    }
}
