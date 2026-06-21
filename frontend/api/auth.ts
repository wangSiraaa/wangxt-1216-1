import apiClient from '~/plugins/api'

export interface LoginData {
  email: string
  password: string
}

export interface UserInfo {
  id: number
  name: string
  email: string
  role: string
  store_id: number | null
  store?: {
    id: number
    code: string
    name: string
  } | null
}

export const authApi = {
  login(data: LoginData) {
    return apiClient.post<{ user: UserInfo; token: string }>('/auth/login', data)
  },
  logout() {
    return apiClient.post('/auth/logout')
  },
  getUser() {
    return apiClient.get<UserInfo>('/auth/user')
  },
}
