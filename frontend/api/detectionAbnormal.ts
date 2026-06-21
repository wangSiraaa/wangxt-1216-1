import apiClient from '~/plugins/api'

export interface DetectionAbnormalData {
  abnormal_type: string
  batch_id?: number
  batch_no?: string
  product_id?: number
  product_name?: string
  detection_item: string
  detection_value?: string
  standard_value?: string
  description: string
  detection_report_no?: string
  detection_date?: string
}

export const detectionAbnormalApi = {
  list(params: any = {}) {
    return apiClient.get('/detection-abnormals', { params })
  },
  create(data: DetectionAbnormalData) {
    return apiClient.post('/detection-abnormals', data)
  },
  detail(id: number) {
    return apiClient.get(`/detection-abnormals/${id}`)
  },
  update(id: number, data: Partial<DetectionAbnormalData>) {
    return apiClient.put(`/detection-abnormals/${id}`, data)
  },
  confirm(id: number, remark?: string) {
    return apiClient.post(`/detection-abnormals/${id}/confirm`, { remark })
  },
  reject(id: number, remark: string) {
    return apiClient.post(`/detection-abnormals/${id}/reject`, { remark })
  },
  abnormalTypes() {
    return apiClient.get('/detection-abnormal-types')
  },
}
