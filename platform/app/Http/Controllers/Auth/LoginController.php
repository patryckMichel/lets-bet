<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OpsMetricsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, OpsMetricsService $ops): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials['email'] = strtolower(trim($credentials['email']));

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email') + ['_tab' => 'login'])
                ->withErrors(['email' => 'Credenciais inválidas.']);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        if ($user->is_blocked) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withInput($request->only('email') + ['_tab' => 'login'])
                ->withErrors(['email' => 'Conta bloqueada. Contate o suporte.']);
        }

        $ops->startSession($user);

        $user->last_ip = $request->ip();
        $user->ip_address = $request->ip();
        $user->save();

        if ($user->is_admin) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('games.show', 'tigre-aviator'));
    }

    public function destroy(Request $request, OpsMetricsService $ops): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            $ops->endSession($user, 'logout');
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
