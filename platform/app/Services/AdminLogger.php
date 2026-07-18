<?php

namespace App\Services;

use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AdminLogger
{
    public function record(
        User $admin,
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
    ): AdminLog {
        return AdminLog::query()->create([
            'admin_id' => $admin->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip' => Request::ip(),
        ]);
    }
}
