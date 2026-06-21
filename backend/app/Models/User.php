<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'store_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function isHqAdmin(): bool
    {
        return $this->role === 'hq_admin';
    }

    public function isQcSupervisor(): bool
    {
        return $this->role === 'qc_supervisor';
    }

    public function isWarehouseStaff(): bool
    {
        return $this->role === 'warehouse_staff';
    }

    public function isStoreManager(): bool
    {
        return $this->role === 'store_manager';
    }
}
