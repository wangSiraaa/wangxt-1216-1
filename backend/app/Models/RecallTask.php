<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecallTask extends Model
{
    use HasFactory, SoftDeletes;

    const LEVEL_1 = 'level1';
    const LEVEL_2 = 'level2';
    const LEVEL_3 = 'level3';

    const REASON_ALLERGEN = 'allergen';
    const REASON_MICROBE = 'microbe';
    const REASON_QUALITY = 'quality';
    const REASON_COMPLAINT = 'complaint';
    const REASON_OTHER = 'other';

    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_PUBLISHED = 'published';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'recall_no',
        'title',
        'description',
        'recall_level',
        'recall_reason_type',
        'detection_abnormal_id',
        'customer_complaint_id',
        'status',
        'published_at',
        'published_by',
        'announcement_content',
        'expected_completion_date',
        'completed_at',
        'completed_by',
        'summary',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expected_completion_date' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public static function generateRecallNo(): string
    {
        return 'RCL' . date('YmdHis') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function detectionAbnormal()
    {
        return $this->belongsTo(DetectionAbnormal::class);
    }

    public function customerComplaint()
    {
        return $this->belongsTo(CustomerComplaint::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publishedBy()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function batches()
    {
        return $this->belongsToMany(Batch::class, 'recall_task_batches')
            ->withPivot(['batch_no', 'batch_type', 'product_name', 'total_quantity', 'delivered_quantity']);
    }

    public function storeFeedbacks()
    {
        return $this->hasMany(StoreFeedback::class);
    }

    public function fileAttachments()
    {
        return $this->morphMany(FileAttachment::class, 'related');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function canPublish(): bool
    {
        if (! $this->isDraft() && ! $this->isPending()) {
            return false;
        }
        if ($this->detection_abnormal_id) {
            $abnormal = DetectionAbnormal::find($this->detection_abnormal_id);
            if ($abnormal && ! $abnormal->canPublishRecall()) {
                return false;
            }
        }
        return true;
    }

    public function publish(int $userId): void
    {
        $this->update([
            'status' => self::STATUS_PUBLISHED,
            'published_at' => now(),
            'published_by' => $userId,
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }

    public function complete(int $userId, ?string $summary = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => $userId,
            'summary' => $summary,
        ]);
    }

    public function getFeedbackStats(): array
    {
        $total = $this->storeFeedbacks()->count();
        $submitted = $this->storeFeedbacks()->whereIn('status', [StoreFeedback::STATUS_SUBMITTED, StoreFeedback::STATUS_CONFIRMED])->count();
        $pending = $this->storeFeedbacks()->where('status', StoreFeedback::STATUS_PENDING)->count();
        $missing = $this->storeFeedbacks()->where('is_missing', true)->count();
        $confirmed = $this->storeFeedbacks()->where('status', StoreFeedback::STATUS_CONFIRMED)->count();

        $quantityAbnormal = 0;
        $remarkOnly = 0;
        $allFeedbacks = $this->storeFeedbacks()->get();
        foreach ($allFeedbacks as $fb) {
            if ($fb->isQuantityAbnormal()) {
                $quantityAbnormal++;
            } elseif (! empty($fb->unshelved_reason) && ! $fb->isMissingReport() && ! $fb->isOverdue()) {
                $remarkOnly++;
            }
        }

        return [
            'total' => $total,
            'submitted' => $submitted,
            'pending' => $pending,
            'missing' => $missing,
            'confirmed' => $confirmed,
            'quantity_abnormal' => $quantityAbnormal,
            'remark_only' => $remarkOnly,
            'submitted_rate' => $total > 0 ? round(($submitted / $total) * 100, 2) : 0,
        ];
    }

    public function getOffShelfStats(): array
    {
        $totalReceived = $this->storeFeedbacks()->sum('received_quantity');
        $totalOffShelf = $this->storeFeedbacks()->sum('off_shelf_quantity');
        $totalReturned = $this->storeFeedbacks()->sum('returned_quantity');
        $totalDestroyed = $this->storeFeedbacks()->sum('destroyed_quantity');
        $totalSold = $this->storeFeedbacks()->sum('sold_quantity');

        return [
            'received' => (float) $totalReceived,
            'off_shelf' => (float) $totalOffShelf,
            'returned' => (float) $totalReturned,
            'destroyed' => (float) $totalDestroyed,
            'sold' => (float) $totalSold,
            'off_shelf_rate' => $totalReceived > 0 ? round(($totalOffShelf / $totalReceived) * 100, 2) : 0,
        ];
    }
}
