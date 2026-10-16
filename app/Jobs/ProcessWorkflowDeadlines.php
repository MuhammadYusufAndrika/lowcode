<?php

namespace App\Jobs;

use App\Services\WorkflowSlaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWorkflowDeadlines implements ShouldQueue
{
    use Queueable;

    public function handle(WorkflowSlaService $service): void
    {
        $service->run();
    }
}
