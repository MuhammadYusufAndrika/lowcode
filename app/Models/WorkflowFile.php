<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowFile extends Model
{
    protected $guarded = [];

    protected $hidden = ['path'];

    public function instance()
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }
}
