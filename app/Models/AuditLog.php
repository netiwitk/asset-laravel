<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Who changed which row: written by the model hooks in AppServiceProvider.
 * Append-only; database triggers reject UPDATE and DELETE.
 *
 * Status changes are not here: AssetLedger updates through the query builder (no model events)
 * and records them in asset_movements instead.
 */
#[Fillable(['actor_id', 'auditable_type', 'auditable_id', 'event', 'diff', 'ip'])]
class AuditLog extends Model
{
    const UPDATED_AT = null;

    /**
     * Fields that change on their own: timestamps, and the remember-me token Laravel rotates on every logout.
     */
    private const IGNORED_FIELDS = ['created_at', 'updated_at', 'deleted_at', 'remember_token'];

    protected function casts(): array
    {
        return ['diff' => 'json:unicode'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * An update stores only the fields that changed; hidden fields (the password hash) are masked.
     */
    public static function record(Model $model, string $event): void
    {
        $diff = null;

        if ($event === 'updated') {
            $diff = collect($model->getChanges())
                ->except(self::IGNORED_FIELDS)
                ->map(fn (mixed $to, string $field): array => in_array($field, $model->getHidden(), true)
                    ? ['from' => '•••', 'to' => '•••']
                    : ['from' => $model->getRawOriginal($field), 'to' => $to])
                ->all();

            // e.g. a logout that only rotated the remember-me token, or a restore (logged as "restored").
            if ($diff === []) {
                return;
            }
        }

        static::create([
            'actor_id' => auth()->id(),
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'event' => $event,
            'diff' => $diff,
            'ip' => request()->ip(),
        ]);
    }
}
