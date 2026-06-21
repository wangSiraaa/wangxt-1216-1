<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecallTask;
use App\Models\Batch;
use App\Models\Store;
use App\Models\StoreFeedback;
use App\Models\DetectionAbnormal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class RecallTaskController extends Controller
{
    public function index(Request $request)
    {
        $query = RecallTask::with([
            'createdBy:id,name',
            'detectionAbnormal:id,abnormal_no,abnormal_type,detection_item,status',
            'customerComplaint:id,complaint_no,complaint_type,severity',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('recall_level')) {
            $query->where('recall_level', $request->recall_level);
        }

        if ($request->filled('recall_no')) {
            $query->where('recall_no', 'like', '%' . $request->recall_no . '%');
        }

        if ($request->filled('title')) {
            $query->where('title', 'like', '%' . $request->title . '%');
        }

        $tasks = $query->latest()->paginate($request->input('per_page', 20));

        foreach ($tasks as $task) {
            $task->feedback_stats = $task->getFeedbackStats();
            $task->off_shelf_stats = $task->getOffShelfStats();
        }

        return response()->json($tasks);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'recall_level' => [
                'required',
                Rule::in([RecallTask::LEVEL_1, RecallTask::LEVEL_2, RecallTask::LEVEL_3]),
            ],
            'recall_reason_type' => 'required|string',
            'detection_abnormal_id' => 'nullable|exists:detection_abnormals,id',
            'customer_complaint_id' => 'nullable|exists:customer_complaints,id',
            'batch_ids' => 'required|array|min:1',
            'batch_ids.*' => 'exists:batches,id',
            'expected_completion_date' => 'nullable|date',
            'announcement_content' => 'nullable|string',
        ]);

        if ($request->filled('detection_abnormal_id')) {
            $abnormal = DetectionAbnormal::find($request->detection_abnormal_id);
            if (! $abnormal->canPublishRecall()) {
                return response()->json([
                    'message' => '关联的检测异常未确认，不能创建召回任务',
                ], 400);
            }
        }

        DB::beginTransaction();
        try {
            $recallTask = RecallTask::create([
                'recall_no' => RecallTask::generateRecallNo(),
                'title' => $request->title,
                'description' => $request->description,
                'recall_level' => $request->recall_level,
                'recall_reason_type' => $request->recall_reason_type,
                'detection_abnormal_id' => $request->detection_abnormal_id,
                'customer_complaint_id' => $request->customer_complaint_id,
                'status' => RecallTask::STATUS_DRAFT,
                'expected_completion_date' => $request->expected_completion_date,
                'announcement_content' => $request->announcement_content,
                'created_by' => $request->user()->id,
            ]);

            $batches = Batch::whereIn('id', $request->batch_ids)->get();
            $allBatchIds = collect($request->batch_ids);

            foreach ($batches as $batch) {
                if ($batch->isRawMaterial()) {
                    $related = $batch->getRelatedBatches();
                    $allBatchIds = $allBatchIds->merge(collect($related)->pluck('id'));
                }
            }

            $allBatchIds = $allBatchIds->unique()->values();
            $allBatches = Batch::whereIn('id', $allBatchIds)->get();

            $storeIds = collect();

            foreach ($allBatches as $batch) {
                $deliveredQty = $batch->deliveries()->where('status', 'delivered')->sum('delivery_quantity');

                $recallTask->batches()->attach($batch->id, [
                    'batch_no' => $batch->batch_no,
                    'batch_type' => $batch->batch_type,
                    'product_name' => $batch->product_name,
                    'total_quantity' => $batch->quantity,
                    'delivered_quantity' => $deliveredQty,
                ]);

                $deliveries = $batch->deliveries()->where('status', 'delivered')->get();
                foreach ($deliveries as $delivery) {
                    $storeIds->push($delivery->store_id);
                }

                $batch->lock('召回任务锁定: ' . $recallTask->recall_no, $request->user()->id);
            }

            $storeIds = $storeIds->unique()->values();
            $stores = Store::whereIn('id', $storeIds)->get();

            foreach ($stores as $store) {
                $totalDelivered = 0;
                foreach ($allBatches as $batch) {
                    $delivery = $batch->deliveries()
                        ->where('store_id', $store->id)
                        ->where('status', 'delivered')
                        ->sum('delivery_quantity');
                    $totalDelivered += $delivery;
                }

                StoreFeedback::create([
                    'recall_task_id' => $recallTask->id,
                    'store_id' => $store->id,
                    'store_name' => $store->name,
                    'store_code' => $store->code,
                    'received_quantity' => $totalDelivered,
                    'status' => StoreFeedback::STATUS_PENDING,
                ]);
            }

            DB::commit();

            $recallTask->load([
                'batches:id,batch_no,batch_type,product_name,quantity,is_locked',
                'storeFeedbacks.store:id,code,name',
            ]);

            return response()->json([
                'message' => '召回任务已创建',
                'data' => $recallTask,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function show(RecallTask $recallTask)
    {
        $recallTask->load([
            'batches:id,batch_no,batch_type,product_name,quantity,is_locked',
            'storeFeedbacks.store:id,code,name',
            'storeFeedbacks.items',
            'detectionAbnormal:id,abnormal_no,abnormal_type,detection_item,status',
            'customerComplaint:id,complaint_no,complaint_type',
            'createdBy:id,name',
            'publishedBy:id,name',
            'completedBy:id,name',
            'fileAttachments',
        ]);

        foreach ($recallTask->storeFeedbacks as $fb) {
            $fb->abnormal_type = $fb->getAbnormalType();
            $fb->abnormal_type_label = $fb->getAbnormalTypeLabel();
        }

        $recallTask->feedback_stats = $recallTask->getFeedbackStats();
        $recallTask->off_shelf_stats = $recallTask->getOffShelfStats();

        return response()->json($recallTask);
    }

    public function update(Request $request, RecallTask $recallTask)
    {
        if (! $recallTask->isDraft() && ! $recallTask->isPending()) {
            return response()->json(['message' => '只有草稿或待发布状态的召回任务才能修改'], 400);
        }

        $request->validate([
            'title' => 'sometimes|string|max:200',
            'description' => 'sometimes|string',
            'recall_level' => [
                'sometimes',
                Rule::in([RecallTask::LEVEL_1, RecallTask::LEVEL_2, RecallTask::LEVEL_3]),
            ],
            'expected_completion_date' => 'sometimes|nullable|date',
            'announcement_content' => 'sometimes|nullable|string',
        ]);

        $recallTask->update($request->only([
            'title',
            'description',
            'recall_level',
            'expected_completion_date',
            'announcement_content',
        ]));

        return response()->json([
            'message' => '召回任务已更新',
            'data' => $recallTask,
        ]);
    }

    public function publish(Request $request, RecallTask $recallTask)
    {
        if (! $recallTask->canPublish()) {
            if ($recallTask->detection_abnormal_id) {
                return response()->json([
                    'message' => '检测未确认的异常不能发布召回公告',
                ], 400);
            }
            return response()->json([
                'message' => '当前状态不能发布召回公告',
            ], 400);
        }

        $recallTask->publish($request->user()->id);

        return response()->json([
            'message' => '召回公告已发布',
            'data' => $recallTask->fresh(),
        ]);
    }

    public function cancel(RecallTask $recallTask)
    {
        if ($recallTask->isCompleted()) {
            return response()->json(['message' => '已完成的召回任务不能取消'], 400);
        }

        $recallTask->cancel();

        return response()->json([
            'message' => '召回任务已取消',
            'data' => $recallTask->fresh(),
        ]);
    }

    public function complete(Request $request, RecallTask $recallTask)
    {
        if (! $recallTask->isPublished()) {
            return response()->json(['message' => '只有已发布的召回任务才能完成'], 400);
        }

        $pendingFeedbacks = $recallTask->storeFeedbacks()
            ->where('status', StoreFeedback::STATUS_PENDING)
            ->count();

        if ($pendingFeedbacks > 0) {
            return response()->json([
                'message' => '还有 ' . $pendingFeedbacks . ' 个门店未反馈下架数量，是否确定完成？',
                'pending_count' => $pendingFeedbacks,
            ], 400);
        }

        $request->validate([
            'summary' => 'nullable|string',
        ]);

        $recallTask->complete($request->user()->id, $request->summary);

        return response()->json([
            'message' => '召回任务已完成',
            'data' => $recallTask->fresh(),
        ]);
    }

    public function statuses()
    {
        return response()->json([
            [
                'value' => RecallTask::STATUS_DRAFT,
                'label' => '草稿',
            ],
            [
                'value' => RecallTask::STATUS_PENDING,
                'label' => '待发布',
            ],
            [
                'value' => RecallTask::STATUS_PUBLISHED,
                'label' => '已发布',
            ],
            [
                'value' => RecallTask::STATUS_CANCELLED,
                'label' => '已取消',
            ],
            [
                'value' => RecallTask::STATUS_COMPLETED,
                'label' => '已完成',
            ],
        ]);
    }
}
