<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Batch;
use App\Models\DetectionAbnormal;
use App\Models\RecallTask;
use App\Models\StoreFeedback;
use App\Models\Store;
use App\Models\FileAttachment;
use App\Models\FileDownloadLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class CoreBusinessFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $hqAdmin;
    protected User $qcSupervisor;
    protected User $warehouseStaff;
    protected User $storeManager;
    protected Store $testStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStore = Store::create([
            'code' => 'TEST-ST01',
            'name' => '测试门店A',
            'address' => '测试地址',
            'contact_person' => '测试店长',
            'contact_phone' => '13800000000',
        ]);

        $this->hqAdmin = User::create(['name' => '总部管理员', 'email' => 'admin@test.com', 'password' => bcrypt('password'), 'role' => 'hq_admin', 'store_id' => null]);
        $this->qcSupervisor = User::create(['name' => '品控主管', 'email' => 'qc@test.com', 'password' => bcrypt('password'), 'role' => 'qc_supervisor', 'store_id' => null]);
        $this->warehouseStaff = User::create(['name' => '仓配人员', 'email' => 'warehouse@test.com', 'password' => bcrypt('password'), 'role' => 'warehouse_staff', 'store_id' => null]);
        $this->storeManager = User::create(['name' => '门店店长', 'email' => 'store@test.com', 'password' => bcrypt('password'), 'role' => 'store_manager', 'store_id' => $this->testStore->id]);
    }

    /**
     * 测试场景1：检测异常确认（品控主管登记→确认）
     */
    public function test_1_detection_abnormal_registration_and_confirmation(): void
    {
        $this->info("\n========== 场景1：检测异常登记与确认 ==========");

        // 品控主管登记过敏原异常（未确认状态）
        $response = $this->actingAs($this->qcSupervisor, 'sanctum')
            ->postJson('/api/detection-abnormals', [
                'abnormal_type' => 'allergen',
                'batch_no' => 'R-PEANUT-20260601-A',
                'product_name' => '山东优质花生仁',
                'detection_item' => '黄曲霉毒素B1',
                'detection_value' => '25 μg/kg',
                'standard_value' => '≤20 μg/kg',
                'description' => '花生原料抽检黄曲霉毒素超出限值',
                'detection_date' => '2026-06-20',
            ]);

        $response->assertStatus(201);
        $abnormalId = $response->json('data.id');
        $this->assertNotEmpty($abnormalId, '异常登记成功，返回ID');
        $this->assertEquals('pending', $response->json('data.status'), '初始状态为待确认');
        $this->info('✅ [1.1] 检测异常登记成功，编号：' . $response->json('data.abnormal_no') . '，状态：pending');

        // 测试：检测异常未确认时，不能发起创建召回任务（RecallTask::canPublishRecall检查）
        $createBeforeConfirm = $this->actingAs($this->hqAdmin, 'sanctum')
            ->postJson('/api/recall-tasks', [
                'title' => '测试召回-未确认异常',
                'description' => '不应创建成功',
                'recall_level' => 'level1',
                'recall_reason_type' => 'allergen',
                'detection_abnormal_id' => $abnormalId,
                'batch_ids' => [1],
            ]);

        $this->assertEquals(400, $createBeforeConfirm->status(), '关联未确认检测异常时创建召回任务返回400');
        $this->assertStringContainsString('检测异常未确认', $createBeforeConfirm->json('message'), '提示检测异常未确认');
        $this->info('✅ [1.2] 关联未确认的检测异常创建召回任务：成功拦截（400）');

        // 品控主管确认异常
        $confirmResponse = $this->actingAs($this->qcSupervisor, 'sanctum')
            ->postJson("/api/detection-abnormals/{$abnormalId}/confirm", [
                'remark' => '经复核，检测结果真实有效，同意发起召回',
            ]);

        $confirmResponse->assertStatus(200);
        $this->assertEquals('confirmed', $confirmResponse->json('data.status'), '确认后状态为confirmed');
        $this->assertNotEmpty($confirmResponse->json('data.confirmed_at'), '确认时间已记录');
        $this->info('✅ [1.3] 检测异常确认成功，状态：confirmed');

        // 测试DetectionAbnormal::canPublishRecall()方法
        $abnormal = DetectionAbnormal::find($abnormalId);
        $this->assertTrue($abnormal->canPublishRecall(), 'canPublishRecall返回true');
        $this->info('✅ [1.4] canPublishRecall()方法返回true，校验逻辑正确');

        $this->info("========== 场景1：PASS ==========\n");
    }

    /**
     * 测试场景2：召回任务创建+发布（含检测未确认不能发布公告校验）
     */
    public function test_2_recall_task_create_and_publish_validation(): void
    {
        $this->info("\n========== 场景2：召回任务创建与发布 ==========");

        // 先创建已确认的检测异常 + 批次 + 谱系
        $rawPeanut = Batch::create([
            'batch_no' => 'TEST-RAW-A',
            'batch_type' => Batch::BATCH_TYPE_RAW_MATERIAL,
            'product_name' => '测试花生仁',
            'quantity' => 100,
            'unit' => 'kg',
            'production_date' => '2026-06-01',
            'status' => 1,
        ]);

        $semiBatch = Batch::create([
            'batch_no' => 'TEST-SEMI-A',
            'batch_type' => Batch::BATCH_TYPE_SEMI_FINISHED,
            'product_name' => '测试面团',
            'quantity' => 80,
            'unit' => 'kg',
            'production_date' => '2026-06-10',
            'status' => 1,
        ]);

        $finishedBatch = Batch::create([
            'batch_no' => 'TEST-FIN-A',
            'batch_type' => Batch::BATCH_TYPE_FINISHED,
            'product_name' => '测试饼干',
            'quantity' => 2000,
            'unit' => '盒',
            'production_date' => '2026-06-12',
            'status' => 1,
        ]);

        \App\Models\BatchLineage::create(['parent_batch_id' => $rawPeanut->id, 'child_batch_id' => $semiBatch->id, 'usage_quantity' => 80]);
        \App\Models\BatchLineage::create(['parent_batch_id' => $semiBatch->id, 'child_batch_id' => $finishedBatch->id, 'usage_quantity' => 80]);

        // 配送门店
        \App\Models\BatchDelivery::create(['batch_id' => $finishedBatch->id, 'store_id' => $this->testStore->id, 'delivery_quantity' => 500, 'delivery_unit' => '盒', 'delivery_date' => '2026-06-15', 'status' => 'delivered']);

        // 创建检测异常并确认
        $abnormal = DetectionAbnormal::create([
            'abnormal_no' => 'ABN-TEST-001',
            'abnormal_type' => 'microbe',
            'batch_id' => $rawPeanut->id,
            'batch_no' => $rawPeanut->batch_no,
            'product_name' => $rawPeanut->product_name,
            'detection_item' => '沙门氏菌',
            'description' => '沙门氏菌阳性',
            'status' => DetectionAbnormal::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'confirmed_by' => $this->qcSupervisor->id,
            'reported_by' => $this->qcSupervisor->id,
        ]);

        // 创建召回任务（选原料批次，系统应自动级联锁定所有关联半成品/成品）
        $createResponse = $this->actingAs($this->hqAdmin, 'sanctum')
            ->postJson('/api/recall-tasks', [
                'title' => '沙门氏菌污染花生原料召回',
                'description' => '因花生原料检出沙门氏菌阳性，启动三级召回',
                'recall_level' => 'level3',
                'recall_reason_type' => 'microbe',
                'detection_abnormal_id' => $abnormal->id,
                'batch_ids' => [$rawPeanut->id],
                'expected_completion_date' => '2026-06-30 18:00:00',
                'announcement_content' => '各门店请立即下架相关批次产品',
            ]);

        $createResponse->assertStatus(201);
        $recallTaskId = $createResponse->json('data.id');
        $this->info('✅ [2.1] 召回任务创建成功，编号：' . $createResponse->json('data.recall_no'));

        // 验证：同原料批次半成品级联锁定（核心规则2）
        $rawPeanut->refresh();
        $semiBatch->refresh();
        $finishedBatch->refresh();

        $this->assertEquals(1, $rawPeanut->is_locked, '原料批次已锁定');
        $this->assertEquals(1, $semiBatch->is_locked, '半成品批次（关联原料）已级联锁定');
        $this->assertEquals(1, $finishedBatch->is_locked, '成品批次（关联半成品）已级联锁定');
        $this->info('✅ [2.2] 级联锁定验证：原料→半成品→成品 三个批次全部锁定（同原料级联锁定规则生效）');

        // 验证：自动创建门店下架反馈单
        $feedbacks = StoreFeedback::where('recall_task_id', $recallTaskId)->get();
        $this->assertGreaterThan(0, $feedbacks->count(), '已创建门店反馈单');
        $this->info('✅ [2.3] 自动为配送门店创建下架反馈单，共 ' . $feedbacks->count() . ' 家门店');

        // 测试：发布召回公告（检测已确认，可以发布）
        $publishResponse = $this->actingAs($this->hqAdmin, 'sanctum')
            ->postJson("/api/recall-tasks/{$recallTaskId}/publish");

        $publishResponse->assertStatus(200);
        $this->assertEquals('published', $publishResponse->json('data.status'), '发布后状态为published');
        $this->assertNotEmpty($publishResponse->json('data.published_at'), '发布时间已记录');
        $this->info('✅ [2.4] 召回公告发布成功（检测已确认，校验通过）');

        // --- 验证：检测未确认不能发召回公告 ---
        // 创建一个pending状态的异常，关联到新召回任务测试发布拦截
        $pendingAbnormal = DetectionAbnormal::create([
            'abnormal_no' => 'ABN-TEST-PENDING',
            'abnormal_type' => 'allergen',
            'batch_no' => 'TEST-PENDING',
            'product_name' => '测试未确认',
            'detection_item' => '花生过敏原',
            'description' => '待确认的异常',
            'status' => DetectionAbnormal::STATUS_PENDING,
            'reported_by' => $this->qcSupervisor->id,
        ]);

        $pendingRaw = Batch::create(['batch_no' => 'TEST-RAW-B', 'batch_type' => 'raw_material', 'product_name' => '测试未锁定', 'quantity' => 50, 'unit' => 'kg', 'status' => 1]);

        $pendingRecall = RecallTask::create([
            'recall_no' => 'RCL-TEST-PENDING',
            'title' => '待确认异常测试召回',
            'description' => '应无法发布',
            'recall_level' => 'level3',
            'recall_reason_type' => 'allergen',
            'detection_abnormal_id' => $pendingAbnormal->id,
            'status' => RecallTask::STATUS_DRAFT,
            'created_by' => $this->hqAdmin->id,
        ]);
        $pendingRecall->batches()->attach($pendingRaw->id, ['batch_no' => $pendingRaw->batch_no, 'batch_type' => $pendingRaw->batch_type, 'product_name' => $pendingRaw->product_name, 'total_quantity' => $pendingRaw->quantity]);

        $failPublish = $this->actingAs($this->hqAdmin, 'sanctum')
            ->postJson("/api/recall-tasks/{$pendingRecall->id}/publish");

        $this->assertEquals(400, $failPublish->status(), '未确认异常发布返回400');
        $this->assertStringContainsString('检测未确认', $failPublish->json('message'), '错误消息包含：检测未确认');
        $this->info('✅ [2.5] 核心规则校验：关联未确认异常的召回任务【发布失败】（符合预期），错误：' . $failPublish->json('message'));

        $this->info("========== 场景2：PASS ==========\n");
    }

    /**
     * 测试场景3：同原料批次半成品一起锁定（通过BatchController::lock手动锁定）
     */
    public function test_3_raw_material_locks_related_batches(): void
    {
        $this->info("\n========== 场景3：同原料半成品级联锁定（手动锁定）==========");

        // 建立原料→半成品1→成品1
        //               ↘半成品2→成品2   的谱系
        $raw = Batch::create(['batch_no' => 'RAW-LOCK-001', 'batch_type' => Batch::BATCH_TYPE_RAW_MATERIAL, 'product_name' => '级联锁定测试原料', 'quantity' => 1000, 'unit' => 'kg', 'status' => 1]);
        $semi1 = Batch::create(['batch_no' => 'SEMI-LOCK-001', 'batch_type' => Batch::BATCH_TYPE_SEMI_FINISHED, 'product_name' => '半成品A', 'quantity' => 500, 'unit' => 'kg', 'status' => 1]);
        $semi2 = Batch::create(['batch_no' => 'SEMI-LOCK-002', 'batch_type' => Batch::BATCH_TYPE_SEMI_FINISHED, 'product_name' => '半成品B', 'quantity' => 400, 'unit' => 'kg', 'status' => 1]);
        $fin1 = Batch::create(['batch_no' => 'FIN-LOCK-001', 'batch_type' => Batch::BATCH_TYPE_FINISHED, 'product_name' => '成品1', 'quantity' => 1000, 'unit' => '盒', 'status' => 1]);
        $fin2 = Batch::create(['batch_no' => 'FIN-LOCK-002', 'batch_type' => Batch::BATCH_TYPE_FINISHED, 'product_name' => '成品2', 'quantity' => 800, 'unit' => '盒', 'status' => 1]);

        \App\Models\BatchLineage::create(['parent_batch_id' => $raw->id, 'child_batch_id' => $semi1->id, 'usage_quantity' => 400]);
        \App\Models\BatchLineage::create(['parent_batch_id' => $raw->id, 'child_batch_id' => $semi2->id, 'usage_quantity' => 350]);
        \App\Models\BatchLineage::create(['parent_batch_id' => $semi1->id, 'child_batch_id' => $fin1->id, 'usage_quantity' => 400]);
        \App\Models\BatchLineage::create(['parent_batch_id' => $semi2->id, 'child_batch_id' => $fin2->id, 'usage_quantity' => 350]);

        // 仓配人员手动锁定原料批次，include_related=true
        $lockResponse = $this->actingAs($this->warehouseStaff, 'sanctum')
            ->postJson("/api/batches/{$raw->id}/lock", [
                'reason' => '发现原料疑似被污染',
                'include_related' => true,
            ]);

        $lockResponse->assertStatus(200);
        $lockedCount = $lockResponse->json('locked_count');
        $lockedBatches = $lockResponse->json('locked_batches');

        $this->assertEquals(5, $lockedCount, '锁定数量应为5（原料+2半成品+2成品）');
        $this->assertCount(5, $lockedBatches, '返回的锁定批次列表数量正确');
        $this->info('✅ [3.1] 锁定接口返回：锁定了 ' . $lockedCount . ' 个批次，列表：' . implode(', ', $lockedBatches));

        // 逐一核对每个批次状态
        $raw->refresh();
        $semi1->refresh();
        $semi2->refresh();
        $fin1->refresh();
        $fin2->refresh();

        $assertions = [
            ['原料', $raw], ['半成品A', $semi1], ['半成品B', $semi2], ['成品1', $fin1], ['成品2', $fin2],
        ];
        foreach ($assertions as [$name, $batch]) {
            $this->assertEquals(1, $batch->is_locked, "{$name} {$batch->batch_no} 已锁定");
            $this->assertStringContainsString('关联批次锁定', $batch->lock_reason, "{$name} 锁定原因包含'关联批次锁定'");
        }
        $this->info('✅ [3.2] 全链路锁定验证通过：原料→半成品A→成品1，原料→半成品B→成品2，两条分支全部锁定');
        $this->info('✅ [3.3] Batch::getRelatedBatches() + BatchController级联锁定：核心规则2生效');

        // 测试：不勾选include_related，只锁定单个原料
        $rawSingle = Batch::create(['batch_no' => 'RAW-LOCK-SINGLE', 'batch_type' => Batch::BATCH_TYPE_RAW_MATERIAL, 'product_name' => '单独锁定原料', 'quantity' => 10, 'unit' => 'kg', 'status' => 1]);
        \App\Models\BatchLineage::create(['parent_batch_id' => $rawSingle->id, 'child_batch_id' => $fin1->id, 'usage_quantity' => 5]); // 复用已有fin1（已锁定，但验证只锁1个）

        $singleLock = $this->actingAs($this->warehouseStaff, 'sanctum')
            ->postJson("/api/batches/{$rawSingle->id}/lock", [
                'reason' => '单独锁定，不级联',
                'include_related' => false,
            ]);
        $singleLock->assertStatus(200);
        $this->assertEquals(1, $singleLock->json('locked_count'), '未勾选时只锁定1个');
        $this->info('✅ [3.4] include_related=false 时只锁定1个批次，分支逻辑正常');

        $this->info("========== 场景3：PASS ==========\n");
    }

    /**
     * 测试场景4：门店漏报下架数量总部看板标红
     */
    public function test_4_store_missing_report_flagged_red(): void
    {
        $this->info("\n========== 场景4：门店漏报下架数量标红 ==========");

        // 创建一个已发布的召回任务，涉及3家门店
        $finBatch = Batch::create(['batch_no' => 'TEST-MISSING-FIN', 'batch_type' => Batch::BATCH_TYPE_FINISHED, 'product_name' => '漏报测试成品', 'quantity' => 1000, 'unit' => '盒', 'status' => 1]);

        $storeA = Store::create(['code' => 'MIS-A', 'name' => '漏报测试门店A', 'contact_person' => 'A店长', 'contact_phone' => '13800000011']);
        $storeB = Store::create(['code' => 'MIS-B', 'name' => '漏报测试门店B', 'contact_person' => 'B店长', 'contact_phone' => '13800000012']);
        $storeC = Store::create(['code' => 'MIS-C', 'name' => '漏报测试门店C', 'contact_person' => 'C店长', 'contact_phone' => '13800000013']);

        \App\Models\BatchDelivery::create(['batch_id' => $finBatch->id, 'store_id' => $storeA->id, 'delivery_quantity' => 200, 'delivery_date' => '2026-06-18', 'status' => 'delivered']);
        \App\Models\BatchDelivery::create(['batch_id' => $finBatch->id, 'store_id' => $storeB->id, 'delivery_quantity' => 150, 'delivery_date' => '2026-06-18', 'status' => 'delivered']);
        \App\Models\BatchDelivery::create(['batch_id' => $finBatch->id, 'store_id' => $storeC->id, 'delivery_quantity' => 180, 'delivery_date' => '2026-06-18', 'status' => 'delivered']);

        $recall = RecallTask::create([
            'recall_no' => 'RCL-TEST-MISSING',
            'title' => '漏报测试召回任务',
            'description' => '测试门店漏报场景',
            'recall_level' => 'level3',
            'recall_reason_type' => 'quality',
            'status' => RecallTask::STATUS_PUBLISHED,
            'published_at' => now(),
            'published_by' => $this->hqAdmin->id,
            'created_by' => $this->hqAdmin->id,
        ]);
        $recall->batches()->attach($finBatch->id, [
            'batch_no' => $finBatch->batch_no,
            'batch_type' => $finBatch->batch_type,
            'product_name' => $finBatch->product_name,
            'total_quantity' => $finBatch->quantity,
            'delivered_quantity' => 530,
        ]);

        // 为3家门店创建反馈单（总部自动创建）
        $fbA = StoreFeedback::create(['recall_task_id' => $recall->id, 'store_id' => $storeA->id, 'store_name' => $storeA->name, 'store_code' => $storeA->code, 'received_quantity' => 200, 'status' => StoreFeedback::STATUS_SUBMITTED, 'off_shelf_quantity' => 180, 'returned_quantity' => 180, 'submitted_at' => now(), 'submitted_by' => $this->storeManager->id, 'is_missing' => false]);
        // 门店B：pending待反馈
        $fbB = StoreFeedback::create(['recall_task_id' => $recall->id, 'store_id' => $storeB->id, 'store_name' => $storeB->name, 'store_code' => $storeB->code, 'received_quantity' => 150, 'status' => StoreFeedback::STATUS_PENDING, 'is_missing' => false]);
        // 门店C：pending待反馈
        $fbC = StoreFeedback::create(['recall_task_id' => $recall->id, 'store_id' => $storeC->id, 'store_name' => $storeC->name, 'store_code' => $storeC->code, 'received_quantity' => 180, 'status' => StoreFeedback::STATUS_PENDING, 'is_missing' => false]);

        $this->info('✅ [4.1] 初始状态：门店A已反馈(200盒)，门店B/C待反馈');

        // 调用总部检查漏报门店接口（StoreFeedbackController::unreportedStores）
        $unreportedResponse = $this->actingAs($this->hqAdmin, 'sanctum')
            ->getJson("/api/unreported-stores/{$recall->id}");

        $unreportedResponse->assertStatus(200);
        $this->assertEquals(2, $unreportedResponse->json('unreported_count'), '未上报门店数量为2家');
        $this->info('✅ [4.2] 调用 unreported-stores 接口，检查到漏报门店：' . $unreportedResponse->json('unreported_count') . ' 家');

        // 验证门店B、C被标记为is_missing（标红）
        $fbB->refresh();
        $fbC->refresh();
        $this->assertTrue($fbB->is_missing, '门店B已标记为漏报（is_missing=true）');
        $this->assertTrue($fbC->is_missing, '门店C已标记为漏报（is_missing=true）');
        $this->assertEquals(StoreFeedback::STATUS_OVERDUE, $fbB->status, '门店B状态变更为overdue');
        $this->info('✅ [4.3] StoreFeedback::markAsMissing() 已设置 is_missing=true 和 status=overdue');

        // 验证看板列表：StoreFeedbackController::index 漏报门店排序在最前，前端表格通过 is_missing 渲染红色样式
        $listResponse = $this->actingAs($this->hqAdmin, 'sanctum')
            ->getJson('/api/store-feedbacks?recall_task_id=' . $recall->id);
        $listResponse->assertStatus(200);

        $feedbacks = $listResponse->json('data');
        $this->assertNotEmpty($feedbacks, '看板列表返回数据');

        // 第一个应该是漏报门店（MIS-B 或 MIS-C），is_missing=true
        $firstRow = is_array($feedbacks[0] ?? null) ? $feedbacks[0] : null;
        if ($firstRow) {
            $this->assertTrue((bool)$firstRow['is_missing'], '看板第1行是漏报门店（排序正确：漏报优先）');
            $this->info("✅ [4.4] 看板列表排序正确：第1行是 {$firstRow['store_code']}（is_missing=true），前端将渲染 .missing-row 红底红字样式");
        }

        // 门店B补报：提交后自动取消漏报标记（核心：更新后is_missing=false）
        $storeBManager = User::create(['name' => 'B店店长', 'email' => 'storeb@test.com', 'password' => bcrypt('password'), 'role' => 'store_manager', 'store_id' => $storeB->id]);
        $submitB = $this->actingAs($storeBManager, 'sanctum')
            ->postJson('/api/store-feedbacks', [
                'recall_task_id' => $recall->id,
                'store_id' => $storeB->id,
                'items' => [[
                    'batch_id' => $finBatch->id,
                    'off_shelf_quantity' => 150,
                    'returned_quantity' => 145,
                    'destroyed_quantity' => 0,
                    'sold_quantity' => 5,
                ]],
                'remark' => '补报：已完成下架',
            ]);

        $submitB->assertStatus(200);
        $fbB->refresh();
        $this->assertFalse($fbB->is_missing, '门店B补报后 is_missing 已自动复位为false（标红取消）');
        $this->assertEquals(150, $fbB->off_shelf_quantity, '门店B下架数量已更新');
        $this->info('✅ [4.5] 门店B补报反馈：is_missing=false（红底红字样式自动取消），下架数量=150');

        // 再次检查漏报门店：剩余1家（门店C）
        $recheck = $this->actingAs($this->hqAdmin, 'sanctum')->getJson("/api/unreported-stores/{$recall->id}");
        $this->assertEquals(1, $recheck->json('unreported_count'), '补报后剩余1家漏报门店');
        $this->info("✅ [4.6] 重新检查：剩余 {$recheck->json('unreported_count')} 家漏报门店（符合预期：仅门店C漏报）");

        $this->info("========== 场景4：PASS ==========\n");
    }

    /**
     * 测试场景5：附件下载记录（上传→SHA256校验→下载→写入日志）
     */
    public function test_5_file_attachment_integrity_and_download_log(): void
    {
        $this->info("\n========== 场景5：附件下载SHA256校验与日志记录 ==========");

        Storage::fake('local');

        // 创建测试文件内容（品控检测报告）
        $testContent = "=== FOOD SAFETY TEST REPORT ===\nSample: Peanut 20260601\nResult: Aflatoxin B1 positive (25ug/kg)\nConfirmed by: Lab A11y";
        $uploadedFile = UploadedFile::fake()->createWithContent('detection-report-花生黄曲霉-20260620.pdf', $testContent);
        $expectedHash = hash('sha256', $testContent);

        // 上传文件
        $uploadResponse = $this->actingAs($this->qcSupervisor, 'sanctum')
            ->postJson('/api/files/upload', [
                'file' => $uploadedFile,
                'related_type' => 'detection_abnormal',
                'related_id' => 1,
            ]);

        $uploadResponse->assertStatus(201);
        $fileId = $uploadResponse->json('data.id');
        $storedHash = $uploadResponse->json('data.file_hash');

        $this->assertNotEmpty($fileId, '文件上传成功，返回ID');
        $this->assertEquals($expectedHash, $storedHash, '上传时SHA256哈希正确：' . substr($storedHash, 0, 16) . '...');
        $this->info('✅ [5.1] 文件上传成功，SHA256=' . substr($storedHash, 0, 16) . '...（与本地计算一致）');

        // 验证 FileAttachment::verifyIntegrity() 方法
        $file = FileAttachment::find($fileId);
        $this->assertTrue($file->verifyIntegrity(), 'verifyIntegrity() 返回true（文件未被篡改）');
        $this->info('✅ [5.2] FileAttachment::verifyIntegrity() 校验通过');

        // 篡改文件（模拟被篡改的情况，验证校验失败）
        $tamperedContent = $testContent . "\n<< HACKED CONTENT >>";
        Storage::disk('local')->put($file->file_path, $tamperedContent);
        $this->assertFalse($file->verifyIntegrity(), '篡改后 verifyIntegrity() 返回false');
        $this->info('✅ [5.3] 防篡改验证：文件内容被篡改后 verifyIntegrity() 正确返回false');

        // 恢复文件（用于下载测试）
        Storage::disk('local')->put($file->file_path, $testContent);

        // 模拟总部管理员下载文件
        $downloadResponse = $this->actingAs($this->hqAdmin, 'sanctum')
            ->get("/api/files/{$fileId}/download");

        $downloadResponse->assertStatus(200);
        $this->assertEquals('true', $downloadResponse->headers->get('X-Integrity-Verified'), '响应头 X-Integrity-Verified = true');
        $this->assertEquals($storedHash, $downloadResponse->headers->get('X-File-Hash'), '响应头包含正确的X-File-Hash供客户端校验');
        $this->info('✅ [5.4] 文件下载成功，响应头：X-Integrity-Verified=true，X-File-Hash已下发');

        // 验证 file_download_logs 表已写入记录
        $logs = FileDownloadLog::where('file_attachment_id', $fileId)->get();
        $this->assertCount(1, $logs, '下载日志已写入1条记录');
        $log = $logs->first();
        $this->assertEquals($this->hqAdmin->id, $log->user_id, '下载人是总部管理员');
        $this->assertTrue($log->integrity_verified, 'integrity_verified=true（下载时完整性校验通过）');
        $this->assertNotEmpty($log->ip_address, '已记录下载IP地址');
        $this->info("✅ [5.5] 下载日志记录完整：用户ID={$log->user_id}，校验通过=yes，IP={$log->ip_address}，UA已记录");

        // 验证下载次数已累加
        $file->refresh();
        $this->assertEquals(1, $file->download_count, 'download_count=1，计数器已+1');
        $this->info('✅ [5.6] download_count 字段自动累加 = 1');

        $this->info("========== 场景5：PASS ==========\n");
    }

    protected function info(string $message): void
    {
        fwrite(STDERR, $message . "\n");
    }
}
