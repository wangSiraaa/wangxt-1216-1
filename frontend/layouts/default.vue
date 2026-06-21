<template>
  <div class="flex h-screen bg-gray-50">
    <aside class="w-64 bg-white border-r border-gray-200 flex flex-col">
      <div class="h-16 flex items-center px-6 border-b border-gray-200">
        <span class="text-xl">🛡️</span>
        <span class="ml-2 font-bold text-gray-800">食品召回系统</span>
      </div>
      <nav class="flex-1 py-4 overflow-y-auto">
        <el-menu
          :default-active="activeMenu"
          router
          class="border-none"
        >
          <el-menu-item index="/">
            <el-icon><DataBoard /></el-icon>
            <span>总部看板</span>
          </el-menu-item>
          <el-menu-item index="/detection-abnormals">
            <el-icon><Warning /></el-icon>
            <span>检测异常管理</span>
          </el-menu-item>
          <el-menu-item index="/batches">
            <el-icon><Box /></el-icon>
            <span>批次追溯管理</span>
          </el-menu-item>
          <el-menu-item index="/recall-tasks">
            <el-icon><RefreshLeft /></el-icon>
            <span>召回任务管理</span>
          </el-menu-item>
          <el-menu-item index="/store-feedbacks">
            <el-icon><List /></el-icon>
            <span>门店下架反馈</span>
          </el-menu-item>
          <el-menu-item index="/customer-complaints">
            <el-icon><ChatDotRound /></el-icon>
            <span>顾客投诉管理</span>
          </el-menu-item>
        </el-menu>
      </nav>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden">
      <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6">
        <div class="text-lg font-medium text-gray-700">
          {{ pageTitle }}
        </div>
        <div class="flex items-center space-x-4">
          <el-dropdown>
            <div class="flex items-center cursor-pointer">
              <el-avatar :size="36" class="bg-blue-500">
                {{ authStore.user?.name?.charAt(0) || 'U' }}
              </el-avatar>
              <div class="ml-3">
                <div class="text-sm font-medium text-gray-700">{{ authStore.user?.name }}</div>
                <div class="text-xs text-gray-500">{{ roleLabel }}</div>
              </div>
            </div>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item @click="handleLogout">
                  <el-icon><SwitchButton /></el-icon>
                  退出登录
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </header>
      <main class="flex-1 overflow-auto p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import {
  DataBoard,
  Warning,
  Box,
  RefreshLeft,
  List,
  ChatDotRound,
  SwitchButton,
} from '@element-plus/icons-vue'
import { useAuthStore } from '~/stores/auth'
import { useRoute } from 'vue-router'

const authStore = useAuthStore()
const route = useRoute()

const activeMenu = computed(() => route.path)

const pageTitle = computed(() => {
  const titles: Record<string, string> = {
    '/': '总部看板',
    '/detection-abnormals': '检测异常管理',
    '/batches': '批次追溯管理',
    '/recall-tasks': '召回任务管理',
    '/store-feedbacks': '门店下架反馈',
    '/customer-complaints': '顾客投诉管理',
  }
  return titles[route.path] || '食品召回系统'
})

const roleLabel = computed(() => {
  const labels: Record<string, string> = {
    hq_admin: '总部管理员',
    qc_supervisor: '品控主管',
    warehouse_staff: '仓配人员',
    store_manager: '门店负责人',
    staff: '普通员工',
  }
  return labels[authStore.userRole] || authStore.userRole
})

const handleLogout = async () => {
  await authStore.logout()
}

onMounted(() => {
  if (!authStore.initialized) {
    authStore.initialize()
  }
})
</script>
