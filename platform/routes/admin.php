<?php

use App\Http\Controllers\Admin\OpsMetricsAdminController;
use App\Http\Controllers\Admin\AffiliateAdminController;
use App\Http\Controllers\Admin\BonusCodeAdminController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinanceAdminController;
use App\Http\Controllers\Admin\GameAdminController;
use App\Http\Controllers\Admin\LogAdminController;
use App\Http\Controllers\Admin\PlayerAdminController;
use App\Http\Controllers\Admin\SettingAdminController;
use App\Http\Controllers\Admin\WithdrawalAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/jogos', [GameAdminController::class, 'index'])->name('games.index');
    Route::patch('/jogos/{game}/status', [GameAdminController::class, 'updateStatus'])->name('games.status');

    Route::get('/jogadores', [PlayerAdminController::class, 'index'])->name('players.index');
    Route::get('/jogadores/exportar', [PlayerAdminController::class, 'export'])->name('players.export');
    Route::get('/jogadores/{user}', [PlayerAdminController::class, 'show'])->name('players.show');
    Route::post('/jogadores/{user}/bloquear', [PlayerAdminController::class, 'toggleBlock'])->name('players.block');
    Route::post('/jogadores/{user}/kyc', [PlayerAdminController::class, 'toggleKyc'])->name('players.kyc');
    Route::post('/jogadores/{user}/fraude-limpar', [PlayerAdminController::class, 'clearFraudFlag'])->name('players.fraud-clear');
    Route::post('/jogadores/{user}/saldo', [PlayerAdminController::class, 'adjustBalance'])->name('players.balance');
    Route::post('/jogadores/{user}/afiliado', [PlayerAdminController::class, 'makeAffiliate'])->name('players.affiliate');

    Route::get('/afiliados', [AffiliateAdminController::class, 'index'])->name('affiliates.index');
    Route::get('/afiliados/{affiliate}', [AffiliateAdminController::class, 'show'])->name('affiliates.show');
    Route::put('/afiliados/{affiliate}', [AffiliateAdminController::class, 'update'])->name('affiliates.update');
    Route::post('/afiliados/{affiliate}/calcular', [AffiliateAdminController::class, 'calculate'])->name('affiliates.calculate');
    Route::post('/afiliados/{affiliate}/confirmar-comissao', [AffiliateAdminController::class, 'confirmCommission'])->name('affiliates.confirm');
    Route::post('/afiliados/{affiliate}/codigos', [AffiliateAdminController::class, 'storeCode'])->name('affiliates.codes.store');

    Route::get('/codigos', [BonusCodeAdminController::class, 'index'])->name('bonus-codes.index');
    Route::post('/codigos', [BonusCodeAdminController::class, 'store'])->name('bonus-codes.store');
    Route::post('/codigos/{bonusCode}/toggle', [BonusCodeAdminController::class, 'toggle'])->name('bonus-codes.toggle');

    Route::get('/financeiro', [FinanceAdminController::class, 'index'])->name('finance.index');
    Route::post('/financeiro', [FinanceAdminController::class, 'store'])->name('finance.store');

    Route::get('/saques', [WithdrawalAdminController::class, 'index'])->name('withdrawals.index');
    Route::post('/saques/{withdrawal}/pagar', [WithdrawalAdminController::class, 'pay'])->name('withdrawals.pay');
    Route::post('/saques/{withdrawal}/rejeitar', [WithdrawalAdminController::class, 'reject'])->name('withdrawals.reject');

    Route::get('/metricas', [OpsMetricsAdminController::class, 'index'])->name('ops.index');

    Route::get('/configuracoes', [SettingAdminController::class, 'edit'])->name('settings.edit');
    Route::put('/configuracoes', [SettingAdminController::class, 'update'])->name('settings.update');

    Route::get('/logs', [LogAdminController::class, 'index'])->name('logs.index');
});
