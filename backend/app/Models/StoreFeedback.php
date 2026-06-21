<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreFeedback extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CONFIRMED = 'confirmed';

    protected $fillable = [
        'recall_task_id',
        'store_id',
        'store_name',
        'store_code',
        'received_quantity',
        'off_shelf_quantity',
        'remaining_quantity',
        'returned_quantity',
        'destroyed_quantity',
        'sold_quantity',
        'status',
        'remark',
        'submitted_at',
        'submitted_by',
        'confirmed_at',
        'confirmed_by',
        'is_overdue',
        'is_missing',
    ];

    protected function casts(): array
    {
        return [
            'received_quantity' => 'decimal:2',
            'off_shelf_quantity' => 'decimal:2',
            'remaining_quantity' => 'decimal:2',
            'returned_quantity' => 'decimal:2',
            'destroyed_quantity' => 'decimal:2',
            'sold_quantity' => 'decimal:2',
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'is_overdue' => 'boolean',
            'is_missing' => 'boolean',
        ];
    }

    public function recallTask()
    {
        return $this->belongsTo(RecallTask::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function items()
    {
        return $this->hasMany(StoreFeedbackItem::class);
    }

    public function fileAttachments()
    {
        return $this->morphMany(FileAttachment::class, 'related');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OVERDUE || $this->is_overdue;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isMissingReport(): bool
    {
        return $this->is_missing;
    }

    public function submit(int $userId): void
    {
        $this->update([
            'status' => self::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'submitted_by' => $userId,
        ]);
    }

    public function confirm(int $userId): void
    {
        $this->update([
            'status' => self::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'confirmed_by' => $userId,
        ]);
    }

    public function markAsMissing(): void
    {
        $this->update([
            'is_missing' => true,
            'status' => self::STATUS_OVERDUE,
            'is_overdue' => true,
        ]);
    }

    public function unmarkMissing(): void
    {
        $this->update([
            'is_missing' => false,
        ]);
    }

    public function updateRemainingQuantity(): void
    {
        $remaining = max(0, $this->received_quantity - $this->off_shelf_quantity);
        $this->remaining_quantity = $remaining;
    }
}
