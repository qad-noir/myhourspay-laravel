<?php

namespace App\Services;

use App\Models\Feature;
use App\Models\FeatureUsageDaily;
use App\Models\User;
use App\Models\Workspace;

class FeatureUsage
{
    public function record(User $user, string $featureKey, ?Workspace $workspace = null): void
    {
        $featureId = Feature::query()->where('key', $featureKey)->value('id');
        if (! $featureId) {
            return;
        }

        $usage = FeatureUsageDaily::query()->firstOrCreate([
            'usage_date' => today(),
            'user_id' => $user->id,
            'workspace_id' => $workspace?->id,
            'feature_id' => $featureId,
        ], ['usage_count' => 0]);
        $usage->increment('usage_count');
    }
}
