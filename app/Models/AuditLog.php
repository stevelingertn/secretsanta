<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    protected $fillable = ['event_id', 'actor_id', 'action', 'subject_type', 'subject_id', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** Never pass login codes or contact details in $details. */
    public static function record(string $action, ?Event $event = null, ?User $actor = null, ?Model $subject = null, array $details = []): self
    {
        return static::create([
            'event_id' => $event?->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'details' => $details ?: null,
        ]);
    }
}
