<template>
  <div class="space-y-6">
    <el-card shadow="never">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold">召回任务管理</h2>
        <el-button type="primary" @click="openCreateDialog">
          <el-icon><Plus /></el-icon>
          创建召回任务
        </el-button>
      </div>
      <el-form :inline="true" class="mb-4">
        <el-form-item label="状态">
          <el-select v-model="query.status" placeholder="全部" clearable @change="loadData">
            <el-option label="草稿" value="draft" />
            <el-option label="待发布" value="pending" />
            <el-option label="已发布" value="published" />
            <el-option label="已完成" value="completed" />
            <el-option label="已取消" value="cancelled" />
          </el-select>
        </el-form-item>
        <el-form-item label="级别">
          <el-select v-model="query.recall_level" placeholder="全部" clearable @change="loadData">
            <el-option label="一级(严重)" value="level1" />
            <el-option label="二级(较重)" value="level2" />
            <el-option label="三级(一般)" value="level3" />
          </el-select>
        </el-form-item>
        <el-form-item label="召回编号">
          <el-input v-model="query.recall_no" placeholder="请输入" clearable @keyup.enter="loadData" />
        </el-form-item>
        <el-form-item>
          <el-button @click="loadData">查询</el-button>
          <el-button @click="resetQuery">重置</el-button>
        </el-form-item>
      </el-form>
      <el-table :data="list" stripe>
        <el-table-column prop="recall_no" label="召回编号" width="200">
          <template #default="{ row }">
            <el-link type="primary" @click="viewDetail(row.id)">
              {{ row.recall_no }}
            </el-link>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="召回标题" min-width="200" />
        <el-table-column label="级别" width="120">
          <template #default="{ row }">
            <el-tag v-if="row.recall_level === 'level1'" type="danger">一级</el-tag>
            <el-tag v-else-if="row.recall_level === 'level2'" type="warning">二级</el-tag>
            <el-tag v-else type="info">三级</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="关联异常" width="180">
          <template #default="{ row }">
            <div v-if="row.detection_abnormal">
              <div>{{ row.detection_abnormal.abnormal_no }}</div>
              <div class="text-xs text-gray-500">
                <el-tag
                  v-if="row.detection_abnormal.status === 'confirmed'"
                  type="success"
                  size="small"
                >已确认</el-tag>
                <el-tag v-else type="warning" size="small">未确认</el-tag>
              </div>
            </div>
            <span v-else class="text-gray-400">-</span>
          </template>
        </el-table-column>
        <el-table-column label="门店反馈" width="180">
          <template #default="{ row }">
            <el-progress
              :percentage="row.feedback_stats?.submitted_rate || 0"
              :stroke-width="12"
            />
            <div class="text-xs text-gray-500 mt-1">
              {{ row.feedback_stats?.submitted || 0 }} / {{ row.feedback_stats?.total || 0 }}
              <span class="text-red-500 ml-2" v-if="row.feedback_stats?.missing > 0">
                漏报{{ row.feedback_stats?.missing }}
              </span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag v-if="row.status === 'draft'" type="info">草稿</el-tag>
            <el-tag v-else-if="row.status === 'pending'" type="warning">待发布</el-tag>
            <el-tag v-else-if="row.status === 'published'" type="success">已发布</el-tag>
            <el-tag v-else-if="row.status === 'completed'" type="primary">已完成</el-tag>
            <el-tag v-else type="info">已取消</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="操作" width="280" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="viewDetail(row.id)">查看</el-button>
            <el-popconfirm
              v-if="(row.status === 'draft' || row.status === 'pending')"
              title="确认发布召回公告？只有检测异常已确认时才能发布。"
              @confirm="handlePublish(row)"
            >
              <template #reference>
                <el-button link type="success">发布公告</el-button>
              </template>
            </el-popconfirm>
            <el-popconfirm
              v-if="row.status === 'published'"
              title="确认完成该召回任务？"
              @confirm="handleComplete(row)"
            >
              <template #reference>
                <el-button link type="primary">完成</el-button>
              </template>
            </el-popconfirm>
            <el-popconfirm
              v-if="row.status !== 'completed' && row.status !== 'cancelled'"
              title="确认取消该召回任务？"
              @confirm="handleCancel(row)"
            >
              <template #reference>
                <el-button link type="danger">取消</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="dialogVisible" title="创建召回任务" width="700px">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="120px">
        <el-form-item label="召回标题" prop="title">
          <el-input v-model="form.title" placeholder="请输入召回标题" />
        </el-form-item>
        <el-form-item label="召回级别" prop="recall_level">
          <el-select v-model="form.recall_level" placeholder="请选择">
            <el-option label="一级(严重)" value="level1" />
            <el-option label="二级(较重)" value="level2" />
            <el-option label="三级(一般)" value="level3" />
          </el-select>
        </el-form-item>
        <el-form-item label="召回原因" prop="recall_reason_type">
          <el-select v-model="form.recall_reason_type" placeholder="请选择">
            <el-option label="过敏原" value="allergen" />
            <el-option label="微生物" value="microbe" />
            <el-option label="品质问题" value="quality" />
            <el-option label="顾客投诉" value="complaint" />
            <el-option label="其他" value="other" />
          </el-select>
        </el-form-item>
        <el-form-item label="关联检测异常">
          <el-select
            v-model="form.detection_abnormal_id"
            placeholder="选择已确认的检测异常"
            clearable
            filterable
          >
            <el-option
              v-for="abnormal in confirmedAbnormals"
              :key="abnormal.id"
              :label="`${abnormal.abnormal_no} - ${abnormal.detection_item}`"
              :value="abnormal.id"
            />
          </el-select>
          <div class="text-xs text-orange-500 mt-1">
            ⚠️ 检测未确认的异常不能发布召回公告
          </div>
        </el-form-item>
        <el-form-item label="召回描述" prop="description">
          <el-input v-model="form.description" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item label="涉及批次" prop="batch_ids">
          <el-select
            v-model="form.batch_ids"
            multiple
            filterable
            placeholder="选择涉及的批次"
            style="width: 100%"
          >
            <el-option
              v-for="batch in batches"
              :key="batch.id"
              :label="`${batch.batch_no} - ${batch.product_name}`"
              :value="batch.id"
            />
          </el-select>
          <div class="text-xs text-orange-500 mt-1">
            💡 选择原料批次时，系统将自动锁定所有使用该原料的半成品和成品批次
          </div>
        </el-form-item>
        <el-form-item label="预计完成日期">
          <el-date-picker
            v-model="form.expected_completion_date"
            type="datetime"
            value-format="YYYY-MM-DD HH:mm:ss"
          />
        </el-form-item>
        <el-form-item label="公告内容">
          <el-input v-model="form.announcement_content" type="textarea" :rows="3" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="handleSubmit">创建</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Plus } from '@element-plus/icons-vue'
import type { FormInstance, FormRules } from 'element-plus'
import { recallTaskApi, detectionAbnormalApi, batchApi } from '~/api'
import { ElMessage } from 'element-plus'

const list = ref<any[]>([])
const dialogVisible = ref(false)
const submitting = ref(false)
const confirmedAbnormals = ref<any[]>([])
const batches = ref<any[]>([])
const formRef = ref<FormInstance>()

const query = reactive({
  status: '',
  recall_level: '',
  recall_no: '',
})

const form = reactive({
  title: '',
  recall_level: 'level3',
  recall_reason_type: 'allergen',
  detection_abnormal_id: undefined as number | undefined,
  description: '',
  batch_ids: [] as number[],
  expected_completion_date: '',
  announcement_content: '',
})

const rules: FormRules = {
  title: [{ required: true, message: '请输入召回标题', trigger: 'blur' }],
  recall_level: [{ required: true, message: '请选择召回级别', trigger: 'change' }],
  recall_reason_type: [{ required: true, message: '请选择召回原因', trigger: 'change' }],
  description: [{ required: true, message: '请输入召回描述', trigger: 'blur' }],
  batch_ids: [{ required: true, message: '请选择涉及批次', trigger: 'change' }],
}

const loadData = async () => {
  try {
    const response = await recallTaskApi.list(query)
    list.value = response.data.data || response.data
  } catch (e) {}
}

const resetQuery = () => {
  query.status = ''
  query.recall_level = ''
  query.recall_no = ''
  loadData()
}

const openCreateDialog = async () => {
  Object.assign(form, {
    title: '',
    recall_level: 'level3',
    recall_reason_type: 'allergen',
    detection_abnormal_id: undefined,
    description: '',
    batch_ids: [],
    expected_completion_date: '',
    announcement_content: '',
  })

  const [abnormalRes, batchRes] = await Promise.all([
    detectionAbnormalApi.list({ status: 'confirmed', per_page: 100 }),
    batchApi.list({ per_page: 200 }),
  ])
  confirmedAbnormals.value = abnormalRes.data.data || abnormalRes.data || []
  batches.value = batchRes.data.data || batchRes.data || []

  dialogVisible.value = true
}

const handleSubmit = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (valid) {
      submitting.value = true
      try {
        await recallTaskApi.create({ ...form })
        ElMessage.success('召回任务已创建，相关批次已自动锁定')
        dialogVisible.value = false
        loadData()
      } catch (e) {} finally {
        submitting.value = false
      }
    }
  })
}

const viewDetail = (id: number) => navigateTo(`/recall-tasks/${id}`)

const handlePublish = async (row: any) => {
  try {
    await recallTaskApi.publish(row.id)
    ElMessage.success('召回公告已发布')
    loadData()
  } catch (e) {}
}

const handleComplete = async (row: any) => {
  try {
    await recallTaskApi.complete(row.id)
    ElMessage.success('召回任务已完成')
    loadData()
  } catch (e) {}
}

const handleCancel = async (row: any) => {
  try {
    await recallTaskApi.cancel(row.id)
    ElMessage.success('召回任务已取消')
    loadData()
  } catch (e) {}
}

onMounted(() => loadData())
</script>
