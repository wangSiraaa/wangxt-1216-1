<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\DetectionAbnormalController;
use App\Http\Controllers\Api\RecallTaskController;
use App\Http\Controllers\Api\StoreFeedbackController;
use App\Http\Controllers\Api\CustomerComplaintController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\AuthController;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/auth/user', [AuthController::class, 'user'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/store-feedback-status', [DashboardController::class, 'storeFeedbackStatus']);
    Route::get('/dashboard/abnormal-trend', [DashboardController::class, 'abnormalTrend']);

    Route::get('/batches', [BatchController::class, 'index']);
    Route::get('/batches/{batch}', [BatchController::class, 'show']);
    Route::get('/batches/{batch}/lineage', [BatchController::class, 'lineage']);
    Route::get('/batches/{batch}/related-batches', [BatchController::class, 'relatedBatches']);
    Route::post('/batches/{batch}/lock', [BatchController::class, 'lock']);
    Route::post('/batches/{batch}/unlock', [BatchController::class, 'unlock']);
    Route::get('/batch-types', [BatchController::class, 'batchTypes']);

    Route::get('/detection-abnormals', [DetectionAbnormalController::class, 'index']);
    Route::post('/detection-abnormals', [DetectionAbnormalController::class, 'store']);
    Route::get('/detection-abnormals/{detectionAbnormal}', [DetectionAbnormalController::class, 'show']);
    Route::put('/detection-abnormals/{detectionAbnormal}', [DetectionAbnormalController::class, 'update']);
    Route::post('/detection-abnormals/{detectionAbnormal}/confirm', [DetectionAbnormalController::class, 'confirm']);
    Route::post('/detection-abnormals/{detectionAbnormal}/reject', [DetectionAbnormalController::class, 'reject']);
    Route::get('/detection-abnormal-types', [DetectionAbnormalController::class, 'abnormalTypes']);

    Route::get('/recall-tasks', [RecallTaskController::class, 'index']);
    Route::post('/recall-tasks', [RecallTaskController::class, 'store']);
    Route::get('/recall-tasks/{recallTask}', [RecallTaskController::class, 'show']);
    Route::put('/recall-tasks/{recallTask}', [RecallTaskController::class, 'update']);
    Route::post('/recall-tasks/{recallTask}/publish', [RecallTaskController::class, 'publish']);
    Route::post('/recall-tasks/{recallTask}/cancel', [RecallTaskController::class, 'cancel']);
    Route::post('/recall-tasks/{recallTask}/complete', [RecallTaskController::class, 'complete']);
    Route::get('/recall-task-statuses', [RecallTaskController::class, 'statuses']);

    Route::get('/store-feedbacks', [StoreFeedbackController::class, 'index']);
    Route::post('/store-feedbacks', [StoreFeedbackController::class, 'store']);
    Route::get('/store-feedbacks/{storeFeedback}', [StoreFeedbackController::class, 'show']);
    Route::put('/store-feedbacks/{storeFeedback}', [StoreFeedbackController::class, 'update']);
    Route::post('/store-feedbacks/{storeFeedback}/submit', [StoreFeedbackController::class, 'submit']);
    Route::get('/unreported-stores/{recallTaskId}', [StoreFeedbackController::class, 'unreportedStores']);

    Route::get('/customer-complaints', [CustomerComplaintController::class, 'index']);
    Route::post('/customer-complaints', [CustomerComplaintController::class, 'store']);
    Route::get('/customer-complaints/{customerComplaint}', [CustomerComplaintController::class, 'show']);
    Route::put('/customer-complaints/{customerComplaint}', [CustomerComplaintController::class, 'update']);
    Route::post('/customer-complaints/{customerComplaint}/resolve', [CustomerComplaintController::class, 'resolve']);
    Route::get('/complaint-types', [CustomerComplaintController::class, 'complaintTypes']);

    Route::post('/files/upload', [FileController::class, 'upload']);
    Route::get('/files/{fileAttachment}/download', [FileController::class, 'download']);
    Route::get('/files/{fileAttachment}', [FileController::class, 'show']);
    Route::delete('/files/{fileAttachment}', [FileController::class, 'destroy']);
    Route::get('/files/download-history/{fileAttachment}', [FileController::class, 'downloadHistory']);

    Route::get('/stores', [StoreController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
});
