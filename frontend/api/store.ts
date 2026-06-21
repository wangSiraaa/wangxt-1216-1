import apiClient from '~/plugins/api'

export const storeApi = {
  list(params: any = {}) {
    return apiClient.get('/stores', { params })
  },
}

export const productApi = {
  list(params: any = {}) {
    return apiClient.get('/products', { params })
  },
}
