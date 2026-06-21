<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-100">
    <el-card class="w-full max-w-md shadow-2xl" rounded>
      <div class="text-center mb-8">
        <div class="text-5xl mb-4">🛡️</div>
        <h1 class="text-2xl font-bold text-gray-800">中央厨房食品召回系统</h1>
        <p class="text-gray-500 mt-2">Food Recall Management System</p>
      </div>
      <el-form
        ref="loginForm"
        :model="form"
        :rules="rules"
        label-position="top"
        @keyup.enter="handleLogin"
      >
        <el-form-item label="邮箱地址" prop="email">
          <el-input
            v-model="form.email"
            placeholder="请输入邮箱地址"
            size="large"
            :prefix-icon="User"
            clearable
          />
        </el-form-item>
        <el-form-item label="密码" prop="password">
          <el-input
            v-model="form.password"
            type="password"
            placeholder="请输入密码"
            size="large"
            :prefix-icon="Lock"
            show-password
          />
        </el-form-item>
        <el-button
          type="primary"
          size="large"
          class="w-full mt-2"
          :loading="loading"
          @click="handleLogin"
        >
          登 录
        </el-button>
      </el-form>
      <div class="mt-6 text-sm text-gray-500 text-center space-y-1">
        <p>测试账号：</p>
        <p>管理员：admin@example.com / admin123</p>
        <p>品控主管：qc@example.com / qc123456</p>
      </div>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { User, Lock } from '@element-plus/icons-vue'
import type { FormInstance, FormRules } from 'element-plus'
import { useAuthStore } from '~/stores/auth'
import { ElMessage } from 'element-plus'

const authStore = useAuthStore()

const loginForm = ref<FormInstance>()
const loading = ref(false)

const form = reactive({
  email: '',
  password: '',
})

const rules: FormRules = {
  email: [
    { required: true, message: '请输入邮箱地址', trigger: 'blur' },
    { type: 'email', message: '请输入正确的邮箱格式', trigger: 'blur' },
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 6, message: '密码长度不少于6位', trigger: 'blur' },
  ],
}

const handleLogin = async () => {
  if (!loginForm.value) return
  await loginForm.value.validate(async (valid) => {
    if (valid) {
      loading.value = true
      try {
        await authStore.login({
          email: form.email,
          password: form.password,
        })
        ElMessage.success('登录成功')
        await navigateTo('/')
      } catch (e) {
        // 错误已在拦截器中处理
      } finally {
        loading.value = false
      }
    }
  })
}
</script>
