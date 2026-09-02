<?php

namespace App\Services;

use App\Models\WorkspaceInvitation;
use Illuminate\Http\Request;

class WorkspaceInvitationContext
{
    private const SESSION_KEY = 'workspace_invitation';

    public function remember(Request $request, WorkspaceInvitation $invitation, string $token): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'invitation' => $invitation->public_id,
            'token' => $token,
        ]);
        $request->session()->put('url.intended', $this->acceptanceUrl($invitation, $token));
    }

    public function pending(Request $request): ?WorkspaceInvitation
    {
        $context = $request->session()->get(self::SESSION_KEY);
        if (! is_array($context) || blank($context['invitation'] ?? null) || blank($context['token'] ?? null)) {
            return null;
        }

        $invitation = WorkspaceInvitation::query()
            ->with(['workspace', 'inviter'])
            ->where('public_id', $context['invitation'])
            ->first();

        if (! $invitation || ! $this->isValid($invitation, (string) $context['token'])) {
            $this->forget($request);

            return null;
        }

        return $invitation;
    }

    public function token(Request $request): ?string
    {
        return $this->pending($request)
            ? (string) $request->session()->get(self::SESSION_KEY.'.token')
            : null;
    }

    public function intendedAcceptanceUrl(Request $request): ?string
    {
        $invitation = $this->pending($request);
        $token = $invitation ? $this->token($request) : null;

        return $invitation && $token ? $this->acceptanceUrl($invitation, $token) : null;
    }

    public function isValid(WorkspaceInvitation $invitation, string $token): bool
    {
        return $invitation->status === 'pending'
            && $invitation->expires_at->isFuture()
            && hash_equals($invitation->token_hash, hash('sha256', $token));
    }

    public function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
        if (str_contains((string) $request->session()->get('url.intended'), '/business/invitations/')) {
            $request->session()->forget('url.intended');
        }
    }

    private function acceptanceUrl(WorkspaceInvitation $invitation, string $token): string
    {
        return route('business.invitations.accept', [
            'invitation' => $invitation,
            'token' => $token,
        ]);
    }
}
