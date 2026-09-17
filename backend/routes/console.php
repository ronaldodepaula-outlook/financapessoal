<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('finance:generate-forecasts')->dailyAt('03:00')->timezone('America/Sao_Paulo')->withoutOverlapping(60);
