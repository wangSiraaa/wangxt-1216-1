import apiClient from '~/plugins/api'

export interface StoreFeedbackItemData {
  batch_id: number
  off_shelf_quantity: number
  returned_quantity?: number
  destroyed_quantity?: number
  sold_quantity?: number
}

export interface StoreFeedbackData {
  recall_task_id: number
  store_id: number
  items: StoreFeedbackItemData[]
  remark?: string
}

export const storeFeedbackApi = {
  list(params: any = {}) {
    return apiClient.get('/store-feedbacks', { params })
  },
  create(data: StoreFeedbackData) {
    return apiClient.post('/store-feedbacks', data)
  },
  detail(id: number) {
    return apiClient.get(`/store-feedbacks/${id}`)
  },
  update(id: number, data: Partial<StoreFeedbackData>) {
    return apiClient.put(`/store-feedbacks/${id}`, data)
  },
  submit(id: number) {
    return apiClient.post(`/store-feedbacks/${id}/submit`)
  },
  unreportedStores(recallTaskId: number) {
    return apiClient.get(`/unreported-stores/${recallTaskId}`)
  },
}
