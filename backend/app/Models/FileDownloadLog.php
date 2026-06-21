<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FileDownloadLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_attachment_id',
        'user_id',
        'ip_address',
        'user_agent',
        'integrity_verified',
        'verified_hash',
    ];

    protected function casts(): array
    {
        return [
            'integrity_verified' => 'boolean',
        ];
    }

    public function fileAttachment()
    {
        return $this->belongsTo(FileAttachment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
