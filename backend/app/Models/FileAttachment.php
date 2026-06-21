<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class FileAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'original_name',
        'stored_name',
        'file_path',
        'file_size',
        'mime_type',
        'file_hash',
        'related_type',
        'related_id',
        'uploaded_by',
        'download_count',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'download_count' => 'integer',
        ];
    }

    public function related()
    {
        return $this->morphTo();
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function downloadLogs()
    {
        return $this->hasMany(FileDownloadLog::class);
    }

    public function getFullPath(): string
    {
        return Storage::disk('local')->path($this->file_path);
    }

    public function exists(): bool
    {
        return Storage::disk('local')->exists($this->file_path);
    }

    public function getContent()
    {
        return Storage::disk('local')->get($this->file_path);
    }

    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
    }

    public function verifyIntegrity(): bool
    {
        if (! $this->exists()) {
            return false;
        }
        $actualHash = hash_file('sha256', $this->getFullPath());
        return hash_equals($this->file_hash, $actualHash);
    }

    public function getHumanReadableSize(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
