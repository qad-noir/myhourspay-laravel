@if(in_array($event->status, ['failed', 'exhausted', 'processing', 'unmatched']) && ! $event->lease_until?->isFuture())
<form method="POST" action="{{ route('admin.billing.webhooks.retry', $event) }}">@csrf<button class="admin-button admin-button--secondary" type="submit">Retry event</button></form>
@else
<span>—</span>
@endif
