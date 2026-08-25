<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AdminAudit
{
    public function record(Request $request, string $action, Model $target, array $before = [], array $after = [], ?string $reason = null): void
    {
        $log = new AdminAuditLog(['admin_user_id' => $request->user()->id, 'action' => $action, 'reason' => $reason, 'before' => $before, 'after' => $after, 'ip_address' => $request->ip()]);
        $log->target()->associate($target);
        $log->save();
    }
}
