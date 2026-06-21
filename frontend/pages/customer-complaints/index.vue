<template>
  <div class="space-y-6">
    <el-card shadow="never">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold">顾客投诉管理</h2>
        <el-button type="primary" @click="dialogVisible = true">
          <el-icon><Plus /></el-icon>
          登记投诉
        </el-button>
      </div>
      <el-form :inline="true" class="mb-4">
        <el-form-item label="状态">
          <el-select v-model="query.status" placeholder="全部" clearable @change="loadData">
            <el-option label="待处理" value="pending" />
            <el-option label="处理中" value="processing" />
            <el-option label="已解决" value="resolved" />
            <el-option label="已关闭" value="closed" />
          </el-select>
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="query.complaint_type" placeholder="全部" clearable @change="loadData">
            <el-option label="过敏反应" value="allergy" />
            <el-option label="食物中毒" value="food_poisoning" />
            <el-option label="异物" value="foreign_matter" />
            <el-option label="品质问题" value="quality" />
          </el-select>
        </el-form-item>
        <el-form-item label="严重程度">
          <el-select v-model="query.severity" placeholder="全部" clearable @change="loadData">
            <el-option label="轻微" value="mild" />
            <el-option label="一般" value="general" />
            <el-option label="严重" value="serious" />
            <el-option label="危重" value="critical" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button @click="loadData">查询</el-button>
          <el-button @click="resetQuery">重置</el-button>
        </el-form-item>
      </el-form>
      <el-table :data="list" stripe>
        <el-table-column prop="complaint_no" label="投诉单号" width="200">
          <template #default="{ row }">
            <el-link type="primary" @click="navigateTo(`/customer-complaints/${row.id}`)">
              {{ row.complaint_no }}
            </el-link>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="130">
          <template #default="{ row }">
            <el-tag v-if="row.complaint_type === 'allergy'" type="warning">过敏</el-tag>
            <el-tag v-else-if="row.complaint_type === 'food_poisoning'" type="danger">中毒</el-tag>
            <el-tag v-else-if="row.complaint_type === 'foreign_matter'" type="info">异物</el-tag>
            <el-tag v-else-if="row.complaint_type === 'quality'" type="warning">品质</el-tag>
            <el-tag v-else type="info">其他</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="严重程度" width="100">
          <template #default="{ row }">
            <el-tag v-if="row.severity === 'mild'" type="success">轻微</el-tag>
            <el-tag v-else-if="row.severity === 'general'" type="info">一般</el-tag>
            <el-tag v-else-if="row.severity === 'serious'" type="warning">严重</el-tag>
            <el-tag v-else type="danger">危重</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="product_name" label="产品" />
        <el-table-column prop="batch_no" label="批次号" width="180" />
        <el-table-column label="门店" width="180">
          <template #default="{ row }">{{ row.store?.name }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag v-if="row.status === 'pending'" type="warning">待处理</el-tag>
            <el-tag v-else-if="row.status === 'processing'" type="primary">处理中</el-tag>
            <el-tag v-else-if="row.status === 'resolved'" type="success">已解决</el-tag>
            <el-tag v-else type="info">已关闭</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="登记时间" width="180" />
      </el-table>
    </el-card>

    <el-dialog v-model="dialogVisible" title="登记顾客投诉" width="600px">
      <el-form :model="form" label-width="120px">
        <el-form-item label="投诉类型">
          <el-select v-model="form.complaint_type" placeholder="请选择">
            <el-option label="过敏反应" value="allergy" />
            <el-option label="食物中毒" value="food_poisoning" />
            <el-option label="异物" value="foreign_matter" />
            <el-option label="品质问题" value="quality" />
            <el-option label="包装问题" value="packaging" />
            <el-option label="其他" value="other" />
          </el-select>
        </el-form-item>
        <el-form-item label="顾客姓名">
          <el-input v-model="form.customer_name" />
        </el-form-item>
        <el-form-item label="联系电话">
          <el-input v-model="form.customer_phone" />
        </el-form-item>
        <el-form-item label="严重程度">
          <el-select v-model="form.severity">
            <el-option label="轻微" value="mild" />
            <el-option label="一般" value="general" />
            <el-option label="严重" value="serious" />
            <el-option label="危重" value="critical" />
          </el-select>
        </el-form-item>
        <el-form-item label="批次号">
          <el-input v-model="form.batch_no" />
        </el-form-item>
        <el-form-item label="产品名称">
          <el-input v-model="form.product_name" />
        </el-form-item>
        <el-form-item label="投诉描述">
          <el-input v-model="form.description" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="症状描述">
          <el-input v-model="form.symptoms" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmit">提交</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Plus } from '@element-plus/icons-vue'
import { customerComplaintApi } from '~/api'
import { ElMessage } from 'element-plus'

const list = ref<any[]>([])
const dialogVisible = ref(false)

const query = reactive({
  status: '',
  complaint_type: '',
  severity: '',
})

const form = reactive({
  complaint_type: '',
  customer_name: '',
  customer_phone: '',
  severity: 'general',
  batch_no: '',
  product_name: '',
  description: '',
  symptoms: '',
})

const loadData = async () => {
  try {
    const response = await customerComplaintApi.list(query)
    list.value = response.data.data || response.data
  } catch (e) {}
}

const resetQuery = () => {
  query.status = ''
  query.complaint_type = ''
  query.severity = ''
  loadData()
}

const handleSubmit = async () => {
  try {
    await customerComplaintApi.create({ ...form })
    ElMessage.success('投诉已登记')
    dialogVisible.value = false
    loadData()
  } catch (e) {}
}

onMounted(() => loadData())
</script>
