<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowInstance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function version()
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function tasks()
    {
        return $this->hasMany(WorkflowTask::class);
    }

    public function logs()
    {
        return $this->hasMany(WorkflowExecutionLog::class);
    }

    public function values()
    {
        return $this->hasMany(WorkflowInstanceData::class);
    }

    public function files()
    {
        return $this->hasMany(WorkflowFile::class);
    }
}
