<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreFeedbackItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_feedback_id',
        'batch_id',
        'batch_no',
        'product_name',
        'received_quantity',
        'off_shelf_quantity',
        'returned_quantity',
        'destroyed_quantity',
        'sold_quantity',
    ];

    protected function casts(): array
    {
        return [
            'received_quantity' => 'decimal:2',
            'off_shelf_quantity' => 'decimal:2',
            'returned_quantity' => 'decimal:2',
            'destroyed_quantity' => 'decimal:2',
            'sold_quantity' => 'decimal:2',
        ];
    }

    public function storeFeedback()
    {
        return $this->belongsTo(StoreFeedback::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
