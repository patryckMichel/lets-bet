<?php

use App\Console\Commands\ApplyDailyCashbackCommand;
use App\Console\Commands\CreditAffiliateCommissionsCommand;
use App\Console\Commands\ExpirePendingDepositsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(CreditAffiliateCommissionsCommand::class)->everyFifteenMinutes();
Schedule::command(ExpirePendingDepositsCommand::class)->everyThirtySeconds();
Schedule::command(ApplyDailyCashbackCommand::class)->dailyAt('00:15');
