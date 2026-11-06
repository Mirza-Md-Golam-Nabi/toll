<?php

use App\Enums\AppMode;
use App\Support\Toll;
use Illuminate\Support\Facades\Schedule;

Schedule::command('toll:sync')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->when(fn (): bool => AppMode::isBooth() && Toll::syncEnabled());
