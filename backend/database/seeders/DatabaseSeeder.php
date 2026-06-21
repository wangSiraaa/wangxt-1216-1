<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Store;
use App\Models\Product;
use App\Models\Batch;
use App\Models\BatchLineage;
use App\Models\BatchDelivery;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ========== 门店数据 ==========
        $stores = [
            ['code' => 'ST001', 'name' => '上海浦东店', 'address' => '上海市浦东新区世纪大道100号', 'contact_person' => '张伟', 'contact_phone' => '13800000001'],
            ['code' => 'ST002', 'name' => '上海徐汇店', 'address' => '上海市徐汇区淮海中路500号', 'contact_person' => '李娜', 'contact_phone' => '13800000002'],
            ['code' => 'ST003', 'name' => '北京朝阳店', 'address' => '北京市朝阳区建国路88号', 'contact_person' => '王强', 'contact_phone' => '13900000001'],
            ['code' => 'ST004', 'name' => '广州天河店', 'address' => '广州市天河区天河路200号', 'contact_person' => '黄丽', 'contact_phone' => '13900000002'],
            ['code' => 'ST005', 'name' => '深圳南山店', 'address' => '深圳市南山区科技园路50号', 'contact_person' => '陈浩', 'contact_phone' => '13900000003'],
        ];

        $createdStores = [];
        foreach ($stores as $s) {
            $createdStores[] = Store::create($s);
        }

        // ========== 产品数据 ==========
        $products = [
            ['code' => 'P001', 'name' => '花生酥饼干', 'category' => '糕点', 'allergens' => ['peanut', 'wheat'], 'spec' => '200g/盒'],
            ['code' => 'P002', 'name' => '肉松小贝', 'category' => '糕点', 'allergens' => ['egg', 'wheat', 'soy'], 'spec' => '6个/盒'],
            ['code' => 'P003', 'name' => '全麦吐司', 'category' => '面包', 'allergens' => ['wheat'], 'spec' => '500g/条'],
            ['code' => 'P004', 'name' => '奶油泡芙', 'category' => '蛋糕', 'allergens' => ['milk', 'egg', 'wheat'], 'spec' => '12个/盒'],
        ];

        $createdProducts = [];
        foreach ($products as $p) {
            $createdProducts[] = Product::create($p);
        }

        // ========== 用户数据 ==========
        $users = [
            [
                'name' => '总部管理员',
                'email' => 'admin@foodrecall.com',
                'password' => Hash::make('password123'),
                'role' => 'hq_admin',
                'store_id' => null,
            ],
            [
                'name' => '品控主管-刘芳',
                'email' => 'qc@foodrecall.com',
                'password' => Hash::make('password123'),
                'role' => 'qc_supervisor',
                'store_id' => null,
            ],
            [
                'name' => '仓配主管-赵军',
                'email' => 'warehouse@foodrecall.com',
                'password' => Hash::make('password123'),
                'role' => 'warehouse_staff',
                'store_id' => null,
            ],
            [
                'name' => '浦东店店长-孙敏',
                'email' => 'store1@foodrecall.com',
                'password' => Hash::make('password123'),
                'role' => 'store_manager',
                'store_id' => $createdStores[0]->id,
            ],
            [
                'name' => '徐汇店店长-周磊',
                'email' => 'store2@foodrecall.com',
                'password' => Hash::make('password123'),
                'role' => 'store_manager',
                'store_id' => $createdStores[1]->id,
            ],
            [
                'name' => '朝阳店店长-吴萍',
                'email' => 'store3@foodrecall.com',
                'password' => Hash::make('password123'),
                'role' => 'store_manager',
                'store_id' => $createdStores[2]->id,
            ],
        ];

        $createdUsers = [];
        foreach ($users as $u) {
            $createdUsers[] = User::create($u);
        }

        // ========== 批次谱系数据（核心：原料→半成品→成品）==========
        // 原料批次
        $rawPeanut = Batch::create([
            'batch_no' => 'R-PEANUT-20260601-A',
            'batch_type' => Batch::BATCH_TYPE_RAW_MATERIAL,
            'product_name' => '山东优质花生仁',
            'product_code' => 'R-PEANUT',
            'quantity' => 500,
            'unit' => 'kg',
            'production_date' => '2026-06-01',
            'expiry_date' => '2026-12-01',
            'supplier_name' => '山东花生加工厂',
            'supplier_batch_no' => 'SUP-PEANUT-20260530',
            'status' => 1,
        ]);

        $rawFlour = Batch::create([
            'batch_no' => 'R-WHEAT-20260605-A',
            'batch_type' => Batch::BATCH_TYPE_RAW_MATERIAL,
            'product_name' => '高筋小麦粉',
            'product_code' => 'R-WHEAT',
            'quantity' => 1000,
            'unit' => 'kg',
            'production_date' => '2026-06-05',
            'expiry_date' => '2026-12-05',
            'supplier_name' => '河南面粉厂',
            'supplier_batch_no' => 'SUP-WHEAT-20260601',
            'status' => 1,
        ]);

        $rawEgg = Batch::create([
            'batch_no' => 'R-EGG-20260610-A',
            'batch_type' => Batch::BATCH_TYPE_RAW_MATERIAL,
            'product_name' => '新鲜鸡蛋',
            'product_code' => 'R-EGG',
            'quantity' => 200,
            'unit' => 'kg',
            'production_date' => '2026-06-10',
            'expiry_date' => '2026-06-30',
            'supplier_name' => '上海禽蛋合作社',
            'supplier_batch_no' => 'SUP-EGG-20260608',
            'status' => 1,
        ]);

        // 半成品批次 (使用花生+面粉)
        $semiPeanutDough = Batch::create([
            'batch_no' => 'S-PEANUT-DOUGH-20260612',
            'batch_type' => Batch::BATCH_TYPE_SEMI_FINISHED,
            'product_id' => $createdProducts[0]->id,
            'product_name' => '花生酥面团',
            'product_code' => 'S-PEANUT-DOUGH',
            'quantity' => 300,
            'unit' => 'kg',
            'production_date' => '2026-06-12',
            'expiry_date' => '2026-06-19',
            'status' => 1,
        ]);

        // 半成品批次 (使用鸡蛋+面粉)
        $semiCakeBase = Batch::create([
            'batch_no' => 'S-CAKE-BASE-20260615',
            'batch_type' => Batch::BATCH_TYPE_SEMI_FINISHED,
            'product_id' => $createdProducts[3]->id,
            'product_name' => '泡芙蛋糕坯',
            'product_code' => 'S-CAKE-BASE',
            'quantity' => 150,
            'unit' => 'kg',
            'production_date' => '2026-06-15',
            'expiry_date' => '2026-06-22',
            'status' => 1,
        ]);

        // 成品批次 (使用半成品花生酥面团)
        $finishedPeanutCookies = Batch::create([
            'batch_no' => 'F-PEANUT-COOKIE-20260613',
            'batch_type' => Batch::BATCH_TYPE_FINISHED,
            'product_id' => $createdProducts[0]->id,
            'product_name' => '花生酥饼干',
            'product_code' => $createdProducts[0]->code,
            'quantity' => 250,
            'unit' => 'kg',
            'production_date' => '2026-06-13',
            'expiry_date' => '2026-09-13',
            'status' => 1,
        ]);

        // 成品批次 (使用半成品泡芙坯)
        $finishedPuff = Batch::create([
            'batch_no' => 'F-CREAM-PUFF-20260616',
            'batch_type' => Batch::BATCH_TYPE_FINISHED,
            'product_id' => $createdProducts[3]->id,
            'product_name' => '奶油泡芙',
            'product_code' => $createdProducts[3]->code,
            'quantity' => 5000,
            'unit' => '个',
            'production_date' => '2026-06-16',
            'expiry_date' => '2026-06-23',
            'status' => 1,
        ]);

        // ========== 批次谱系关系 ==========
        // 花生酥面团 = 花生仁 + 面粉
        BatchLineage::create(['parent_batch_id' => $rawPeanut->id, 'child_batch_id' => $semiPeanutDough->id, 'usage_quantity' => 120, 'usage_unit' => 'kg']);
        BatchLineage::create(['parent_batch_id' => $rawFlour->id, 'child_batch_id' => $semiPeanutDough->id, 'usage_quantity' => 150, 'usage_unit' => 'kg']);

        // 泡芙蛋糕坯 = 面粉 + 鸡蛋
        BatchLineage::create(['parent_batch_id' => $rawFlour->id, 'child_batch_id' => $semiCakeBase->id, 'usage_quantity' => 80, 'usage_unit' => 'kg']);
        BatchLineage::create(['parent_batch_id' => $rawEgg->id, 'child_batch_id' => $semiCakeBase->id, 'usage_quantity' => 60, 'usage_unit' => 'kg']);

        // 花生酥饼干 = 花生酥面团
        BatchLineage::create(['parent_batch_id' => $semiPeanutDough->id, 'child_batch_id' => $finishedPeanutCookies->id, 'usage_quantity' => 250, 'usage_unit' => 'kg']);

        // 奶油泡芙 = 泡芙蛋糕坯
        BatchLineage::create(['parent_batch_id' => $semiCakeBase->id, 'child_batch_id' => $finishedPuff->id, 'usage_quantity' => 140, 'usage_unit' => 'kg']);

        // ========== 配送门店数据 ==========
        // 花生酥饼干配送
        BatchDelivery::create(['batch_id' => $finishedPeanutCookies->id, 'store_id' => $createdStores[0]->id, 'delivery_quantity' => 50, 'delivery_unit' => 'kg', 'delivery_date' => '2026-06-14', 'delivery_no' => 'D20260614001', 'status' => 'delivered']);
        BatchDelivery::create(['batch_id' => $finishedPeanutCookies->id, 'store_id' => $createdStores[1]->id, 'delivery_quantity' => 40, 'delivery_unit' => 'kg', 'delivery_date' => '2026-06-14', 'delivery_no' => 'D20260614002', 'status' => 'delivered']);
        BatchDelivery::create(['batch_id' => $finishedPeanutCookies->id, 'store_id' => $createdStores[2]->id, 'delivery_quantity' => 60, 'delivery_unit' => 'kg', 'delivery_date' => '2026-06-14', 'delivery_no' => 'D20260614003', 'status' => 'delivered']);

        // 奶油泡芙配送
        BatchDelivery::create(['batch_id' => $finishedPuff->id, 'store_id' => $createdStores[0]->id, 'delivery_quantity' => 800, 'delivery_unit' => '个', 'delivery_date' => '2026-06-17', 'delivery_no' => 'D20260617001', 'status' => 'delivered']);
        BatchDelivery::create(['batch_id' => $finishedPuff->id, 'store_id' => $createdStores[3]->id, 'delivery_quantity' => 1200, 'delivery_unit' => '个', 'delivery_date' => '2026-06-17', 'delivery_no' => 'D20260617002', 'status' => 'delivered']);
        BatchDelivery::create(['batch_id' => $finishedPuff->id, 'store_id' => $createdStores[4]->id, 'delivery_quantity' => 1500, 'delivery_unit' => '个', 'delivery_date' => '2026-06-17', 'delivery_no' => 'D20260617003', 'status' => 'delivered']);
    }
}
