<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Workflow extends Model
{
    protected $fillable = ['code', 'name', 'description', 'category', 'owner_id', 'status'];

    protected $attributes = ['draft_revision' => 0];

    protected function casts(): array
    {
        return ['draft_definition' => 'array', 'draft_revision' => 'integer'];
    }

    public function versions()
    {
        return $this->hasMany(WorkflowVersion::class);
    }

    public function latestPublishedVersion()
    {
        return $this->hasOne(WorkflowVersion::class)->ofMany('version', 'max');
    }

    public function instances()
    {
        return $this->hasMany(WorkflowInstance::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('Super Admin') || $user->can('workflows.view.all')) {
            return $query;
        }

        if ($user->can('workflows.view.own')) {
            return $query->where('owner_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }
}
