<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'specification',
        'category',
        'allergens',
        'ingredients',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'allergens' => 'array',
            'ingredients' => 'array',
            'status' => 'integer',
        ];
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function customerComplaints()
    {
        return $this->hasMany(CustomerComplaint::class);
    }
}
