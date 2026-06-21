<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BatchDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'store_id',
        'delivery_quantity',
        'delivery_unit',
        'delivery_date',
        'delivery_no',
        'status',
        'returned_quantity',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'delivery_quantity' => 'decimal:2',
            'delivery_date' => 'date',
            'returned_quantity' => 'decimal:2',
        ];
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
