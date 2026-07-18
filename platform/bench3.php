<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CrashRound;
use App\Services\CrashEngine;

$round = CrashRound::find(30);
echo "status={$round->status} crash={$round->crash_point} started={$round->started_at}\n";
$e = app(CrashEngine::class);
echo "mult=".$e->currentMultiplier($round)."\n";
echo "calling advanceLight...\n"; flush();
$t = microtime(true);
$ref = new ReflectionMethod($e, 'advanceLight');
$ref->setAccessible(true);
$r = $ref->invoke($e);
echo "done ms=".round((microtime(true)-$t)*1000)." status={$r->status} id={$r->id}\n";
