<?php

use App\Console\Commands\NotifyDiscordEventsStartingCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Discord-оповещение «Начало гильдейского события (за 10 мин)».
// Команда сама вычисляет окно 9..10 минут до старта и защищена кешем от дублей.
// withoutOverlapping — на случай долгой отправки в Discord (1 мин tick может пересечься).
Schedule::command(NotifyDiscordEventsStartingCommand::class)
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();
