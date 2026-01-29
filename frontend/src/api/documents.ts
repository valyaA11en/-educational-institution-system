import apiClient from './client'

export interface DocumentDTO {
  id: number
  type: string
  number: string
  date: string
  status: 'draft' | 'on_review' | 'approved' | 'signed' | 'archived'
  template_id: number
  data_json: Record<string, any>
  created_by: number
  signed_by: number | null
  signed_at: string | null
  verify_hash: string
  created_at: string
  updated_at: string
  template?: {
    id: number
    type: string
    name: string
  }
  creator?: {
    id: number
    fio: string
  }
  signer?: {
    id: number
    fio: string
  }
  routes?: Array<{
    id: number
    step_no: number
    approver_role_id: number | null
    approver_user_id: number | null
    status: 'pending' | 'approved' | 'rejected'
    decided_at: string | null
    comment: string | null
    approver_role?: {
      id: number
      name: string
    }
    approver_user?: {
      id: number
      fio: string
    }
  }>
  acks?: Array<{
    id: number
    user_id: number
    status: 'read' | 'confirmed'
    confirmed_at: string | null
    user?: {
      id: number
      fio: string
    }
  }>
}

export interface DocumentListResponse {
  data: DocumentDTO[]
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface DocTemplateDTO {
  id: number
  type: string
  name: string
  schema_json: Record<string, any>
  file_template_key: string | null
  created_at?: string
  updated_at?: string
}

export const documentsApi = {
  list: async (params?: {
    type?: string
    status?: string
    dateFrom?: string
    dateTo?: string
    search?: string
    page?: number
    per_page?: number
  }): Promise<DocumentListResponse> => {
    const response = await apiClient.get('/v1/documents', { params })
    return response.data
  },

  get: async (id: number): Promise<DocumentDTO> => {
    const response = await apiClient.get(`/v1/documents/${id}`)
    return response.data
  },

  create: async (payload: {
    type: string
    template_id: number
    data_json: Record<string, any>
    term_id?: number
  }): Promise<DocumentDTO> => {
    const response = await apiClient.post('/v1/documents', payload)
    return response.data
  },

  update: async (id: number, payload: {
    data_json: Record<string, any>
  }): Promise<DocumentDTO> => {
    const response = await apiClient.patch(`/v1/documents/${id}`, payload)
    return response.data
  },

  templates: async (params?: { type?: string }): Promise<DocTemplateDTO[]> => {
    const response = await apiClient.get('/v1/documents/templates', { params })
    return response.data
  },

  sendToApproval: async (id: number, payload: {
    route: Array<{ stepNo: number; approverRoleId?: number | null; approverUserId?: number | null }>
  }): Promise<DocumentDTO> => {
    const response = await apiClient.post(`/v1/documents/${id}/send-to-approval`, {
      route: payload.route.map(s => ({
        stepNo: s.stepNo,
        approverRoleId: s.approverRoleId,
        approverUserId: s.approverUserId,
      })),
    })
    return response.data
  },

  approve: async (id: number, payload?: {
    comment?: string
  }): Promise<DocumentDTO> => {
    const response = await apiClient.post(`/v1/documents/${id}/approve`, payload || {})
    return response.data
  },

  reject: async (id: number, payload: {
    comment: string
  }): Promise<DocumentDTO> => {
    const response = await apiClient.post(`/v1/documents/${id}/reject`, payload)
    return response.data
  },

  sign: async (id: number, payload?: {
    comment?: string
  }): Promise<DocumentDTO> => {
    const response = await apiClient.post(`/v1/documents/${id}/sign`, payload || {})
    return response.data
  },

  setAckTargets: async (id: number, payload: {
    userIds: number[]
  }): Promise<void> => {
    await apiClient.post(`/v1/documents/${id}/ack/targets`, payload)
  },

  confirmAck: async (id: number): Promise<void> => {
    await apiClient.post(`/v1/documents/${id}/ack/confirm`)
  },

  getAck: async (id: number): Promise<{
    data: Array<{
      id: number
      user_id: number
      status: 'read' | 'confirmed'
      confirmed_at: string | null
      user?: {
        id: number
        fio: string
      }
    }>
  }> => {
    const response = await apiClient.get(`/v1/documents/${id}/ack`)
    return response.data
  },

  registerNumber: async (id: number): Promise<DocumentDTO> => {
    const response = await apiClient.post(`/v1/documents/${id}/register-number`)
    return response.data
  },

  export: async (id: number, format: 'docx' | 'pdf'): Promise<Blob> => {
    const response = await apiClient.get(`/v1/documents/${id}/download`, {
      params: { format },
      responseType: 'blob',
    })
    return response.data
  },

  verify: async (hash: string): Promise<{
    valid: boolean
    document?: DocumentDTO
  }> => {
    const response = await apiClient.get(`/v1/documents/verify/${hash}`)
    return response.data
  },

  validateGost: async (id: number): Promise<{
    valid: boolean
    errors: string[]
  }> => {
    const response = await apiClient.post(`/v1/documents/${id}/gost/validate`)
    return response.data
  },
}
