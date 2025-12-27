<?php

use Illuminate\Support\Facades\Schedule;

// Run daily sales report every evening at 6:00 PM
Schedule::command('report:daily-sales')->dailyAt('18:00');
