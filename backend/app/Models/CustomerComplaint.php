<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerComplaint extends Model
{
    use HasFactory, SoftDeletes;

    const TYPE_ALLERGY = 'allergy';
    const TYPE_FOOD_POISONING = 'food_poisoning';
    const TYPE_FOREIGN_MATTER = 'foreign_matter';
    const TYPE_QUALITY = 'quality';
    const TYPE_PACKAGING = 'packaging';
    const TYPE_OTHER = 'other';

    const SEVERITY_MILD = 'mild';
    const SEVERITY_GENERAL = 'general';
    const SEVERITY_SERIOUS = 'serious';
    const SEVERITY_CRITICAL = 'critical';

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'complaint_no',
        'complaint_type',
        'customer_name',
        'customer_phone',
        'store_id',
        'product_name',
        'product_id',
        'batch_no',
        'batch_id',
        'purchase_date',
        'occurrence_time',
        'description',
        'symptoms',
        'severity',
        'handling_process',
        'status',
        'handled_by',
        'resolved_at',
        'resolution',
        'reported_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'occurrence_time' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public static function generateComplaintNo(): string
    {
        return 'CMP' . date('YmdHis') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by');
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

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function resolve(int $userId, string $resolution): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'handled_by' => $userId,
            'resolved_at' => now(),
            'resolution' => $resolution,
        ]);
    }
}
