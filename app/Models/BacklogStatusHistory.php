<?php

namespace App\Models;

use App\Enums\BacklogStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One recorded status move of a backlog item.
 *
 * Written in the same transaction as the item update, so the log never
 * disagrees with the item. The row is append only.
 *
 * @property int $id
 * @property int $backlog_item_id
 * @property BacklogStatus|null $from_status
 * @property BacklogStatus $to_status
 * @property int|null $changed_by
 * @property Carbon|null $created_at
 */
#[Fillable(['backlog_item_id', 'from_status', 'to_status', 'changed_by', 'created_at'])]
class BacklogStatusHistory extends Model
{
    /**
     * The log is append only, so it carries no updated_at.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => BacklogStatus::class,
            'to_status' => BacklogStatus::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the item the row belongs to.
     *
     * @return BelongsTo<BacklogItem, $this>
     */
    public function backlogItem(): BelongsTo
    {
        return $this->belongsTo(BacklogItem::class);
    }

    /**
     * Get the account that made the move.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
