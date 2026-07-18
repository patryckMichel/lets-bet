<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminLogger;
use App\Services\SystemUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class SystemUpdateAdminController extends Controller
{
    public function index(SystemUpdateService $updates): View
    {
        return view('admin.system-update.index', [
            'status' => $updates->status(),
        ]);
    }

    public function update(Request $request, SystemUpdateService $updates, AdminLogger $logger): RedirectResponse
    {
        try {
            $result = $updates->runUpdate();
        } catch (RuntimeException $e) {
            return back()->withErrors(['update' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return back()->withErrors(['update' => 'Falha ao atualizar: '.$e->getMessage()]);
        }

        $logger->record($request->user(), 'system.update_ran', null, null, [
            'ok' => $result['ok'],
            'log_tail' => mb_substr($result['log'], -2000),
        ]);

        if (! $result['ok']) {
            return back()
                ->withErrors(['update' => 'O script de update retornou erro. Veja o log abaixo.'])
                ->with('update_log', $result['log']);
        }

        return back()
            ->with('status', 'Sistema atualizado com sucesso.')
            ->with('update_log', $result['log']);
    }
}
