<template>
  <div class="space-y-6">
    <el-card shadow="never">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold">检测异常管理</h2>
        <el-button type="primary" @click="openCreateDialog">
          <el-icon><Plus /></el-icon>
          登记异常
        </el-button>
      </div>
      <el-form :inline="true" class="mb-4">
        <el-form-item label="状态">
          <el-select v-model="query.status" placeholder="全部" clearable @change="loadData">
            <el-option label="待确认" value="pending" />
            <el-option label="已确认" value="confirmed" />
            <el-option label="已驳回" value="rejected" />
            <el-option label="已处理" value="resolved" />
          </el-select>
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="query.abnormal_type" placeholder="全部" clearable @change="loadData">
            <el-option label="过敏原异常" value="allergen" />
            <el-option label="微生物异常" value="microbe" />
            <el-option label="物理指标" value="physical" />
            <el-option label="化学指标" value="chemical" />
          </el-select>
        </el-form-item>
        <el-form-item label="批次号">
          <el-input v-model="query.batch_no" placeholder="请输入批次号" clearable @keyup.enter="loadData" />
        </el-form-item>
        <el-form-item>
          <el-button @click="loadData">查询</el-button>
          <el-button @click="resetQuery">重置</el-button>
        </el-form-item>
      </el-form>
      <el-table :data="list" stripe>
        <el-table-column prop="abnormal_no" label="异常编号" width="200">
          <template #default="{ row }">
            <el-link type="primary" @click="viewDetail(row.id)">
              {{ row.abnormal_no }}
            </el-link>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="130">
          <template #default="{ row }">
            <el-tag v-if="row.abnormal_type === 'allergen'" type="warning">过敏原</el-tag>
            <el-tag v-else-if="row.abnormal_type === 'microbe'" type="danger">微生物</el-tag>
            <el-tag v-else-if="row.abnormal_type === 'physical'" type="info">物理</el-tag>
            <el-tag v-else-if="row.abnormal_type === 'chemical'" type="warning">化学</el-tag>
            <el-tag v-else type="info">其他</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="detection_item" label="检测项目" width="150" />
        <el-table-column prop="product_name" label="产品" />
        <el-table-column prop="batch_no" label="批次号" width="180" />
        <el-table-column label="状态" width="120">
          <template #default="{ row }">
            <el-tag v-if="row.status === 'pending'" type="warning">待确认</el-tag>
            <el-tag v-else-if="row.status === 'confirmed'" type="success">已确认</el-tag>
            <el-tag v-else-if="row.status === 'rejected'" type="danger">已驳回</el-tag>
            <el-tag v-else type="info">已处理</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="登记人" width="120" prop="reported_by.name" />
        <el-table-column prop="created_at" label="登记时间" width="180" />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="viewDetail(row.id)">查看</el-button>
            <el-button
              link
              type="success"
              v-if="row.status === 'pending'"
              @click="handleConfirm(row)"
            >确认</el-button>
            <el-button
              link
              type="danger"
              v-if="row.status === 'pending'"
              @click="handleReject(row)"
            >驳回</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="dialogVisible" title="登记检测异常" width="600px">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="120px">
        <el-form-item label="异常类型" prop="abnormal_type">
          <el-select v-model="form.abnormal_type" placeholder="请选择">
            <el-option label="过敏原异常" value="allergen" />
            <el-option label="微生物检测异常" value="microbe" />
            <el-option label="物理指标异常" value="physical" />
            <el-option label="化学指标异常" value="chemical" />
            <el-option label="其他" value="other" />
          </el-select>
        </el-form-item>
        <el-form-item label="关联批次号" prop="batch_no">
          <el-input v-model="form.batch_no" placeholder="请输入批次号" />
        </el-form-item>
        <el-form-item label="产品名称" prop="product_name">
          <el-input v-model="form.product_name" placeholder="请输入产品名称" />
        </el-form-item>
        <el-form-item label="检测项目" prop="detection_item">
          <el-input v-model="form.detection_item" placeholder="如:沙门氏菌,花生过敏原" />
        </el-form-item>
        <el-form-item label="检测值">
          <el-input v-model="form.detection_value" placeholder="请输入检测值" />
        </el-form-item>
        <el-form-item label="标准值/阈值">
          <el-input v-model="form.standard_value" placeholder="请输入标准值" />
        </el-form-item>
        <el-form-item label="检测日期">
          <el-date-picker v-model="form.detection_date" type="date" value-format="YYYY-MM-DD" />
        </el-form-item>
        <el-form-item label="检测报告编号">
          <el-input v-model="form.detection_report_no" />
        </el-form-item>
        <el-form-item label="异常描述" prop="description">
          <el-input v-model="form.description" type="textarea" :rows="4" placeholder="请详细描述异常情况" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">提交</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Plus } from '@element-plus/icons-vue'
import type { FormInstance, FormRules } from 'element-plus'
import { detectionAbnormalApi } from '~/api'
import { ElMessage, ElMessageBox } from 'element-plus'

const list = ref<any[]>([])
const dialogVisible = ref(false)
const submitting = ref(false)
const formRef = ref<FormInstance>()

const query = reactive({
  status: '',
  abnormal_type: '',
  batch_no: '',
})

const form = reactive({
  abnormal_type: '',
  batch_no: '',
  product_name: '',
  detection_item: '',
  detection_value: '',
  standard_value: '',
  detection_date: '',
  detection_report_no: '',
  description: '',
})

const rules: FormRules = {
  abnormal_type: [{ required: true, message: '请选择异常类型', trigger: 'change' }],
  detection_item: [{ required: true, message: '请输入检测项目', trigger: 'blur' }],
  description: [{ required: true, message: '请输入异常描述', trigger: 'blur' }],
}

const loadData = async () => {
  try {
    const response = await detectionAbnormalApi.list(query)
    list.value = response.data.data || response.data
  } catch (e) {}
}

const resetQuery = () => {
  query.status = ''
  query.abnormal_type = ''
  query.batch_no = ''
  loadData()
}

const openCreateDialog = () => {
  Object.keys(form).forEach((k) => (form as any)[k] = '')
  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (valid) {
      submitting.value = true
      try {
        await detectionAbnormalApi.create({ ...form })
        ElMessage.success('检测异常已登记')
        dialogVisible.value = false
        loadData()
      } finally {
        submitting.value = false
      }
    }
  })
}

const viewDetail = (id: number) => {
  navigateTo(`/detection-abnormals/${id}`)
}

const handleConfirm = async (row: any) => {
  try {
    await ElMessageBox.confirm('确认该检测异常真实有效吗？确认后可发起召回任务。', '确认异常', {
      type: 'warning',
    })
    await detectionAbnormalApi.confirm(row.id)
    ElMessage.success('已确认异常')
    loadData()
  } catch {}
}

const handleReject = async (row: any) => {
  try {
    const { value } = await ElMessageBox.prompt('请输入驳回原因', '驳回异常', {
      inputPattern: /.+/,
      inputErrorMessage: '请输入驳回原因',
    })
    await detectionAbnormalApi.reject(row.id, value)
    ElMessage.success('已驳回')
    loadData()
  } catch {}
}

onMounted(() => {
  loadData()
})
</script>
