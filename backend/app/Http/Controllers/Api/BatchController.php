<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\BatchLineage;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function index(Request $request)
    {
        $query = Batch::with(['product:id,code,name']);

        if ($request->filled('batch_type')) {
            $query->where('batch_type', $request->batch_type);
        }

        if ($request->filled('batch_no')) {
            $query->where('batch_no', 'like', '%' . $request->batch_no . '%');
        }

        if ($request->filled('product_name')) {
            $query->where('product_name', 'like', '%' . $request->product_name . '%');
        }

        if ($request->filled('is_locked')) {
            $query->where('is_locked', $request->is_locked);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $batches = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json($batches);
    }

    public function show(Batch $batch)
    {
        $batch->load([
            'product:id,code,name,allergens',
            'parentBatches:id,batch_no,batch_type,product_name,product_code,is_locked',
            'childBatches:id,batch_no,batch_type,product_name,product_code,is_locked',
            'deliveries.store:id,code,name',
            'lockedBy:id,name',
        ]);

        return response()->json($batch);
    }

    public function lineage(Batch $batch)
    {
        $batch->load([
            'parentBatches.parentBatches:id,batch_no,batch_type,product_name,is_locked',
            'childBatches.childBatches:id,batch_no,batch_type,product_name,is_locked',
        ]);

        $ancestors = $this->collectAncestors($batch);
        $descendants = $this->collectDescendants($batch);

        $allBatches = collect([$batch])
            ->merge($ancestors)
            ->merge($descendants)
            ->unique('id')
            ->values();

        $lineages = BatchLineage::whereIn('parent_batch_id', $allBatches->pluck('id'))
            ->orWhereIn('child_batch_id', $allBatches->pluck('id'))
            ->get();

        $nodes = $allBatches->map(function ($b) {
            return [
                'id' => $b->id,
                'batch_no' => $b->batch_no,
                'batch_type' => $b->batch_type,
                'product_name' => $b->product_name,
                'is_locked' => (bool) $b->is_locked,
                'level' => $this->calculateLevel($b, $batch),
            ];
        });

        $edges = $lineages->map(function ($lineage) {
            return [
                'source' => $lineage->parent_batch_id,
                'target' => $lineage->child_batch_id,
                'usage_quantity' => $lineage->usage_quantity,
                'usage_unit' => $lineage->usage_unit,
            ];
        });

        return response()->json([
            'root_batch' => [
                'id' => $batch->id,
                'batch_no' => $batch->batch_no,
                'batch_type' => $batch->batch_type,
                'product_name' => $batch->product_name,
                'quantity' => $batch->quantity,
                'unit' => $batch->unit,
                'is_locked' => (bool) $batch->is_locked,
            ],
            'nodes' => $nodes,
            'edges' => $edges,
        ]);
    }

    protected function collectAncestors($batch, &$collected = null)
    {
        if ($collected === null) {
            $collected = collect();
        }
        foreach ($batch->parentBatches as $parent) {
            if (! $collected->contains('id', $parent->id)) {
                $collected->push($parent);
                $this->collectAncestors($parent, $collected);
            }
        }
        return $collected;
    }

    protected function collectDescendants($batch, &$collected = null)
    {
        if ($collected === null) {
            $collected = collect();
        }
        foreach ($batch->childBatches as $child) {
            if (! $collected->contains('id', $child->id)) {
                $collected->push($child);
                $this->collectDescendants($child, $collected);
            }
        }
        return $collected;
    }

    protected function calculateLevel($target, $root): int
    {
        if ($target->id === $root->id) {
            return 0;
        }
        return $this->findLevel($root, $target, 0, []);
    }

    protected function findLevel($current, $target, $currentLevel, $visited): int
    {
        if (in_array($current->id, $visited)) {
            return PHP_INT_MAX;
        }
        $visited[] = $current->id;

        foreach ($current->parentBatches as $parent) {
            if ($parent->id === $target->id) {
                return $currentLevel - 1;
            }
            $result = $this->findLevel($parent, $target, $currentLevel - 1, $visited);
            if ($result !== PHP_INT_MAX) {
                return $result;
            }
        }

        foreach ($current->childBatches as $child) {
            if ($child->id === $target->id) {
                return $currentLevel + 1;
            }
            $result = $this->findLevel($child, $target, $currentLevel + 1, $visited);
            if ($result !== PHP_INT_MAX) {
                return $result;
            }
        }

        return PHP_INT_MAX;
    }

    public function relatedBatches(Batch $batch)
    {
        $related = $batch->getRelatedBatches();

        $relatedIds = collect($related)->pluck('id')->toArray();

        $relatedBatches = Batch::whereIn('id', $relatedIds)
            ->with(['deliveries.store:id,code,name'])
            ->get()
            ->map(function ($b) {
                return [
                    'id' => $b->id,
                    'batch_no' => $b->batch_no,
                    'batch_type' => $b->batch_type,
                    'product_name' => $b->product_name,
                    'quantity' => $b->quantity,
                    'unit' => $b->unit,
                    'is_locked' => (bool) $b->is_locked,
                    'status' => $b->status,
                    'delivered_stores' => $b->deliveries->map(function ($d) {
                        return [
                            'store_id' => $d->store_id,
                            'store_name' => $d->store ? $d->store->name : null,
                            'delivery_quantity' => $d->delivery_quantity,
                            'delivery_date' => $d->delivery_date,
                            'status' => $d->status,
                        ];
                    }),
                ];
            });

        return response()->json([
            'original_batch' => [
                'id' => $batch->id,
                'batch_no' => $batch->batch_no,
                'batch_type' => $batch->batch_type,
            ],
            'related_batches' => $relatedBatches,
            'related_count' => count($relatedBatches),
        ]);
    }

    public function lock(Request $request, Batch $batch)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'include_related' => 'boolean',
        ]);

        $userId = $request->user()->id;
        $includeRelated = $request->input('include_related', false);

        $lockedBatches = collect([$batch]);
        $batch->lock($request->reason, $userId);

        if ($includeRelated && $batch->isRawMaterial()) {
            $related = $batch->getRelatedBatches();
            foreach ($related as $relatedBatch) {
                $relatedBatch->lock($request->reason . ' (关联批次锁定)', $userId);
                $lockedBatches->push($relatedBatch);
            }
        }

        return response()->json([
            'message' => '批次已锁定',
            'locked_count' => $lockedBatches->unique('id')->count(),
            'locked_batches' => $lockedBatches->unique('id')->pluck('batch_no'),
        ]);
    }

    public function unlock(Batch $batch)
    {
        if (! $batch->is_locked) {
            return response()->json(['message' => '批次未锁定'], 400);
        }

        $batch->unlock();

        return response()->json([
            'message' => '批次已解锁',
            'batch_no' => $batch->batch_no,
        ]);
    }

    public function batchTypes()
    {
        return response()->json([
            [
                'value' => Batch::BATCH_TYPE_RAW_MATERIAL,
                'label' => '原料批次',
            ],
            [
                'value' => Batch::BATCH_TYPE_SEMI_FINISHED,
                'label' => '半成品批次',
            ],
            [
                'value' => Batch::BATCH_TYPE_FINISHED,
                'label' => '成品批次',
            ],
        ]);
    }
}
