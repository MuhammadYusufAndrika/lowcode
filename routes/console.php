<?php

use App\Jobs\ProcessWorkflowDeadlines;
use App\Services\WorkflowSlaService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('workflows:deadlines', function (WorkflowSlaService $service) {
    $this->info('Processed '.$service->run().' overdue tasks.');
})->purpose('Process workflow reminders and escalations');

Schedule::job(new ProcessWorkflowDeadlines)->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
