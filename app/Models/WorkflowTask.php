<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowTask extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'decided_at' => 'datetime', 'reminded_at' => 'datetime', 'escalated_at' => 'datetime'];
    }

    public function instance()
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }
}
