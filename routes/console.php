<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Demo mode: visitors share the one-click accounts and can change anything,
 * so the sample data is put back every night (needs the scheduler cron on the host).
 */
Schedule::command('migrate:fresh --seed --force')
    ->dailyAt('03:00')
    ->when(fn (): bool => (bool) config('app.demo'));
