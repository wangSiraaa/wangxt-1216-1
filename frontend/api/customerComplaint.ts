import apiClient from '~/plugins/api'

export const customerComplaintApi = {
  list(params: any = {}) {
    return apiClient.get('/customer-complaints', { params })
  },
  create(data: any) {
    return apiClient.post('/customer-complaints', data)
  },
  detail(id: number) {
    return apiClient.get(`/customer-complaints/${id}`)
  },
  update(id: number, data: any) {
    return apiClient.put(`/customer-complaints/${id}`, data)
  },
  resolve(id: number, resolution: string) {
    return apiClient.post(`/customer-complaints/${id}/resolve`, { resolution })
  },
  complaintTypes() {
    return apiClient.get('/complaint-types')
  },
}
