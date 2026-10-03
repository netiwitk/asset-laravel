<?php

namespace App\Models;

use App\Enums\Availability;
use App\Enums\Condition;
use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only ledger row; database triggers reject UPDATE and DELETE. */
#[Fillable([
    'asset_id', 'actor_id', 'type',
    'from_condition', 'to_condition', 'from_availability', 'to_availability',
    'from_department_id', 'to_department_id', 'loan_id', 'repair_order_id',
    'occurred_at', 'note',
])]
class AssetMovement extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'from_condition' => Condition::class,
            'to_condition' => Condition::class,
            'from_availability' => Availability::class,
            'to_availability' => Availability::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }
}
