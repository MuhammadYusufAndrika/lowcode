<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class WorkflowVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['definition' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Published versions are immutable.'));
        static::deleting(fn () => throw new LogicException('Published versions cannot be deleted.'));
    }

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }
}
