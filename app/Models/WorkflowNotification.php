<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowNotification extends Model
{
    protected $guarded = [];

    public function instance()
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
