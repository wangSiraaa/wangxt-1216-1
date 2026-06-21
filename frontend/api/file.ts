import apiClient from '~/plugins/api'

export const fileApi = {
  upload(file: File, relatedType?: string, relatedId?: number) {
    const formData = new FormData()
    formData.append('file', file)
    if (relatedType) {
      formData.append('related_type', relatedType)
    }
    if (relatedId) {
      formData.append('related_id', String(relatedId))
    }
    return apiClient.post('/files/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
  detail(id: number) {
    return apiClient.get(`/files/${id}`)
  },
  download(id: number) {
    return `${apiClient.defaults.baseURL}/files/${id}/download`
  },
  remove(id: number) {
    return apiClient.delete(`/files/${id}`)
  },
  downloadHistory(id: number) {
    return apiClient.get(`/files/download-history/${id}`)
  },
}
