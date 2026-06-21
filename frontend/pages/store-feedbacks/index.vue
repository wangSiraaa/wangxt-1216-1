<template>
  <div class="space-y-6">
    <el-card shadow="never">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold">门店下架反馈</h2>
      </div>
      <el-form :inline="true" class="mb-4">
        <el-form-item label="召回任务">
          <el-select
            v-model="query.recall_task_id"
            placeholder="请选择"
            clearable
            filterable
            @change="loadData"
          >
            <el-option
              v-for="task in recallTasks"
              :key="task.id"
              :label="`${task.recall_no} - ${task.title}`"
              :value="task.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="门店">
          <el-input v-model="query.store_keyword" placeholder="门店编码/名称" clearable />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="query.status" placeholder="全部" clearable @change="loadData">
            <el-option label="待反馈" value="pending" />
            <el-option label="已反馈" value="submitted" />
            <el-option label="逾期未报" value="overdue" />
            <el-option label="总部已确认" value="confirmed" />
          </el-select>
        </el-form-item>
        <el-form-item label="漏报">
          <el-select v-model="query.is_missing" placeholder="全部" clearable @change="loadData">
            <el-option label="漏报(标红)" :value="1" />
            <el-option label="正常上报" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button @click="loadData">查询</el-button>
          <el-button type="warning" @click="checkMissing">检查漏报门店</el-button>
        </el-form-item>
      </el-form>

      <el-alert
        v-if="query.recall_task_id"
        :title="`漏报的门店将自动标红展示，便于总部跟进处理`"
        type="warning"
        :closable="false"
        class="mb-4"
      />

      <el-table :data="list" stripe :row-class-name="rowClassName">
        <el-table-column label="门店" width="200">
          <template #default="{ row }">
            <div class="font-medium">
              {{ row.store?.code || row.store_code }}
            </div>
            <div class="text-gray-500 text-sm">
              {{ row.store?.name || row.store_name }}
            </div>
          </template>
        </el-table-column>
        <el-table-column label="召回任务" min-width="200">
          <template #default="{ row }">
            <div>{{ row.recallTask?.title }}</div>
            <div class="text-gray-400 text-sm">{{ row.recallTask?.recall_no }}</div>
          </template>
        </el-table-column>
        <el-table-column label="应下架" prop="received_quantity" width="120" align="right" />
        <el-table-column label="已下架" prop="off_shelf_quantity" width="120" align="right">
          <template #default="{ row }">
            <span class="font-semibold" :class="row.off_shelf_quantity > 0 ? 'text-green-600' : ''">
              {{ row.off_shelf_quantity }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="退回总部" prop="returned_quantity" width="120" align="right" />
        <el-table-column label="门店销毁" prop="destroyed_quantity" width="120" align="right" />
        <el-table-column label="已售出" prop="sold_quantity" width="100" align="right" />
        <el-table-column label="状态" width="150">
          <template #default="{ row }">
            <el-tag v-if="row.is_missing" type="danger">
              <el-icon><WarningFilled /></el-icon>
              漏报(标红)
            </el-tag>
            <el-tag v-else-if="row.status === 'overdue'" type="warning">逾期未报</el-tag>
            <el-tag v-else-if="row.status === 'pending'" type="info">待反馈</el-tag>
            <el-tag v-else-if="row.status === 'submitted'" type="primary">已反馈</el-tag>
            <el-tag v-else type="success">已确认</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="submitted_at" label="提交时间" width="180" />
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="viewDetail(row.id)">查看</el-button>
            <el-button link type="primary" @click="editFeedback(row)">编辑</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { WarningFilled } from '@element-plus/icons-vue'
import { storeFeedbackApi, recallTaskApi } from '~/api'
import { ElMessage } from 'element-plus'

const list = ref<any[]>([])
const recallTasks = ref<any[]>([])

const query = reactive({
  recall_task_id: undefined as number | undefined,
  store_keyword: '',
  status: '',
  is_missing: undefined as number | undefined,
})

const rowClassName = ({ row }: any) => {
  if (row.is_missing) return 'missing-row'
  if (row.status === 'overdue') return 'overdue-row'
  return ''
}

const loadData = async () => {
  try {
    const params: any = { ...query }
    if (params.is_missing === undefined) delete params.is_missing
    if (params.recall_task_id === undefined) delete params.recall_task_id
    const response = await storeFeedbackApi.list(params)
    list.value = response.data.data || response.data
  } catch (e) {}
}

const loadRecallTasks = async () => {
  try {
    const response = await recallTaskApi.list({ per_page: 100 })
    recallTasks.value = response.data.data || response.data
  } catch (e) {}
}

const checkMissing = async () => {
  if (!query.recall_task_id) {
    ElMessage.warning('请先选择召回任务')
    return
  }
  try {
    const response = await storeFeedbackApi.unreportedStores(query.recall_task_id)
    ElMessage.success(
      `共检查到 ${response.data.unreported_count} 家漏报门店，已自动标红`
    )
    loadData()
  } catch (e) {}
}

const viewDetail = (id: number) => navigateTo(`/store-feedbacks/${id}`)
const editFeedback = (row: any) => navigateTo(`/store-feedbacks/${row.id}/edit`)

onMounted(() => {
  loadData()
  loadRecallTasks()
})
</script>
