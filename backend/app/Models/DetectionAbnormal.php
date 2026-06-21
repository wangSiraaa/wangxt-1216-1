<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DetectionAbnormal extends Model
{
    use HasFactory, SoftDeletes;

    const TYPE_ALLERGEN = 'allergen';
    const TYPE_MICROBE = 'microbe';
    const TYPE_PHYSICAL = 'physical';
    const TYPE_CHEMICAL = 'chemical';
    const TYPE_OTHER = 'other';

    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_REJECTED = 'rejected';
    const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'abnormal_no',
        'abnormal_type',
        'batch_id',
        'batch_no',
        'product_id',
        'product_name',
        'detection_item',
        'detection_value',
        'standard_value',
        'description',
        'detection_report_no',
        'detection_date',
        'status',
        'confirmed_at',
        'confirmed_by',
        'confirm_remark',
        'reported_by',
    ];

    protected function casts(): array
    {
        return [
            'detection_date' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    public static function generateAbnormalNo(): string
    {
        return 'ABN' . date('YmdHis') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function recallTasks()
    {
        return $this->hasMany(RecallTask::class);
    }

    public function fileAttachments()
    {
        return $this->morphMany(FileAttachment::class, 'related');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function confirm(int $userId, ?string $remark = null): void
    {
        $this->update([
            'status' => self::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'confirmed_by' => $userId,
            'confirm_remark' => $remark,
        ]);
    }

    public function reject(int $userId, ?string $remark = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'confirmed_at' => now(),
            'confirmed_by' => $userId,
            'confirm_remark' => $remark,
        ]);
    }

    public function canPublishRecall(): bool
    {
        return $this->isConfirmed();
    }
}
