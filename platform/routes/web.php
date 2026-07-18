<?php

use App\Http\Controllers\AsaasTransferValidationController;
use App\Http\Controllers\AsaasWebhookController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BonusController;
use App\Http\Controllers\CrashController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LobbyController;
use App\Http\Controllers\MercadoPagoWebhookController;
use App\Http\Controllers\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::post('/webhooks/mercadopago', MercadoPagoWebhookController::class)->name('webhooks.mercadopago');
Route::post('/webhooks/asaas', AsaasWebhookController::class)->name('webhooks.asaas');
Route::post('/webhooks/asaas/transfer-validation', AsaasTransferValidationController::class)
    ->name('webhooks.asaas.transfer-validation');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/lobby', LobbyController::class)->name('lobby');
    Route::get('/jogos/{slug}', [GameController::class, 'show'])->name('games.show');

    Route::get('/depositar', [DepositController::class, 'create'])->name('deposits.create');
    Route::post('/depositar', [DepositController::class, 'store'])->name('deposits.store');
    Route::get('/depositar/{deposit}', [DepositController::class, 'show'])->name('deposits.show');
    Route::post('/depositar/{deposit}/confirmar', [DepositController::class, 'confirm'])->name('deposits.confirm');
    Route::post('/depositar/{deposit}/verificar', [DepositController::class, 'verify'])->name('deposits.verify');

    Route::get('/sacar', [WithdrawalController::class, 'create'])->name('withdrawals.create');
    Route::post('/sacar', [WithdrawalController::class, 'store'])->name('withdrawals.store');

    Route::get('/bonus', [BonusController::class, 'create'])->name('bonus.create');
    Route::post('/bonus', [BonusController::class, 'store'])->name('bonus.store');

    Route::prefix('api/crash')->group(function () {
        Route::get('/state', [CrashController::class, 'state'])->name('crash.state');
        Route::post('/bet', [CrashController::class, 'bet'])->name('crash.bet');
        Route::post('/cashout', [CrashController::class, 'cashout'])->name('crash.cashout');
    });
});

require __DIR__.'/admin.php';
