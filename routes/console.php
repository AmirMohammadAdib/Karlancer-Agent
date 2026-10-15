<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\ImportProjectsJob;

Schedule::job(new ImportProjectsJob('mock'))->cron(config('ingestion.cron', '0 * * * *'))->withoutOverlapping()->when(fn () => (bool) config('ingestion.mock_enabled'));

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
