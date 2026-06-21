<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Batch extends Model
{
    use HasFactory, SoftDeletes;

    const BATCH_TYPE_RAW_MATERIAL = 'raw_material';
    const BATCH_TYPE_SEMI_FINISHED = 'semi_finished';
    const BATCH_TYPE_FINISHED = 'finished';

    protected $fillable = [
        'batch_no',
        'batch_type',
        'product_id',
        'product_name',
        'product_code',
        'quantity',
        'unit',
        'production_date',
        'expiry_date',
        'supplier_name',
        'supplier_batch_no',
        'remark',
        'is_locked',
        'lock_reason',
        'locked_at',
        'locked_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'production_date' => 'date',
            'expiry_date' => 'date',
            'locked_at' => 'datetime',
            'is_locked' => 'integer',
            'status' => 'integer',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function parentLineages()
    {
        return $this->hasMany(BatchLineage::class, 'child_batch_id');
    }

    public function childLineages()
    {
        return $this->hasMany(BatchLineage::class, 'parent_batch_id');
    }

    public function parentBatches()
    {
        return $this->belongsToMany(Batch::class, 'batch_lineages', 'child_batch_id', 'parent_batch_id')
            ->withPivot(['usage_quantity', 'usage_unit']);
    }

    public function childBatches()
    {
        return $this->belongsToMany(Batch::class, 'batch_lineages', 'parent_batch_id', 'child_batch_id')
            ->withPivot(['usage_quantity', 'usage_unit']);
    }

    public function deliveries()
    {
        return $this->hasMany(BatchDelivery::class);
    }

    public function detectionAbnormals()
    {
        return $this->hasMany(DetectionAbnormal::class);
    }

    public function customerComplaints()
    {
        return $this->hasMany(CustomerComplaint::class);
    }

    public function recallTasks()
    {
        return $this->belongsToMany(RecallTask::class, 'recall_task_batches')
            ->withPivot(['batch_no', 'batch_type', 'product_name', 'total_quantity', 'delivered_quantity']);
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function isRawMaterial(): bool
    {
        return $this->batch_type === self::BATCH_TYPE_RAW_MATERIAL;
    }

    public function isSemiFinished(): bool
    {
        return $this->batch_type === self::BATCH_TYPE_SEMI_FINISHED;
    }

    public function isFinished(): bool
    {
        return $this->batch_type === self::BATCH_TYPE_FINISHED;
    }

    public function lock(string $reason, int $userId): void
    {
        $this->update([
            'is_locked' => 1,
            'lock_reason' => $reason,
            'locked_at' => now(),
            'locked_by' => $userId,
        ]);
    }

    public function unlock(): void
    {
        $this->update([
            'is_locked' => 0,
            'lock_reason' => null,
            'locked_at' => null,
            'locked_by' => null,
        ]);
    }

    public function getRelatedBatches(): array
    {
        $relatedIds = collect();

        if ($this->isRawMaterial()) {
            $this->load('childBatches.childBatches');
            $relatedIds = $this->collectDescendants($this);
        } else {
            $this->load('parentBatches.parentBatches');
            $relatedIds = $this->collectAncestors($this);
            if ($this->isSemiFinished()) {
                $this->load('childBatches');
                $relatedIds = $relatedIds->merge($this->collectDescendants($this));
            }
        }

        return $relatedIds->unique('id')->values()->all();
    }

    protected function collectDescendants($batch, &$collected = null)
    {
        if ($collected === null) {
            $collected = collect();
        }
        foreach ($batch->childBatches as $child) {
            if (! $collected->contains('id', $child->id)) {
                $collected->push($child);
                $this->collectDescendants($child, $collected);
            }
        }
        return $collected;
    }

    protected function collectAncestors($batch, &$collected = null)
    {
        if ($collected === null) {
            $collected = collect();
        }
        foreach ($batch->parentBatches as $parent) {
            if (! $collected->contains('id', $parent->id)) {
                $collected->push($parent);
                $this->collectAncestors($parent, $collected);
            }
        }
        return $collected;
    }
}
