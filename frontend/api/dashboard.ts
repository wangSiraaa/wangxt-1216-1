import apiClient from '~/plugins/api'

export const dashboardApi = {
  summary() {
    return apiClient.get('/dashboard/summary')
  },
  storeFeedbackStatus(recallTaskId?: number) {
    return apiClient.get('/dashboard/store-feedback-status', {
      params: { recall_task_id: recallTaskId },
    })
  },
  abnormalTrend(days = 30) {
    return apiClient.get('/dashboard/abnormal-trend', {
      params: { days },
    })
  },
}
