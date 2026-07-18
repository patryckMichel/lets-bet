<?php

namespace App\Console\Commands;

use App\Services\PlayerBonusService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ApplyDailyCashbackCommand extends Command
{
    protected $signature = 'bonus:cashback {--date= : Dia de referência (Y-m-d), padrão = ontem}';

    protected $description = 'Credita cashback de bônus com base nas perdas líquidas do dia';

    public function handle(PlayerBonusService $bonuses): int
    {
        $dateOpt = $this->option('date');
        $day = $dateOpt
            ? Carbon::parse($dateOpt, config('app.timezone'))->startOfDay()
            : now(config('app.timezone'))->subDay()->startOfDay();

        $count = $bonuses->applyCashbackForDate($day);
        $this->info("Cashback {$day->toDateString()}: {$count} jogador(es) creditados.");

        return self::SUCCESS;
    }
}
