import apiClient from '~/plugins/api'

export interface BatchQuery {
  batch_type?: string
  batch_no?: string
  product_name?: string
  is_locked?: number
  status?: number
  page?: number
  per_page?: number
}

export const batchApi = {
  list(params: BatchQuery = {}) {
    return apiClient.get('/batches', { params })
  },
  detail(id: number) {
    return apiClient.get(`/batches/${id}`)
  },
  lineage(id: number) {
    return apiClient.get(`/batches/${id}/lineage`)
  },
  relatedBatches(id: number) {
    return apiClient.get(`/batches/${id}/related-batches`)
  },
  lock(id: number, reason: string, includeRelated = false) {
    return apiClient.post(`/batches/${id}/lock`, { reason, include_related: includeRelated })
  },
  unlock(id: number) {
    return apiClient.post(`/batches/${id}/unlock`)
  },
  batchTypes() {
    return apiClient.get('/batch-types')
  },
}
