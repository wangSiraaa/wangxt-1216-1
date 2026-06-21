<template>
  <div class="space-y-6">
    <el-card shadow="never">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-semibold">批次追溯管理</h2>
      </div>
      <el-form :inline="true" class="mb-4">
        <el-form-item label="批次类型">
          <el-select v-model="query.batch_type" placeholder="全部" clearable @change="loadData">
            <el-option label="原料批次" value="raw_material" />
            <el-option label="半成品批次" value="semi_finished" />
            <el-option label="成品批次" value="finished" />
          </el-select>
        </el-form-item>
        <el-form-item label="批次号">
          <el-input v-model="query.batch_no" placeholder="请输入批次号" clearable @keyup.enter="loadData" />
        </el-form-item>
        <el-form-item label="锁定状态">
          <el-select v-model="query.is_locked" placeholder="全部" clearable @change="loadData">
            <el-option label="已锁定" :value="1" />
            <el-option label="未锁定" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button @click="loadData">查询</el-button>
          <el-button @click="resetQuery">重置</el-button>
        </el-form-item>
      </el-form>
      <el-table :data="list" stripe>
        <el-table-column prop="batch_no" label="批次号" width="200">
          <template #default="{ row }">
            <el-link type="primary" @click="viewDetail(row.id)">
              {{ row.batch_no }}
            </el-link>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="120">
          <template #default="{ row }">
            <el-tag v-if="row.batch_type === 'raw_material'" type="info">原料</el-tag>
            <el-tag v-else-if="row.batch_type === 'semi_finished'" type="warning">半成品</el-tag>
            <el-tag v-else type="success">成品</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="product_name" label="产品/原料名称" />
        <el-table-column label="数量" width="150" align="right">
          <template #default="{ row }">
            {{ row.quantity }} {{ row.unit }}
          </template>
        </el-table-column>
        <el-table-column prop="production_date" label="生产日期" width="120" />
        <el-table-column prop="expiry_date" label="保质期" width="120" />
        <el-table-column label="锁定状态" width="100">
          <template #default="{ row }">
            <el-tag v-if="row.is_locked" type="danger">已锁定</el-tag>
            <el-tag v-else type="success">正常</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="320" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="viewDetail(row.id)">查看</el-button>
            <el-button link type="primary" @click="viewLineage(row.id)">批次谱系</el-button>
            <el-button link type="warning" @click="viewRelated(row.id)">关联批次</el-button>
            <el-button
              v-if="!row.is_locked"
              link
              type="danger"
              @click="handleLock(row)"
            >锁定</el-button>
            <el-button v-else link type="success" @click="handleUnlock(row)">解锁</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="lockDialogVisible" title="锁定批次" width="500px">
      <el-form label-width="100px">
        <el-form-item label="锁定原因">
          <el-input v-model="lockReason" type="textarea" :rows="3" placeholder="请输入锁定原因" />
        </el-form-item>
        <el-form-item v-if="currentBatch?.batch_type === 'raw_material'">
          <el-checkbox v-model="includeRelated">
            同时锁定使用此原料的半成品和成品批次
          </el-checkbox>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="lockDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submitLock">确认锁定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { batchApi } from '~/api'
import { ElMessage, ElMessageBox } from 'element-plus'

const list = ref<any[]>([])
const lockDialogVisible = ref(false)
const lockReason = ref('')
const includeRelated = ref(false)
const currentBatch = ref<any>(null)

const query = reactive({
  batch_type: '',
  batch_no: '',
  is_locked: undefined as number | undefined,
})

const loadData = async () => {
  try {
    const params: any = { ...query }
    if (params.is_locked === undefined) delete params.is_locked
    const response = await batchApi.list(params)
    list.value = response.data.data || response.data
  } catch (e) {}
}

const resetQuery = () => {
  query.batch_type = ''
  query.batch_no = ''
  query.is_locked = undefined
  loadData()
}

const viewDetail = (id: number) => navigateTo(`/batches/${id}`)
const viewLineage = (id: number) => navigateTo(`/batches/${id}/lineage`)
const viewRelated = (id: number) => navigateTo(`/batches/${id}/related`)

const handleLock = (row: any) => {
  currentBatch.value = row
  lockReason.value = ''
  includeRelated.value = false
  lockDialogVisible.value = true
}

const submitLock = async () => {
  if (!lockReason.value.trim()) {
    ElMessage.warning('请输入锁定原因')
    return
  }
  try {
    const res = await batchApi.lock(currentBatch.value.id, lockReason.value, includeRelated.value)
    ElMessage.success(res.data.message || '锁定成功')
    lockDialogVisible.value = false
    loadData()
  } catch {}
}

const handleUnlock = async (row: any) => {
  try {
    await ElMessageBox.confirm('确认解锁该批次吗？', '解锁批次', { type: 'warning' })
    await batchApi.unlock(row.id)
    ElMessage.success('解锁成功')
    loadData()
  } catch {}
}

onMounted(() => loadData())
</script>
