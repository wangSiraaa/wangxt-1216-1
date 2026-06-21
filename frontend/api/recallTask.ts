import apiClient from '~/plugins/api'

export interface RecallTaskData {
  title: string
  description: string
  recall_level: string
  recall_reason_type: string
  detection_abnormal_id?: number
  customer_complaint_id?: number
  batch_ids: number[]
  expected_completion_date?: string
  announcement_content?: string
}

export const recallTaskApi = {
  list(params: any = {}) {
    return apiClient.get('/recall-tasks', { params })
  },
  create(data: RecallTaskData) {
    return apiClient.post('/recall-tasks', data)
  },
  detail(id: number) {
    return apiClient.get(`/recall-tasks/${id}`)
  },
  update(id: number, data: Partial<RecallTaskData>) {
    return apiClient.put(`/recall-tasks/${id}`, data)
  },
  publish(id: number) {
    return apiClient.post(`/recall-tasks/${id}/publish`)
  },
  cancel(id: number) {
    return apiClient.post(`/recall-tasks/${id}/cancel`)
  },
  complete(id: number, summary?: string) {
    return apiClient.post(`/recall-tasks/${id}/complete`, { summary })
  },
  statuses() {
    return apiClient.get('/recall-task-statuses')
  },
}
