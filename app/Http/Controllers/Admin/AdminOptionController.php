<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOptionController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q'));
        if (mb_strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $results = User::query()
            ->where(function ($builder) use ($query): void {
                $builder->where('name', 'like', $query.'%')
                    ->orWhere('email', 'like', $query.'%');
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'email_verified_at', 'suspended_at'])
            ->map(fn (User $user): array => [
                'value' => (string) $user->id,
                'text' => $user->name,
                'email' => $user->email,
                'status' => $user->suspended_at ? 'Suspended' : ($user->email_verified_at ? 'Verified' : 'Unverified'),
            ]);

        return response()->json(['results' => $results])->header('Cache-Control', 'private, no-store');
    }

    public function workspaces(Request $request, User $user): JsonResponse
    {
        $query = trim((string) $request->query('q'));
        $results = $user->workspaces()
            ->when($query !== '', fn ($builder) => $builder->where('workspaces.name', 'like', $query.'%'))
            ->orderBy('workspaces.name')
            ->limit(20)
            ->get(['workspaces.id', 'workspaces.name'])
            ->map(fn ($workspace): array => [
                'value' => (string) $workspace->id,
                'text' => $workspace->name,
            ]);

        return response()->json(['results' => $results])->header('Cache-Control', 'private, no-store');
    }
}
