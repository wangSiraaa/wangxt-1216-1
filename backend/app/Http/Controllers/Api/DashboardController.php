<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DetectionAbnormal;
use App\Models\RecallTask;
use App\Models\StoreFeedback;
use App\Models\CustomerComplaint;
use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary()
    {
        $totalAbnormals = DetectionAbnormal::count();
        $pendingAbnormals = DetectionAbnormal::where('status', DetectionAbnormal::STATUS_PENDING)->count();
        $confirmedAbnormals = DetectionAbnormal::where('status', DetectionAbnormal::STATUS_CONFIRMED)->count();

        $totalRecalls = RecallTask::count();
        $activeRecalls = RecallTask::where('status', RecallTask::STATUS_PUBLISHED)->count();
        $completedRecalls = RecallTask::where('status', RecallTask::STATUS_COMPLETED)->count();

        $totalComplaints = CustomerComplaint::count();
        $pendingComplaints = CustomerComplaint::whereIn('status', [
            CustomerComplaint::STATUS_PENDING,
            CustomerComplaint::STATUS_PROCESSING
        ])->count();

        $totalBatches = Batch::count();
        $lockedBatches = Batch::where('is_locked', 1)->count();

        $activeRecallTasks = RecallTask::select([
            'id',
            'recall_no',
            'title',
            'recall_level',
            'status',
            'created_at',
            'published_at'
        ])
            ->whereIn('status', [RecallTask::STATUS_PUBLISHED, RecallTask::STATUS_PENDING, RecallTask::STATUS_DRAFT])
            ->latest()
            ->limit(5)
            ->get();

        foreach ($activeRecallTasks as $task) {
            $task->feedback_stats = $task->getFeedbackStats();
            $task->off_shelf_stats = $task->getOffShelfStats();
        }

        $recentAbnormals = DetectionAbnormal::with(['batch:id,batch_no,batch_type', 'product:id,code,name'])
            ->latest()
            ->limit(5)
            ->get();

        $missingFeedbacks = StoreFeedback::with(['store:id,code,name', 'recallTask:id,recall_no,title'])
            ->where('is_missing', true)
            ->orWhere('status', StoreFeedback::STATUS_OVERDUE)
            ->latest()
            ->limit(10)
            ->get();

        foreach ($missingFeedbacks as $fb) {
            $fb->abnormal_type = $fb->getAbnormalType();
            $fb->abnormal_type_label = $fb->getAbnormalTypeLabel();
        }

        return response()->json([
            'statistics' => [
                'total_abnormals' => $totalAbnormals,
                'pending_abnormals' => $pendingAbnormals,
                'confirmed_abnormals' => $confirmedAbnormals,
                'total_recalls' => $totalRecalls,
                'active_recalls' => $activeRecalls,
                'completed_recalls' => $completedRecalls,
                'total_complaints' => $totalComplaints,
                'pending_complaints' => $pendingComplaints,
                'total_batches' => $totalBatches,
                'locked_batches' => $lockedBatches,
            ],
            'active_recall_tasks' => $activeRecallTasks,
            'recent_abnormals' => $recentAbnormals,
            'missing_feedbacks' => $missingFeedbacks,
        ]);
    }

    public function storeFeedbackStatus(Request $request)
    {
        $recallTaskId = $request->input('recall_task_id');

        $query = StoreFeedback::query()
            ->when($recallTaskId, function ($q, $id) {
                return $q->where('recall_task_id', $id);
            });

        $totalStores = (clone $query)->count();
        $submitted = (clone $query)->where('status', StoreFeedback::STATUS_SUBMITTED)->count();
        $pending = (clone $query)->where('status', StoreFeedback::STATUS_PENDING)->count();
        $confirmed = (clone $query)->where('status', StoreFeedback::STATUS_CONFIRMED)->count();
        $missing = (clone $query)->where('is_missing', true)->count();
        $overdue = (clone $query)->where('status', StoreFeedback::STATUS_OVERDUE)->count();

        $quantityAbnormal = 0;
        $remarkOnly = 0;
        $allFeedbacks = (clone $query)->get();
        foreach ($allFeedbacks as $fb) {
            if ($fb->isQuantityAbnormal()) {
                $quantityAbnormal++;
            } elseif (! empty($fb->unshelved_reason) && ! $fb->isMissingReport() && ! $fb->isOverdue()) {
                $remarkOnly++;
            }
        }

        $feedbacks = (clone $query)
            ->with(['store:id,code,name', 'recallTask:id,recall_no,title'])
            ->orderByRaw("CASE 
                WHEN is_missing = 1 THEN 0 
                WHEN status = 'overdue' THEN 1
                WHEN status = 'pending' THEN 2
                WHEN status = 'submitted' THEN 3
                WHEN status = 'confirmed' THEN 4
                ELSE 5 END")
            ->orderByDesc('submitted_at')
            ->limit(50)
            ->get();

        foreach ($feedbacks as $fb) {
            $fb->abnormal_type = $fb->getAbnormalType();
            $fb->abnormal_type_label = $fb->getAbnormalTypeLabel();
        }

        return response()->json([
            'summary' => [
                'total' => $totalStores,
                'submitted' => $submitted,
                'pending' => $pending,
                'confirmed' => $confirmed,
                'missing' => $missing,
                'overdue' => $overdue,
                'quantity_abnormal' => $quantityAbnormal,
                'remark_only' => $remarkOnly,
            ],
            'feedbacks' => $feedbacks,
        ]);
    }

    public function abnormalTrend(Request $request)
    {
        $days = $request->input('days', 30);

        $abnormalTrend = DetectionAbnormal::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total'),
            DB::raw("SUM(CASE WHEN abnormal_type = 'allergen' THEN 1 ELSE 0 END) as allergen_count"),
            DB::raw("SUM(CASE WHEN abnormal_type = 'microbe' THEN 1 ELSE 0 END) as microbe_count")
        )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        $recallTrend = RecallTask::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total'),
            DB::raw("SUM(CASE WHEN recall_level = 'level1' THEN 1 ELSE 0 END) as level1_count"),
            DB::raw("SUM(CASE WHEN recall_level = 'level2' THEN 1 ELSE 0 END) as level2_count"),
            DB::raw("SUM(CASE WHEN recall_level = 'level3' THEN 1 ELSE 0 END) as level3_count")
        )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return response()->json([
            'abnormal_trend' => $abnormalTrend,
            'recall_trend' => $recallTrend,
        ]);
    }
}
