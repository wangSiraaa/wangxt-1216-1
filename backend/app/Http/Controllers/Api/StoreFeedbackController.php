<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreFeedback;
use App\Models\StoreFeedbackItem;
use App\Models\RecallTask;
use App\Models\Batch;
use Illuminate\Http\Request;

class StoreFeedbackController extends Controller
{
    public function index(Request $request)
    {
        $query = StoreFeedback::with([
            'recallTask:id,recall_no,title,status',
            'store:id,code,name,contact_person,contact_phone',
            'submittedBy:id,name',
        ]);

        if ($request->filled('recall_task_id')) {
            $query->where('recall_task_id', $request->recall_task_id);
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('is_missing')) {
            $query->where('is_missing', $request->is_missing);
        }

        $query->orderByRaw("CASE 
            WHEN is_missing = 1 THEN 0 
            WHEN status = 'overdue' THEN 1
            WHEN status = 'pending' THEN 2
            WHEN status = 'submitted' THEN 3
            WHEN status = 'confirmed' THEN 4
            ELSE 5 END");

        $feedbacks = $query->paginate($request->input('per_page', 20));

        return response()->json($feedbacks);
    }

    public function store(Request $request)
    {
        $request->validate([
            'recall_task_id' => 'required|exists:recall_tasks,id',
            'store_id' => 'required|exists:stores,id',
            'items' => 'required|array',
            'items.*.batch_id' => 'required|exists:batches,id',
            'items.*.off_shelf_quantity' => 'required|numeric|min:0',
            'items.*.returned_quantity' => 'nullable|numeric|min:0',
            'items.*.destroyed_quantity' => 'nullable|numeric|min:0',
            'items.*.sold_quantity' => 'nullable|numeric|min:0',
            'remark' => 'nullable|string',
        ]);

        $existing = StoreFeedback::where('recall_task_id', $request->recall_task_id)
            ->where('store_id', $request->store_id)
            ->first();

        if ($existing && $existing->isConfirmed()) {
            return response()->json(['message' => '该门店反馈已总部确认，不能修改'], 400);
        }

        $totalOffShelf = 0;
        $totalReturned = 0;
        $totalDestroyed = 0;
        $totalSold = 0;

        $recallTask = RecallTask::find($request->recall_task_id);
        $relatedBatchIds = $recallTask->batches()->pluck('batches.id')->toArray();

        foreach ($request->items as $itemData) {
            if (! in_array($itemData['batch_id'], $relatedBatchIds)) {
                return response()->json(['message' => '批次 ' . ($itemData['batch_id'] ?? '') . ' 不在此召回任务范围内'], 400);
            }
            $totalOffShelf += $itemData['off_shelf_quantity'] ?? 0;
            $totalReturned += $itemData['returned_quantity'] ?? 0;
            $totalDestroyed += $itemData['destroyed_quantity'] ?? 0;
            $totalSold += $itemData['sold_quantity'] ?? 0;
        }

        if ($existing) {
            $feedback = $existing;
        } else {
            $feedback = new StoreFeedback();
            $feedback->recall_task_id = $request->recall_task_id;
            $feedback->store_id = $request->store_id;
            $store = \App\Models\Store::find($request->store_id);
            $feedback->store_name = $store->name;
            $feedback->store_code = $store->code;

            $receivedQty = 0;
            foreach ($relatedBatchIds as $batchId) {
                $delivery = Batch::find($batchId)->deliveries()
                    ->where('store_id', $request->store_id)
                    ->where('status', 'delivered')
                    ->sum('delivery_quantity');
                $receivedQty += $delivery;
            }
            $feedback->received_quantity = $receivedQty;
        }

        $feedback->off_shelf_quantity = $totalOffShelf;
        $feedback->returned_quantity = $totalReturned;
        $feedback->destroyed_quantity = $totalDestroyed;
        $feedback->sold_quantity = $totalSold;
        $feedback->updateRemainingQuantity();
        $feedback->remark = $request->remark;
        $feedback->is_missing = false;
        $feedback->save();

        if ($existing) {
            StoreFeedbackItem::where('store_feedback_id', $feedback->id)->delete();
        }

        foreach ($request->items as $itemData) {
            $batch = Batch::find($itemData['batch_id']);
            $receivedQty = $batch->deliveries()
                ->where('store_id', $request->store_id)
                ->where('status', 'delivered')
                ->sum('delivery_quantity');

            StoreFeedbackItem::create([
                'store_feedback_id' => $feedback->id,
                'batch_id' => $itemData['batch_id'],
                'batch_no' => $batch->batch_no,
                'product_name' => $batch->product_name,
                'received_quantity' => $receivedQty,
                'off_shelf_quantity' => $itemData['off_shelf_quantity'] ?? 0,
                'returned_quantity' => $itemData['returned_quantity'] ?? 0,
                'destroyed_quantity' => $itemData['destroyed_quantity'] ?? 0,
                'sold_quantity' => $itemData['sold_quantity'] ?? 0,
            ]);
        }

        $feedback->load(['items', 'store:id,code,name', 'recallTask:id,recall_no,title']);

        return response()->json([
            'message' => '门店下架反馈已保存',
            'data' => $feedback,
        ]);
    }

    public function show(StoreFeedback $storeFeedback)
    {
        $storeFeedback->load([
            'items',
            'store:id,code,name,address,contact_person,contact_phone',
            'recallTask:id,recall_no,title,description,status,recall_level',
            'submittedBy:id,name',
            'confirmedBy:id,name',
            'fileAttachments',
        ]);

        return response()->json($storeFeedback);
    }

    public function update(Request $request, StoreFeedback $storeFeedback)
    {
        if ($storeFeedback->isConfirmed()) {
            return response()->json(['message' => '已确认的反馈不能修改'], 400);
        }

        $request->validate([
            'items' => 'sometimes|array',
            'items.*.batch_id' => 'required_with:items|exists:batches,id',
            'items.*.off_shelf_quantity' => 'required_with:items|numeric|min:0',
            'items.*.returned_quantity' => 'nullable|numeric|min:0',
            'items.*.destroyed_quantity' => 'nullable|numeric|min:0',
            'items.*.sold_quantity' => 'nullable|numeric|min:0',
            'remark' => 'nullable|string',
        ]);

        if ($request->filled('items')) {
            $totalOffShelf = 0;
            $totalReturned = 0;
            $totalDestroyed = 0;
            $totalSold = 0;

            foreach ($request->items as $itemData) {
                $totalOffShelf += $itemData['off_shelf_quantity'] ?? 0;
                $totalReturned += $itemData['returned_quantity'] ?? 0;
                $totalDestroyed += $itemData['destroyed_quantity'] ?? 0;
                $totalSold += $itemData['sold_quantity'] ?? 0;
            }

            $storeFeedback->off_shelf_quantity = $totalOffShelf;
            $storeFeedback->returned_quantity = $totalReturned;
            $storeFeedback->destroyed_quantity = $totalDestroyed;
            $storeFeedback->sold_quantity = $totalSold;
            $storeFeedback->updateRemainingQuantity();

            StoreFeedbackItem::where('store_feedback_id', $storeFeedback->id)->delete();

            foreach ($request->items as $itemData) {
                $batch = Batch::find($itemData['batch_id']);
                $receivedQty = $batch->deliveries()
                    ->where('store_id', $storeFeedback->store_id)
                    ->where('status', 'delivered')
                    ->sum('delivery_quantity');

                StoreFeedbackItem::create([
                    'store_feedback_id' => $storeFeedback->id,
                    'batch_id' => $itemData['batch_id'],
                    'batch_no' => $batch->batch_no,
                    'product_name' => $batch->product_name,
                    'received_quantity' => $receivedQty,
                    'off_shelf_quantity' => $itemData['off_shelf_quantity'] ?? 0,
                    'returned_quantity' => $itemData['returned_quantity'] ?? 0,
                    'destroyed_quantity' => $itemData['destroyed_quantity'] ?? 0,
                    'sold_quantity' => $itemData['sold_quantity'] ?? 0,
                ]);
            }
        }

        if ($request->filled('remark')) {
            $storeFeedback->remark = $request->remark;
        }

        $storeFeedback->is_missing = false;
        $storeFeedback->save();

        $storeFeedback->load(['items']);

        return response()->json([
            'message' => '门店下架反馈已更新',
            'data' => $storeFeedback,
        ]);
    }

    public function submit(Request $request, StoreFeedback $storeFeedback)
    {
        if (! $storeFeedback->isPending() && ! $storeFeedback->isOverdue()) {
            return response()->json(['message' => '当前状态不能提交'], 400);
        }

        if ($storeFeedback->off_shelf_quantity <= 0) {
            return response()->json(['message' => '请先填写下架数量'], 400);
        }

        $storeFeedback->submit($request->user()->id);

        return response()->json([
            'message' => '门店下架反馈已提交',
            'data' => $storeFeedback->fresh(),
        ]);
    }

    public function unreportedStores($recallTaskId)
    {
        $recallTask = RecallTask::findOrFail($recallTaskId);

        $reportedStoreIds = StoreFeedback::where('recall_task_id', $recallTaskId)
            ->whereIn('status', [StoreFeedback::STATUS_SUBMITTED, StoreFeedback::STATUS_CONFIRMED])
            ->pluck('store_id');

        $unreported = StoreFeedback::where('recall_task_id', $recallTaskId)
            ->whereNotIn('store_id', $reportedStoreIds)
            ->with(['store:id,code,name,contact_person,contact_phone'])
            ->get();

        $unreported->each(function ($feedback) {
            if (! $feedback->is_missing) {
                $feedback->markAsMissing();
            }
        });

        return response()->json([
            'unreported_count' => $unreported->count(),
            'unreported_stores' => $unreported,
        ]);
    }
}
