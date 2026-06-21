<template>
  <div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <el-card class="shadow-sm" shadow="never">
        <div class="flex items-center">
          <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center text-2xl">
            ⚠️
          </div>
          <div class="ml-4">
            <div class="text-sm text-gray-500">检测异常总数</div>
            <div class="text-2xl font-bold text-gray-800">{{ summary?.statistics?.total_abnormals || 0 }}</div>
          </div>
          <div class="ml-auto text-right">
            <el-tag v-if="summary?.statistics?.pending_abnormals" type="warning">
              待确认 {{ summary?.statistics?.pending_abnormals }}
            </el-tag>
            <el-tag v-else type="success">无待确认</el-tag>
          </div>
        </div>
      </el-card>

      <el-card class="shadow-sm" shadow="never">
        <div class="flex items-center">
          <div class="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center text-2xl">
            📢
          </div>
          <div class="ml-4">
            <div class="text-sm text-gray-500">召回任务总数</div>
            <div class="text-2xl font-bold text-gray-800">{{ summary?.statistics?.total_recalls || 0 }}</div>
          </div>
          <div class="ml-auto text-right">
            <el-tag v-if="summary?.statistics?.active_recalls" type="danger">
              进行中 {{ summary?.statistics?.active_recalls }}
            </el-tag>
          </div>
        </div>
      </el-card>

      <el-card class="shadow-sm" shadow="never">
        <div class="flex items-center">
          <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center text-2xl">
            📦
          </div>
          <div class="ml-4">
            <div class="text-sm text-gray-500">顾客投诉总数</div>
            <div class="text-2xl font-bold text-gray-800">{{ summary?.statistics?.total_complaints || 0 }}</div>
          </div>
          <div class="ml-auto text-right">
            <el-tag v-if="summary?.statistics?.pending_complaints" type="info">
              待处理 {{ summary?.statistics?.pending_complaints }}
            </el-tag>
          </div>
        </div>
      </el-card>

      <el-card class="shadow-sm" shadow="never">
        <div class="flex items-center">
          <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-2xl">
            🔒
          </div>
          <div class="ml-4">
            <div class="text-sm text-gray-500">批次总数</div>
            <div class="text-2xl font-bold text-gray-800">{{ summary?.statistics?.total_batches || 0 }}</div>
          </div>
          <div class="ml-auto text-right">
            <el-tag v-if="summary?.statistics?.locked_batches" type="danger">
              已锁定 {{ summary?.statistics?.locked_batches }}
            </el-tag>
          </div>
        </div>
      </el-card>
    </div>

    <el-tabs v-model="activeTab">
      <el-tab-pane label="活跃召回任务" name="tasks">
        <el-table :data="summary?.active_recall_tasks || []" stripe>
          <el-table-column prop="recall_no" label="召回编号" width="200">
            <template #default="{ row }">
              <el-link type="primary" @click="goToRecallDetail(row.id)">
                {{ row.recall_no }}
              </el-link>
            </template>
          </el-table-column>
          <el-table-column prop="title" label="召回标题" min-width="200" />
          <el-table-column label="召回级别" width="120">
            <template #default="{ row }">
              <el-tag v-if="row.recall_level === 'level1'" type="danger">一级(严重)</el-tag>
              <el-tag v-else-if="row.recall_level === 'level2'" type="warning">二级(较重)</el-tag>
              <el-tag v-else type="info">三级(一般)</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="下架进度" width="150">
            <template #default="{ row }">
              <el-progress :percentage="row.feedback_stats?.submitted_rate || 0" />
            </template>
          </el-table-column>
          <el-table-column label="门店反馈" width="200">
            <template #default="{ row }">
              <span class="text-green-600">{{ row.feedback_stats?.submitted || 0 }}</span>
              <span class="text-gray-400"> / </span>
              <span>{{ row.feedback_stats?.total || 0 }}</span>
              <span class="text-red-600 ml-2" v-if="row.feedback_stats?.missing > 0">
                (漏报{{ row.feedback_stats?.missing }}家)
              </span>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="120">
            <template #default="{ row }">
              <el-tag v-if="row.status === 'draft'" type="info">草稿</el-tag>
              <el-tag v-else-if="row.status === 'pending'" type="warning">待发布</el-tag>
              <el-tag v-else-if="row.status === 'published'" type="success">已发布</el-tag>
              <el-tag v-else-if="row.status === 'completed'" type="primary">已完成</el-tag>
              <el-tag v-else type="info">已取消</el-tag>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane label="漏报/逾期门店" name="missing">
        <el-alert
          title="门店漏报下架数量将在此处标红展示，总部需及时跟进"
          type="warning"
          :closable="false"
          class="mb-4"
        />
        <el-table :data="summary?.missing_feedbacks || []" stripe :row-class-name="missingRowClassName">
          <el-table-column label="门店" width="200">
            <template #default="{ row }">
              <div class="font-medium">{{ row.store?.code }}</div>
              <div class="text-gray-500 text-sm">{{ row.store?.name }}</div>
            </template>
          </el-table-column>
          <el-table-column label="召回任务" min-width="200">
            <template #default="{ row }">
              <div>{{ row.recallTask?.title }}</div>
              <div class="text-gray-400 text-sm">{{ row.recallTask?.recall_no }}</div>
            </template>
          </el-table-column>
          <el-table-column label="应下架数量" prop="received_quantity" width="140" align="right" />
          <el-table-column label="已下架数量" prop="off_shelf_quantity" width="140" align="right" />
          <el-table-column label="状态" width="150">
            <template #default="{ row }">
              <el-tag v-if="row.is_missing" type="danger">
                <el-icon><WarningFilled /></el-icon>
                漏报(标红)
              </el-tag>
              <el-tag v-else-if="row.status === 'overdue'" type="warning">
                <el-icon><Clock /></el-icon>
                逾期未报
              </el-tag>
              <el-tag v-else type="info">待反馈</el-tag>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane label="最近检测异常" name="abnormals">
        <el-table :data="summary?.recent_abnormals || []" stripe>
          <el-table-column prop="abnormal_no" label="异常编号" width="200">
            <template #default="{ row }">
              <el-link type="primary" @click="goToAbnormalDetail(row.id)">
                {{ row.abnormal_no }}
              </el-link>
            </template>
          </el-table-column>
          <el-table-column label="类型" width="130">
            <template #default="{ row }">
              <el-tag v-if="row.abnormal_type === 'allergen'" type="warning">过敏原</el-tag>
              <el-tag v-else-if="row.abnormal_type === 'microbe'" type="danger">微生物</el-tag>
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
        </el-table>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { WarningFilled, Clock } from '@element-plus/icons-vue'
import { dashboardApi } from '~/api'

const summary = ref<any>(null)
const activeTab = ref('tasks')

const missingRowClassName = ({ row }: any) => {
  if (row.is_missing) return 'missing-row'
  if (row.status === 'overdue') return 'overdue-row'
  return ''
}

const loadData = async () => {
  try {
    const response = await dashboardApi.summary()
    summary.value = response.data
  } catch (e) {}
}

const goToRecallDetail = (id: number) => {
  navigateTo(`/recall-tasks/${id}`)
}

const goToAbnormalDetail = (id: number) => {
  navigateTo(`/detection-abnormals/${id}`)
}

onMounted(() => {
  loadData()
})
</script>
