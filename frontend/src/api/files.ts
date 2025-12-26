import apiClient from './client'

export interface FileDTO {
  id: number
  filename: string
  size: number
  content_type: string
  file_path: string
  created_at: string
}

export interface PresignedUploadResponse {
  file_id: number
  upload_url: string
}

export const filesApi = {
  getPresignedUploadUrl: async (data: {
    filename: string
    content_type: string
    size: number
    assignment_id?: number
    ticket_id?: number
  }): Promise<PresignedUploadResponse> => {
    const response = await apiClient.post('/v1/files/presigned-upload', {
      ...data,
      mime: data.content_type, // Backend expects 'mime' field
    })
    return response.data
  },

  confirmUpload: async (
    fileId: number,
    data: {
      filename: string
      size: number
      content_type: string
    }
  ): Promise<FileDTO> => {
    const response = await apiClient.post(`/v1/files/${fileId}/confirm`, data)
    // Backend returns { file: FileDTO, download_url: string }
    return response.data.file || response.data
  },

  download: async (fileId: number): Promise<Blob> => {
    const response = await apiClient.get(`/v1/files/${fileId}/download`, {
      responseType: 'blob',
    })
    return response.data
  },
}

