<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FileAttachment;
use App\Models\FileDownloadLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:51200',
            'related_type' => 'nullable|string|in:detection_abnormal,recall_task,customer_complaint,store_feedback',
            'related_id' => 'nullable|integer',
        ]);

        $uploadedFile = $request->file('file');

        $originalName = $uploadedFile->getClientOriginalName();
        $extension = $uploadedFile->getClientOriginalExtension();
        $storedName = date('YmdHis') . '_' . Str::random(16) . '.' . $extension;
        $relativePath = 'uploads/' . date('Y/m/d');
        $fullPath = $uploadedFile->storeAs($relativePath, $storedName, 'local');

        $filePath = Storage::disk('local')->path($fullPath);
        $fileHash = hash_file('sha256', $filePath);

        $fileAttachment = FileAttachment::create([
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'file_path' => $fullPath,
            'file_size' => $uploadedFile->getSize(),
            'mime_type' => $uploadedFile->getMimeType(),
            'file_hash' => $fileHash,
            'related_type' => $request->related_type,
            'related_id' => $request->related_id,
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => '文件上传成功',
            'data' => [
                'id' => $fileAttachment->id,
                'original_name' => $fileAttachment->original_name,
                'file_size' => $fileAttachment->file_size,
                'file_size_human' => $fileAttachment->getHumanReadableSize(),
                'mime_type' => $fileAttachment->mime_type,
                'file_hash' => $fileAttachment->file_hash,
                'uploaded_at' => $fileAttachment->created_at,
            ],
        ], 201);
    }

    public function show(FileAttachment $fileAttachment)
    {
        $fileAttachment->load([
            'uploadedBy:id,name,email',
        ]);

        return response()->json([
            'id' => $fileAttachment->id,
            'original_name' => $fileAttachment->original_name,
            'stored_name' => $fileAttachment->stored_name,
            'file_size' => $fileAttachment->file_size,
            'file_size_human' => $fileAttachment->getHumanReadableSize(),
            'mime_type' => $fileAttachment->mime_type,
            'file_hash' => $fileAttachment->file_hash,
            'related_type' => $fileAttachment->related_type,
            'related_id' => $fileAttachment->related_id,
            'uploaded_by' => $fileAttachment->uploadedBy ? [
                'id' => $fileAttachment->uploadedBy->id,
                'name' => $fileAttachment->uploadedBy->name,
            ] : null,
            'download_count' => $fileAttachment->download_count,
            'created_at' => $fileAttachment->created_at,
            'file_exists' => $fileAttachment->exists(),
        ]);
    }

    public function download(Request $request, FileAttachment $fileAttachment)
    {
        if (! $fileAttachment->exists()) {
            return response()->json(['message' => '文件不存在'], 404);
        }

        $integrityVerified = $fileAttachment->verifyIntegrity();

        FileDownloadLog::create([
            'file_attachment_id' => $fileAttachment->id,
            'user_id' => $request->user() ? $request->user()->id : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'integrity_verified' => $integrityVerified,
            'verified_hash' => $integrityVerified ? $fileAttachment->file_hash : null,
        ]);

        $fileAttachment->incrementDownloadCount();

        $headers = [
            'Content-Type' => $fileAttachment->mime_type ?: 'application/octet-stream',
            'X-File-Hash' => $fileAttachment->file_hash,
            'X-Integrity-Verified' => $integrityVerified ? 'true' : 'false',
            'X-File-Size' => $fileAttachment->file_size,
        ];

        return Storage::disk('local')->download(
            $fileAttachment->file_path,
            $fileAttachment->original_name,
            $headers
        );
    }

    public function destroy(FileAttachment $fileAttachment)
    {
        if ($fileAttachment->exists()) {
            Storage::disk('local')->delete($fileAttachment->file_path);
        }

        $fileAttachment->delete();

        return response()->json([
            'message' => '文件已删除',
        ]);
    }

    public function downloadHistory(Request $request, FileAttachment $fileAttachment)
    {
        $logs = FileDownloadLog::where('file_attachment_id', $fileAttachment->id)
            ->with(['user:id,name,email'])
            ->latest()
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'file' => [
                'id' => $fileAttachment->id,
                'original_name' => $fileAttachment->original_name,
                'total_downloads' => $fileAttachment->download_count,
                'file_hash' => $fileAttachment->file_hash,
            ],
            'logs' => $logs,
        ]);
    }
}
