<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AuditLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit logs are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit logs are immutable.'));
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
