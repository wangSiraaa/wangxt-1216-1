<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'contact_person',
        'contact_phone',
        'address',
        'city',
        'district',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function batchDeliveries()
    {
        return $this->hasMany(BatchDelivery::class);
    }

    public function storeFeedbacks()
    {
        return $this->hasMany(StoreFeedback::class);
    }

    public function customerComplaints()
    {
        return $this->hasMany(CustomerComplaint::class);
    }
}
