<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerComplaint;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerComplaintController extends Controller
{
    public function index(Request $request)
    {
        $query = CustomerComplaint::with([
            'store:id,code,name',
            'product:id,code,name',
            'batch:id,batch_no,batch_type',
            'reportedBy:id,name',
            'handledBy:id,name',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('complaint_type')) {
            $query->where('complaint_type', $request->complaint_type);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        if ($request->filled('batch_no')) {
            $query->where('batch_no', 'like', '%' . $request->batch_no . '%');
        }

        $complaints = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json($complaints);
    }

    public function store(Request $request)
    {
        $request->validate([
            'complaint_type' => [
                'required',
                Rule::in([
                    CustomerComplaint::TYPE_ALLERGY,
                    CustomerComplaint::TYPE_FOOD_POISONING,
                    CustomerComplaint::TYPE_FOREIGN_MATTER,
                    CustomerComplaint::TYPE_QUALITY,
                    CustomerComplaint::TYPE_PACKAGING,
                    CustomerComplaint::TYPE_OTHER,
                ]),
            ],
            'customer_name' => 'nullable|string',
            'customer_phone' => 'nullable|string',
            'store_id' => 'nullable|exists:stores,id',
            'product_id' => 'nullable|exists:products,id',
            'product_name' => 'nullable|string',
            'batch_id' => 'nullable|exists:batches,id',
            'batch_no' => 'nullable|string',
            'purchase_date' => 'nullable|date',
            'occurrence_time' => 'nullable|date',
            'description' => 'required|string',
            'symptoms' => 'nullable|string',
            'severity' => [
                'sometimes',
                Rule::in([
                    CustomerComplaint::SEVERITY_MILD,
                    CustomerComplaint::SEVERITY_GENERAL,
                    CustomerComplaint::SEVERITY_SERIOUS,
                    CustomerComplaint::SEVERITY_CRITICAL,
                ]),
            ],
        ]);

        $complaint = CustomerComplaint::create([
            'complaint_no' => CustomerComplaint::generateComplaintNo(),
            'complaint_type' => $request->complaint_type,
            'customer_name' => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'store_id' => $request->store_id,
            'product_id' => $request->product_id,
            'product_name' => $request->product_name,
            'batch_id' => $request->batch_id,
            'batch_no' => $request->batch_no,
            'purchase_date' => $request->purchase_date,
            'occurrence_time' => $request->occurrence_time,
            'description' => $request->description,
            'symptoms' => $request->symptoms,
            'severity' => $request->severity ?: CustomerComplaint::SEVERITY_GENERAL,
            'status' => CustomerComplaint::STATUS_PENDING,
            'reported_by' => $request->user()->id,
        ]);

        $complaint->load([
            'store:id,code,name',
            'product:id,code,name',
            'batch:id,batch_no',
        ]);

        return response()->json([
            'message' => '顾客投诉已登记',
            'data' => $complaint,
        ], 201);
    }

    public function show(CustomerComplaint $customerComplaint)
    {
        $customerComplaint->load([
            'store:id,code,name,contact_person,contact_phone',
            'product:id,code,name,allergens',
            'batch:id,batch_no,batch_type,product_name,quantity,is_locked',
            'reportedBy:id,name,email',
            'handledBy:id,name,email',
            'fileAttachments',
            'recallTasks:id,recall_no,title,status',
        ]);

        return response()->json($customerComplaint);
    }

    public function update(Request $request, CustomerComplaint $customerComplaint)
    {
        if ($customerComplaint->isResolved() || $customerComplaint->isClosed()) {
            return response()->json(['message' => '已解决或已关闭的投诉不能修改'], 400);
        }

        $request->validate([
            'handling_process' => 'sometimes|string',
            'status' => [
                'sometimes',
                Rule::in([
                    CustomerComplaint::STATUS_PENDING,
                    CustomerComplaint::STATUS_PROCESSING,
                ]),
            ],
        ]);

        $customerComplaint->update($request->only([
            'handling_process',
            'status',
        ]));

        return response()->json([
            'message' => '顾客投诉已更新',
            'data' => $customerComplaint,
        ]);
    }

    public function resolve(Request $request, CustomerComplaint $customerComplaint)
    {
        if ($customerComplaint->isResolved() || $customerComplaint->isClosed()) {
            return response()->json(['message' => '已解决或已关闭的投诉不能重复处理'], 400);
        }

        $request->validate([
            'resolution' => 'required|string',
        ]);

        $customerComplaint->resolve($request->user()->id, $request->resolution);

        return response()->json([
            'message' => '顾客投诉已解决',
            'data' => $customerComplaint->fresh(),
        ]);
    }

    public function complaintTypes()
    {
        return response()->json([
            [
                'value' => CustomerComplaint::TYPE_ALLERGY,
                'label' => '过敏反应',
            ],
            [
                'value' => CustomerComplaint::TYPE_FOOD_POISONING,
                'label' => '食物中毒',
            ],
            [
                'value' => CustomerComplaint::TYPE_FOREIGN_MATTER,
                'label' => '异物',
            ],
            [
                'value' => CustomerComplaint::TYPE_QUALITY,
                'label' => '品质问题',
            ],
            [
                'value' => CustomerComplaint::TYPE_PACKAGING,
                'label' => '包装问题',
            ],
            [
                'value' => CustomerComplaint::TYPE_OTHER,
                'label' => '其他',
            ],
        ]);
    }
}
