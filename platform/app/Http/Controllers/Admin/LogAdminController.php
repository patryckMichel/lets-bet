<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use Illuminate\View\View;

class LogAdminController extends Controller
{
    public function index(): View
    {
        $logs = AdminLog::query()
            ->with('admin')
            ->latest()
            ->paginate(50);

        return view('admin.logs.index', compact('logs'));
    }
}
