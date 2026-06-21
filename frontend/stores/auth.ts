import { defineStore } from 'pinia'
import { authApi, type LoginData, type UserInfo } from '~/api/auth'

interface AuthState {
  user: UserInfo | null
  token: string | null
  initialized: boolean
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user: null,
    token: null,
    initialized: false,
  }),
  getters: {
    isLoggedIn: (state) => !!state.token,
    isHqAdmin: (state) => state.user?.role === 'hq_admin',
    isQcSupervisor: (state) => state.user?.role === 'qc_supervisor',
    isWarehouseStaff: (state) => state.user?.role === 'warehouse_staff',
    isStoreManager: (state) => state.user?.role === 'store_manager',
    userRole: (state) => state.user?.role || '',
    userStoreId: (state) => state.user?.store_id || null,
  },
  actions: {
    initialize() {
      if (process.client) {
        const token = localStorage.getItem('auth_token')
        const userStr = localStorage.getItem('user_info')
        if (token && userStr) {
          this.token = token
          try {
            this.user = JSON.parse(userStr)
          } catch {
            this.logout()
          }
        }
      }
      this.initialized = true
    },
    async login(data: LoginData) {
      const response = await authApi.login(data)
      this.token = response.data.token
      this.user = response.data.user
      if (process.client) {
        localStorage.setItem('auth_token', response.data.token)
        localStorage.setItem('user_info', JSON.stringify(response.data.user))
      }
      return response.data
    },
    async logout() {
      try {
        await authApi.logout()
      } catch {}
      this.token = null
      this.user = null
      if (process.client) {
        localStorage.removeItem('auth_token')
        localStorage.removeItem('user_info')
      }
      await navigateTo('/login')
    },
    async fetchUser() {
      if (this.token) {
        try {
          const response = await authApi.getUser()
          this.user = response.data
          if (process.client) {
            localStorage.setItem('user_info', JSON.stringify(response.data))
          }
        } catch {
          this.logout()
        }
      }
    },
  },
})
