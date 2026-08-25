<?php

namespace App\Console\Commands;

use App\Models\OutboundWebhookDelivery;
use App\Services\OperationalIncidentRecorder;
use App\Services\PublicWebhookUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeliverOutboundWebhooks extends Command
{
    protected $signature = 'webhooks:deliver {--limit=100}';

    protected $description = 'Deliver due signed workspace webhooks with bounded retries';

    public function handle(PublicWebhookUrl $validator, OperationalIncidentRecorder $incidents): int
    {
        $failures = 0;
        OutboundWebhookDelivery::query()->with('endpoint.workspace')->whereIn('status', ['pending', 'retrying'])->where('next_attempt_at', '<=', now())->limit(max(1, min(500, (int) $this->option('limit'))))->get()->each(function (OutboundWebhookDelivery $delivery) use ($validator, $incidents, &$failures): void {
            $endpoint = $delivery->endpoint;
            if (! $endpoint?->active || $endpoint->suspended_at) {
                return;
            }
            try {
                $validator->validate($endpoint->url);
                $timestamp = now()->timestamp;
                $body = json_encode(['id' => $delivery->public_id, 'event' => $delivery->event_type, 'created_at' => $delivery->created_at->toIso8601String(), 'data' => $delivery->payload], JSON_THROW_ON_ERROR);
                $signature = hash_hmac('sha256', $timestamp.'.'.$body, $endpoint->secret);
                $response = Http::timeout(12)->withHeaders(['Content-Type' => 'application/json', 'User-Agent' => 'myhourspay-webhooks/1.0', 'X-MHP-Delivery' => $delivery->public_id, 'X-MHP-Signature' => "t={$timestamp},v1={$signature}"])->withBody($body, 'application/json')->post($endpoint->url);
                if (! $response->successful()) {
                    throw new \RuntimeException('Webhook endpoint returned HTTP '.$response->status().'.');
                }
                $delivery->update(['attempts' => $delivery->attempts + 1, 'status' => 'delivered', 'response_status' => $response->status(), 'response_excerpt' => mb_substr($response->body(), 0, 1000), 'delivered_at' => now()]);
                $endpoint->update(['consecutive_failures' => 0]);
            } catch (Throwable $exception) {
                $failures++;
                $attempts = $delivery->attempts + 1;
                $suspend = $attempts >= 5;
                $delivery->update(['attempts' => $attempts, 'status' => $suspend ? 'failed' : 'retrying', 'response_excerpt' => mb_substr($exception->getMessage(), 0, 1000), 'next_attempt_at' => now()->addMinutes([1 => 5, 2 => 15, 3 => 60, 4 => 360][$attempts] ?? 1440)]);
                $endpoint->update(['consecutive_failures' => $endpoint->consecutive_failures + 1, 'active' => ! $suspend, 'suspended_at' => $suspend ? now() : null]);
                Log::error('Outbound workspace webhook failed.', ['delivery_id' => $delivery->id, 'endpoint_id' => $endpoint->id, 'attempt' => $attempts, 'exception' => $exception]);
                if ($suspend) {
                    try {
                        $incidents->record('webhooks.endpoint_suspended', $exception, ['severity' => 'error', 'exception_message' => "Workspace webhook endpoint {$endpoint->id} was suspended after five failed deliveries."]);
                    } catch (Throwable $recordingFailure) {
                        Log::critical('Webhook suspension incident could not be persisted.', ['exception' => $recordingFailure]);
                    }
                }
            }
        });
        $this->info("Webhook delivery completed with {$failures} failure(s).");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
