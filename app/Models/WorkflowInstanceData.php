<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowInstanceData extends Model
{
    protected $table = 'workflow_instance_data';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
