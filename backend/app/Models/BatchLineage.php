<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BatchLineage extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_batch_id',
        'child_batch_id',
        'usage_quantity',
        'usage_unit',
    ];

    protected function casts(): array
    {
        return [
            'usage_quantity' => 'decimal:2',
        ];
    }

    public function parentBatch()
    {
        return $this->belongsTo(Batch::class, 'parent_batch_id');
    }

    public function childBatch()
    {
        return $this->belongsTo(Batch::class, 'child_batch_id');
    }
}
