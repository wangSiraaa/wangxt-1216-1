<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DetectionAbnormal;
use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DetectionAbnormalController extends Controller
{
    public function index(Request $request)
    {
        $query = DetectionAbnormal::with([
            'batch:id,batch_no,batch_type,product_name',
            'product:id,code,name',
            'reportedBy:id,name',
            'confirmedBy:id,name',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('abnormal_type')) {
            $query->where('abnormal_type', $request->abnormal_type);
        }

        if ($request->filled('batch_no')) {
            $query->where('batch_no', 'like', '%' . $request->batch_no . '%');
        }

        if ($request->filled('detection_item')) {
            $query->where('detection_item', 'like', '%' . $request->detection_item . '%');
        }

        $abnormals = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json($abnormals);
    }

    public function store(Request $request)
    {
        $request->validate([
            'abnormal_type' => [
                'required',
                Rule::in([
                    DetectionAbnormal::TYPE_ALLERGEN,
                    DetectionAbnormal::TYPE_MICROBE,
                    DetectionAbnormal::TYPE_PHYSICAL,
                    DetectionAbnormal::TYPE_CHEMICAL,
                    DetectionAbnormal::TYPE_OTHER,
                ]),
            ],
            'batch_id' => 'nullable|exists:batches,id',
            'batch_no' => 'nullable|string',
            'product_id' => 'nullable|exists:products,id',
            'product_name' => 'nullable|string',
            'detection_item' => 'required|string|max:100',
            'detection_value' => 'nullable|string',
            'standard_value' => 'nullable|string',
            'description' => 'required|string',
            'detection_report_no' => 'nullable|string',
            'detection_date' => 'nullable|date',
        ]);

        $batch = null;
        if ($request->filled('batch_id')) {
            $batch = Batch::find($request->batch_id);
        }

        $abnormal = DetectionAbnormal::create([
            'abnormal_no' => DetectionAbnormal::generateAbnormalNo(),
            'abnormal_type' => $request->abnormal_type,
            'batch_id' => $request->batch_id,
            'batch_no' => $request->batch_no ?: ($batch ? $batch->batch_no : null),
            'product_id' => $request->product_id,
            'product_name' => $request->product_name ?: ($batch ? $batch->product_name : null),
            'detection_item' => $request->detection_item,
            'detection_value' => $request->detection_value,
            'standard_value' => $request->standard_value,
            'description' => $request->description,
            'detection_report_no' => $request->detection_report_no,
            'detection_date' => $request->detection_date,
            'status' => DetectionAbnormal::STATUS_PENDING,
            'reported_by' => $request->user()->id,
        ]);

        $abnormal->load([
            'batch:id,batch_no,batch_type',
            'product:id,code,name',
            'reportedBy:id,name',
        ]);

        return response()->json([
            'message' => '检测异常已登记',
            'data' => $abnormal,
        ], 201);
    }

    public function show(DetectionAbnormal $detectionAbnormal)
    {
        $detectionAbnormal->load([
            'batch:id,batch_no,batch_type,product_name,quantity,unit,is_locked',
            'product:id,code,name,allergens',
            'reportedBy:id,name,email',
            'confirmedBy:id,name,email',
            'fileAttachments',
            'recallTasks:id,recall_no,title,status',
        ]);

        return response()->json($detectionAbnormal);
    }

    public function update(Request $request, DetectionAbnormal $detectionAbnormal)
    {
        if (! $detectionAbnormal->isPending()) {
            return response()->json(['message' => '只有待确认状态的异常才能修改'], 400);
        }

        $request->validate([
            'abnormal_type' => [
                'sometimes',
                Rule::in([
                    DetectionAbnormal::TYPE_ALLERGEN,
                    DetectionAbnormal::TYPE_MICROBE,
                    DetectionAbnormal::TYPE_PHYSICAL,
                    DetectionAbnormal::TYPE_CHEMICAL,
                    DetectionAbnormal::TYPE_OTHER,
                ]),
            ],
            'detection_item' => 'sometimes|string|max:100',
            'detection_value' => 'sometimes|nullable|string',
            'standard_value' => 'sometimes|nullable|string',
            'description' => 'sometimes|string',
            'detection_report_no' => 'sometimes|nullable|string',
            'detection_date' => 'sometimes|nullable|date',
        ]);

        $detectionAbnormal->update($request->only([
            'abnormal_type',
            'detection_item',
            'detection_value',
            'standard_value',
            'description',
            'detection_report_no',
            'detection_date',
        ]));

        return response()->json([
            'message' => '检测异常已更新',
            'data' => $detectionAbnormal,
        ]);
    }

    public function confirm(Request $request, DetectionAbnormal $detectionAbnormal)
    {
        if (! $detectionAbnormal->isPending()) {
            return response()->json(['message' => '只有待确认状态的异常才能确认'], 400);
        }

        $request->validate([
            'remark' => 'nullable|string',
        ]);

        $detectionAbnormal->confirm($request->user()->id, $request->remark);

        return response()->json([
            'message' => '检测异常已确认，可以发起召回',
            'data' => $detectionAbnormal->fresh(),
        ]);
    }

    public function reject(Request $request, DetectionAbnormal $detectionAbnormal)
    {
        if (! $detectionAbnormal->isPending()) {
            return response()->json(['message' => '只有待确认状态的异常才能驳回'], 400);
        }

        $request->validate([
            'remark' => 'required|string',
        ]);

        $detectionAbnormal->reject($request->user()->id, $request->remark);

        return response()->json([
            'message' => '检测异常已驳回',
            'data' => $detectionAbnormal->fresh(),
        ]);
    }

    public function abnormalTypes()
    {
        return response()->json([
            [
                'value' => DetectionAbnormal::TYPE_ALLERGEN,
                'label' => '过敏原异常',
            ],
            [
                'value' => DetectionAbnormal::TYPE_MICROBE,
                'label' => '微生物检测异常',
            ],
            [
                'value' => DetectionAbnormal::TYPE_PHYSICAL,
                'label' => '物理指标异常',
            ],
            [
                'value' => DetectionAbnormal::TYPE_CHEMICAL,
                'label' => '化学指标异常',
            ],
            [
                'value' => DetectionAbnormal::TYPE_OTHER,
                'label' => '其他异常',
            ],
        ]);
    }
}
